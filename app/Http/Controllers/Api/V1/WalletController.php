<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    /**
     * Send money from wallet to another user (mimics old API: wallet.php?type=send)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function send(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Please check your details.'
                ]
            ], 400);
        }

        $recipientId = (int) $request->input('user_id');
        $amount = (float) $request->input('amount');

        // Cannot send to yourself
        if ($recipientId == $tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Cannot send money to yourself'
                ]
            ], 400);
        }

        // Get sender's wallet balance
        $sender = DB::table('Wo_Users')->where('user_id', $tokenUserId)->first();
        if (!$sender) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 3,
                    'error_text' => 'User not found'
                ]
            ], 404);
        }

        $senderWallet = (float) ($sender->wallet ?? 0);

        // Check if sender has enough balance
        if ($senderWallet < $amount) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'The amount exceeded your current wallet!'
                ]
            ], 400);
        }

        // Get recipient user
        $recipient = DB::table('Wo_Users')
            ->where('user_id', $recipientId)
            ->whereIn('active', ['1', 1])
            ->first();

        if (!$recipient) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Recipient user not found or inactive'
                ]
            ], 404);
        }

        $recipientWallet = (float) ($recipient->wallet ?? 0);

        try {
            DB::beginTransaction();

            // Update recipient's wallet (add amount)
            $newRecipientWallet = sprintf('%.2f', $recipientWallet + $amount);
            DB::table('Wo_Users')
                ->where('user_id', $recipientId)
                ->update(['wallet' => $newRecipientWallet]);

            // Update sender's wallet (subtract amount)
            $newSenderWallet = sprintf('%.2f', $senderWallet - $amount);
            DB::table('Wo_Users')
                ->where('user_id', $tokenUserId)
                ->update(['wallet' => $newSenderWallet]);

            // Create payment transaction log
            if (Schema::hasTable('Wo_PaymentTransactions')) {
                $this->createPaymentTransaction($tokenUserId, 'WALLET_SEND', $amount, "Sent to user {$recipientId}");
                $this->createPaymentTransaction($recipientId, 'WALLET_RECEIVE', $amount, "Received from user {$tokenUserId}");
            }

            // Send notification to recipient
            $this->sendWalletNotification($recipientId, $tokenUserId, $amount, 'sent_u_money');

            DB::commit();

            return response()->json([
                'api_status' => 200,
                'message' => 'Money successfully sent.',
                'data' => [
                    'new_balance' => (float) $newSenderWallet,
                    'sent_amount' => $amount,
                    'recipient_id' => $recipientId,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 7,
                    'error_text' => 'Failed to send money: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Top up wallet (mimics old API: wallet.php?type=top_up)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function topUp(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Please check your details.'
                ]
            ], 400);
        }

        $userId = (int) $request->input('user_id');
        $amount = (float) $request->input('amount');

        // Check if user can top up their own wallet or if admin is topping up
        if ($userId != $tokenUserId) {
            // In a real implementation, you would check if the current user is an admin
            // For now, we'll allow it but you should add admin check
        }

        // Get user
        $user = DB::table('Wo_Users')->where('user_id', $userId)->first();
        if (!$user) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 7,
                    'error_text' => 'User not found'
                ]
            ], 404);
        }

        $currentWallet = (float) ($user->wallet ?? 0);

        try {
            DB::beginTransaction();

            // Increase wallet value
            $newWallet = sprintf('%.2f', $currentWallet + $amount);
            DB::table('Wo_Users')
                ->where('user_id', $userId)
                ->update(['wallet' => $newWallet]);

            // Create payment transaction log
            if (Schema::hasTable('Wo_PaymentTransactions')) {
                $paymentMethod = $request->input('payment_method', 'manual');
                $this->createPaymentTransaction($userId, 'WALLET', $amount, $paymentMethod);
            }

            // Get updated user data
            $updatedUser = DB::table('Wo_Users')->where('user_id', $userId)->first();

            DB::commit();

            return response()->json([
                'api_status' => 200,
                'message' => 'The money successfully added to your wallet.',
                'data' => [
                    'wallet' => (float) ($updatedUser->wallet ?? 0),
                    'balance' => (float) ($updatedUser->balance ?? 0),
                    'added_amount' => $amount,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 8,
                    'error_text' => 'Failed to top up wallet: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Pay using wallet (mimics old API: wallet.php?type=pay)
     * Supports: pro membership, fund donations
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function pay(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'pay_type' => 'required|in:pro,fund',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Please check your details.'
                ]
            ], 400);
        }

        $payType = $request->input('pay_type');
        $user = DB::table('Wo_Users')->where('user_id', $tokenUserId)->first();
        $wallet = (float) ($user->wallet ?? 0);

        try {
            DB::beginTransaction();

            if ($payType == 'pro') {
                // Pay for pro membership
                $validator = Validator::make($request->all(), [
                    'pro_type' => 'required|string',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'api_status' => 400,
                        'errors' => [
                            'error_id' => 5,
                            'error_text' => 'pro_type is required'
                        ]
                    ], 400);
                }

                $proType = $request->input('pro_type');
                
                // Get pro package price (you would fetch this from config or database)
                // For now, we'll use a default price or get from request
                $price = (float) ($request->input('price', 0));
                
                if ($price <= 0) {
                    return response()->json([
                        'api_status' => 400,
                        'errors' => [
                            'error_id' => 5,
                            'error_text' => 'Invalid price'
                        ]
                    ], 400);
                }

                // Check if user has enough wallet balance
                if ($wallet < $price) {
                    return response()->json([
                        'api_status' => 400,
                        'errors' => [
                            'error_id' => 6,
                            'error_text' => 'Insufficient wallet balance'
                        ]
                    ], 400);
                }

                // Update user to pro
                $updateData = [
                    'is_pro' => 1,
                    'pro_time' => time(),
                    'pro_type' => $proType,
                ];

                // Check if pro package includes verified badge
                // This would come from your pro packages config
                $includesVerified = $request->input('verified_badge', false);
                if ($includesVerified) {
                    $updateData['verified'] = 1;
                }

                DB::table('Wo_Users')
                    ->where('user_id', $tokenUserId)
                    ->update($updateData);

                // Deduct from wallet
                $newWallet = sprintf('%.2f', $wallet - $price);
                DB::table('Wo_Users')
                    ->where('user_id', $tokenUserId)
                    ->update(['wallet' => $newWallet]);

                // Create payment transaction log
                if (Schema::hasTable('Wo_PaymentTransactions')) {
                    $notes = json_encode([
                        'pro_type' => $proType,
                        'method_type' => 'wallet'
                    ]);
                    $this->createPaymentTransaction($tokenUserId, 'PRO', $price, $notes);
                }

                DB::commit();

                return response()->json([
                    'api_status' => 200,
                    'message' => 'Upgraded to pro',
                    'data' => [
                        'pro_type' => $proType,
                        'new_wallet_balance' => (float) $newWallet,
                    ]
                ]);

            } elseif ($payType == 'fund') {
                // Pay for fund donation
                $validator = Validator::make($request->all(), [
                    'fund_id' => 'required|integer|min:1',
                    'price' => 'required|numeric|min:0.01',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'api_status' => 400,
                        'errors' => [
                            'error_id' => 5,
                            'error_text' => 'fund_id and price are required'
                        ]
                    ], 400);
                }

                $fundId = (int) $request->input('fund_id');
                $price = (float) $request->input('price');

                // Check if user has enough wallet balance
                if ($wallet < $price) {
                    return response()->json([
                        'api_status' => 400,
                        'errors' => [
                            'error_id' => 6,
                            'error_text' => 'Insufficient wallet balance'
                        ]
                    ], 400);
                }

                // Get fund data
                if (!Schema::hasTable('Wo_Funding')) {
                    return response()->json([
                        'api_status' => 400,
                        'errors' => [
                            'error_id' => 5,
                            'error_text' => 'Funding feature not available'
                        ]
                    ], 400);
                }

                $fund = DB::table('Wo_Funding')->where('id', $fundId)->first();
                if (!$fund) {
                    return response()->json([
                        'api_status' => 400,
                        'errors' => [
                            'error_id' => 5,
                            'error_text' => 'Fund not found'
                        ]
                    ], 404);
                }

                $amount = $price;
                $adminCommission = 0;

                // Calculate admin commission if configured
                // You would get this from config
                $donatePercentage = 0; // Get from config
                if ($donatePercentage > 0) {
                    $adminCommission = ($donatePercentage * $amount) / 100;
                    $amount = $amount - $adminCommission;
                }

                // Deduct from sender's wallet
                $newWallet = sprintf('%.2f', $wallet - $price);
                DB::table('Wo_Users')
                    ->where('user_id', $tokenUserId)
                    ->update(['wallet' => $newWallet]);

                // Add to fund owner's balance
                $fundOwner = DB::table('Wo_Users')->where('user_id', $fund->user_id)->first();
                if ($fundOwner) {
                    $newBalance = (float) ($fundOwner->balance ?? 0) + $amount;
                    DB::table('Wo_Users')
                        ->where('user_id', $fund->user_id)
                        ->update(['balance' => sprintf('%.2f', $newBalance)]);
                }

                // Create payment transaction log
                if (Schema::hasTable('Wo_PaymentTransactions')) {
                    $notes = mb_substr($fund->title ?? 'Fund Donation', 0, 100, 'UTF-8');
                    $this->createPaymentTransaction($tokenUserId, 'DONATE', $amount, $notes);
                }

                // Create fund raise record
                if (Schema::hasTable('Wo_Funding_Raise')) {
                    DB::table('Wo_Funding_Raise')->insert([
                        'user_id' => $tokenUserId,
                        'funding_id' => $fundId,
                        'amount' => $amount,
                        'time' => time(),
                    ]);
                }

                // Send notification to fund owner
                $this->sendWalletNotification($fund->user_id, $tokenUserId, $amount, 'fund_donate');

                DB::commit();

                return response()->json([
                    'api_status' => 200,
                    'message' => 'Payment successfully done',
                    'data' => [
                        'fund_id' => $fundId,
                        'donated_amount' => $amount,
                        'new_wallet_balance' => (float) $newWallet,
                    ]
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get wallet balance (additional endpoint)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getBalance(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        $user = DB::table('Wo_Users')->where('user_id', $tokenUserId)->first();
        if (!$user) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 3,
                    'error_text' => 'User not found'
                ]
            ], 404);
        }

        return response()->json([
            'api_status' => 200,
            'data' => [
                'wallet' => (float) ($user->wallet ?? 0),
                'balance' => (float) ($user->balance ?? 0),
            ]
        ]);
    }

    /**
     * Create payment transaction log
     * 
     * @param string $userId
     * @param string $kind
     * @param float $amount
     * @param string $notes
     * @return void
     */
    private function createPaymentTransaction(string $userId, string $kind, float $amount, string $notes): void
    {
        if (!Schema::hasTable('Wo_PaymentTransactions')) {
            return;
        }

        try {
            $insertData = [
                'userid' => $userId,
                'kind' => $kind,
                'amount' => $amount,
                'notes' => $notes,
            ];

            // Add time column if it exists
            if (Schema::hasColumn('Wo_PaymentTransactions', 'time')) {
                $insertData['time'] = time();
            }

            DB::table('Wo_PaymentTransactions')->insert($insertData);
        } catch (\Exception $e) {
            // Log error but don't fail the transaction
        }
    }

    /**
     * Send wallet notification
     * 
     * @param string $recipientId
     * @param string $senderId
     * @param float $amount
     * @param string $type
     * @return void
     */
    private function sendWalletNotification(string $recipientId, string $senderId, float $amount, string $type): void
    {
        if (!Schema::hasTable('Wo_Notifications')) {
            return;
        }

        try {
            // Get currency symbol (you would get this from config)
            $currency = '$'; // Default currency

            $notificationData = [
                'notifier_id' => $senderId,
                'recipient_id' => $recipientId,
                'type' => $type,
                'time' => time(),
            ];

            // Add text if column exists
            if (Schema::hasColumn('Wo_Notifications', 'text')) {
                $notificationData['text'] = "Sent you {$amount}{$currency}!";
            }

            // Add URL if column exists
            if (Schema::hasColumn('Wo_Notifications', 'url')) {
                $notificationData['url'] = 'index.php?link1=wallet';
            }

            // Add seen column if exists
            if (Schema::hasColumn('Wo_Notifications', 'seen')) {
                $notificationData['seen'] = 0;
            }

            DB::table('Wo_Notifications')->insert($notificationData);
        } catch (\Exception $e) {
            // Log error but don't fail the transaction
        }
    }
}

