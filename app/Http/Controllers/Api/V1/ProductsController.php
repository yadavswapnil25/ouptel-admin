<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ProductsController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $query = Product::query()
            ->where('active', '1')
            ->orderByDesc('id');

        if ($request->filled('category')) {
            $query->where('category', (int) $request->query('category'));
        }

        if ($request->filled('sub_category')) {
            $query->where('sub_category', (int) $request->query('sub_category'));
        }

        if ($request->filled('type')) { // 0=physical,1=digital
            $query->where('type', (string) $request->query('type'));
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->query('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->query('max_price'));
        }

        $term = $request->query('term', $request->query('q'));
        if (!empty($term)) {
            $like = '%' . str_replace('%', '\\%', $term) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('description', 'like', $like)
                  ->orWhere('location', 'like', $like);
            });
        }

        if ($request->boolean('only_my', false)) {
            $authHeader = $request->header('Authorization');
            if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
                return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
            }
            $token = substr($authHeader, 7);
            $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
            if (!$userId) {
                return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
            }
            $query->where('user_id', (string) $userId);
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Product $product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => (float) $product->price,
                'price_formatted' => $product->price_formatted,
                'currency' => $product->currency,
                'location' => $product->location,
                'category' => $product->category,
                'sub_category' => $product->sub_category,
                'status' => $product->status_text,
                'type' => $product->type_text,
                'posted_at' => $product->posted_date,
                'main_image' => $product->main_image,
                'rating' => $product->average_rating,
                'reviews' => $product->reviews_count,
                'orders' => $product->orders_count,
                'total_sales' => (float) $product->total_sales,
                'user' => [
                    'user_id' => optional($product->user)->user_id,
                    'username' => optional($product->user)->username,
                    'avatar_url' => optional($product->user)->avatar_url,
                ],
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function meta(): JsonResponse
    {
        $categories = ProductCategory::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]);

        return response()->json([
            'ok' => true,
            'data' => [
                'types' => [0 => 'Physical', 1 => 'Digital'],
                'categories' => $categories,
            ],
        ]);
    }

    public function my(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $request->merge(['only_my' => true]);
        return $this->index($request);
    }

    public function purchased(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $orders = DB::table('Wo_UserOrders')
            ->join('Wo_Products', 'Wo_UserOrders.product_id', '=', 'Wo_Products.id')
            ->where('Wo_UserOrders.user_id', $userId)
            ->orderByDesc('Wo_UserOrders.id')
            ->select(
                'Wo_UserOrders.*',
                'Wo_Products.name as product_name',
                'Wo_Products.description as product_description',
                'Wo_Products.currency as product_currency',
                'Wo_Products.user_id as owner_id',
                'Wo_Products.id as product_pk'
            )
            ->paginate($perPage);

        $data = $orders->getCollection()->map(function ($row) {
            return [
                'order_id' => $row->id,
                'hash_id' => $row->hash_id,
                'status' => $row->status,
                'status_text' => $row->status, // raw text; could map
                'units' => (int) $row->units,
                'price' => (float) $row->price,
                'final_price' => (float) $row->final_price,
                'order_date' => date('c', (int) $row->time),
                'product' => [
                    'id' => (int) $row->product_pk,
                    'name' => $row->product_name,
                    'description' => $row->product_description,
                    'currency' => $row->product_currency,
                ],
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'integer'],
            'sub_category' => ['nullable', 'integer'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'type' => ['required', 'integer', 'in:0,1'],
            'units' => ['nullable', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['string'],
        ]);

        $product = new Product();
        $product->user_id = (string) $userId;
        $product->name = $validated['name'];
        $product->description = $validated['description'] ?? '';
        $product->category = $validated['category'];
        $product->sub_category = $validated['sub_category'] ?? 0;
        $product->price = (float) $validated['price'];
        $product->currency = $validated['currency'];
        $product->type = (string) $validated['type'];
        $product->units = isset($validated['units']) ? (string) $validated['units'] : '0';
        $product->location = $validated['location'] ?? '';
        $product->status = '1'; // Active
        $product->active = '1';
        $product->time = (string) time();
        $product->save();

        if (!empty($validated['images'])) {
            foreach ($validated['images'] as $img) {
                ProductMedia::create([
                    'product_id' => $product->id,
                    'image' => $img,
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => 'Product created successfully',
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'price_formatted' => $product->price_formatted,
                'currency' => $product->currency,
                'type' => $product->type_text,
                'posted_at' => $product->posted_date,
                'main_image' => $product->main_image,
            ],
        ], 201);
    }

    /**
     * Buy products from cart (mimics old API: market.php?type=buy)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function buy(Request $request): JsonResponse
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
            'address_id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'address_id can not be empty'
                ]
            ], 400);
        }

        $addressId = (int) $request->input('address_id');

        // Validate address belongs to user
        $addressTable = 'Wo_UserAddress';
        if (!Schema::hasTable($addressTable)) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Address feature not available'
                ]
            ], 400);
        }

        $address = DB::table($addressTable)
            ->where('id', $addressId)
            ->where('user_id', $tokenUserId)
            ->first();

        if (!$address) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Address not found'
                ]
            ], 404);
        }

        // Get cart items
        $cartTable = 'Wo_UserCard';
        if (!Schema::hasTable($cartTable)) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Cart feature not available'
                ]
            ], 400);
        }

        $cartItems = DB::table($cartTable)
            ->where('user_id', $tokenUserId)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'No items found in cart'
                ]
            ], 400);
        }

        // Get user wallet
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

        $userWallet = (float) ($user->wallet ?? 0);

        // Get store commission percentage (from config or default to 0)
        $storeCommission = 0; // You would get this from config
        if (Schema::hasTable('Wo_Config')) {
            $config = DB::table('Wo_Config')->where('name', 'store_commission')->first();
            if ($config && !empty($config->value)) {
                $storeCommission = (float) $config->value;
            }
        }

        // Process cart items and group by product owner
        $ordersByOwner = [];
        $totalAmount = 0;
        $mainProduct = null;

        foreach ($cartItems as $cartItem) {
            $product = DB::table('Wo_Products')
                ->where('id', $cartItem->product_id)
                ->where('active', '1')
                ->first();

            if (!$product) {
                continue; // Skip invalid products
            }

            $requestedUnits = (int) ($cartItem->units ?? 1);
            $availableUnits = (int) ($product->units ?? 0);

            // Check if enough units available
            if ($requestedUnits > $availableUnits) {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 5,
                        'error_text' => "Insufficient units for product: {$product->name}"
                    ]
                ], 400);
            }

            $productPrice = (float) ($product->price ?? 0);
            $itemTotal = $productPrice * $requestedUnits;
            $totalAmount += $itemTotal;

            // Calculate commission
            $commission = 0;
            if ($storeCommission > 0) {
                $commission = round(($storeCommission * $itemTotal) / 100, 2);
            }
            $finalPrice = $itemTotal - $commission;

            $productOwnerId = $product->user_id;

            if (!isset($ordersByOwner[$productOwnerId])) {
                $ordersByOwner[$productOwnerId] = [];
            }

            $ordersByOwner[$productOwnerId][] = [
                'product_id' => $product->id,
                'price' => $productPrice,
                'units' => $requestedUnits,
                'item_total' => $itemTotal,
                'commission' => $commission,
                'final_price' => $finalPrice,
            ];

            if (!$mainProduct) {
                $mainProduct = $product;
            }
        }

        if (empty($ordersByOwner)) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'No valid products found in cart'
                ]
            ], 400);
        }

        // Check if user has enough wallet balance
        if ($userWallet < $totalAmount) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Insufficient wallet balance'
                ]
            ], 400);
        }

        try {
            DB::beginTransaction();

            $ordersTable = 'Wo_UserOrders';
            $purchasesTable = 'Wo_Purchases';

            // Process orders for each product owner
            foreach ($ordersByOwner as $productOwnerId => $ownerOrders) {
                $hashId = uniqid(rand(11111, 999999));
                $ownerTotal = 0;
                $ownerTotalCommission = 0;
                $ownerTotalFinalPrice = 0;

                foreach ($ownerOrders as $orderData) {
                    // Decrease product units
                    DB::table('Wo_Products')
                        ->where('id', $orderData['product_id'])
                        ->decrement('units', $orderData['units']);

                    $ownerTotal += $orderData['item_total'];
                    $ownerTotalCommission += $orderData['commission'];
                    $ownerTotalFinalPrice += $orderData['final_price'];

                    // Create order record
                    if (Schema::hasTable($ordersTable)) {
                        $orderInsertData = [
                            'user_id' => $tokenUserId,
                            'product_owner_id' => $productOwnerId,
                            'product_id' => $orderData['product_id'],
                            'price' => $orderData['item_total'],
                            'commission' => $orderData['commission'],
                            'final_price' => $orderData['final_price'],
                            'hash_id' => $hashId,
                            'units' => $orderData['units'],
                            'status' => 'placed',
                            'address_id' => $addressId,
                            'time' => time(),
                        ];

                        // Add optional columns if they exist
                        if (Schema::hasColumn($ordersTable, 'tracking_url')) {
                            $orderInsertData['tracking_url'] = '';
                        }
                        if (Schema::hasColumn($ordersTable, 'tracking_id')) {
                            $orderInsertData['tracking_id'] = '';
                        }

                        DB::table($ordersTable)->insert($orderInsertData);
                    }
                }

                // Create purchase record
                if (Schema::hasTable($purchasesTable)) {
                    $purchaseData = [
                        'user_id' => $tokenUserId,
                        'order_hash_id' => $hashId,
                        'price' => $ownerTotal,
                        'commission' => $ownerTotalCommission,
                        'final_price' => $ownerTotalFinalPrice,
                        'time' => time(),
                    ];

                    // Add data column if exists
                    if (Schema::hasColumn($purchasesTable, 'data')) {
                        $purchaseData['data'] = json_encode([
                            'name' => $mainProduct->name ?? ''
                        ]);
                    }

                    DB::table($purchasesTable)->insert($purchaseData);
                }

                // Create payment transaction for buyer
                if (Schema::hasTable('Wo_PaymentTransactions')) {
                    $this->createPaymentTransaction(
                        $tokenUserId,
                        'PURCHASE',
                        $ownerTotal,
                        'Product purchase'
                    );
                }

                // Create payment transaction for seller (will be credited when delivered)
                if (Schema::hasTable('Wo_PaymentTransactions')) {
                    $this->createPaymentTransaction(
                        $productOwnerId,
                        'SALE',
                        $ownerTotalFinalPrice,
                        'Product sale'
                    );
                }

                // Send notification to product owner
                $this->sendOrderNotification($productOwnerId, $tokenUserId, $hashId, 'new_orders');
            }

            // Deduct total from buyer's wallet
            $newWallet = sprintf('%.2f', $userWallet - $totalAmount);
            DB::table('Wo_Users')
                ->where('user_id', $tokenUserId)
                ->update(['wallet' => $newWallet]);

            // Clear cart
            DB::table($cartTable)
                ->where('user_id', $tokenUserId)
                ->delete();

            DB::commit();

            return response()->json([
                'api_status' => 200,
                'message' => 'Order placed successfully',
                'data' => [
                    'total_amount' => $totalAmount,
                    'new_wallet_balance' => (float) $newWallet,
                    'orders_count' => count($ordersByOwner),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Failed to process order: ' . $e->getMessage()
                ]
            ], 500);
        }
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

            if (Schema::hasColumn('Wo_PaymentTransactions', 'time')) {
                $insertData['time'] = time();
            }

            DB::table('Wo_PaymentTransactions')->insert($insertData);
        } catch (\Exception $e) {
            // Log error but don't fail
        }
    }

    /**
     * Send order notification
     * 
     * @param string $recipientId
     * @param string $senderId
     * @param string $hashId
     * @param string $type
     * @return void
     */
    private function sendOrderNotification(string $recipientId, string $senderId, string $hashId, string $type): void
    {
        if (!Schema::hasTable('Wo_Notifications')) {
            return;
        }

        try {
            $notificationData = [
                'notifier_id' => $senderId,
                'recipient_id' => $recipientId,
                'type' => $type,
                'time' => time(),
            ];

            if (Schema::hasColumn('Wo_Notifications', 'url')) {
                $notificationData['url'] = 'index.php?link1=orders';
            }

            if (Schema::hasColumn('Wo_Notifications', 'seen')) {
                $notificationData['seen'] = 0;
            }

            DB::table('Wo_Notifications')->insert($notificationData);
        } catch (\Exception $e) {
            // Log error but don't fail
        }
    }
}


