<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AuthController extends BaseController
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['nullable', 'string'],
            'email' => ['nullable', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (empty($validated['username']) && empty($validated['email'])) {
            return response()->json(['ok' => false, 'message' => 'Username or email is required'], 422);
        }

        $query = User::query();
        if (!empty($validated['username'])) {
            $query->where('username', $validated['username']);
        } else {
            $query->where('email', $validated['email']);
        }

        /** @var User|null $user */
        $user = $query->first();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Invalid credentials'], 401);
        }

        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json(['ok' => false, 'message' => 'Invalid credentials'], 401);
        }

        if ($user->active === '2') {
            return response()->json(['ok' => false, 'message' => 'Account banned'], 403);
        }

        // Create legacy-style session token in Wo_AppsSessions (legacy WoWonder naming)
        $token = Str::random(64);
        DB::table('Wo_AppsSessions')->insert([
            'user_id' => $user->user_id,
            'session_id' => $token,
            'platform' => 'phone',
            'time' => time(),
        ]);

        return response()->json([
            'ok' => true,
            'data' => [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'verified' => $user->verified === '1',
                'active' => $user->active,
                'token' => $token,
            ],
        ]);
    }

    /**
     * Register a new user (mimics WoWonder user registration)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function signup(Request $request): JsonResponse
    {
        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|min:3|max:32|regex:/^[a-zA-Z0-9_]+$/|unique:Wo_Users,username',
            'email' => 'required|email|max:100|unique:Wo_Users,email',
            'password' => 'required|string|min:6|max:100',
            'confirm_password' => 'required|string|same:password',
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'gender' => 'nullable|string|in:male,female',
            'birthday' => 'nullable|date|before:today',
            'country_id' => 'nullable|integer',
            'timezone' => 'nullable|string|max:50',
            'language' => 'nullable|string|max:10',
            'referrer' => 'nullable|string|max:50',
            'device_id' => 'nullable|string|max:100',
            'platform' => 'nullable|string|max:20',
            'hash' => 'nullable|string', // For compatibility with WoWonder hash system
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Check if username or email already exists
            $existingUser = User::where('username', $request->username)
                ->orWhere('email', $request->email)
                ->first();

            if ($existingUser) {
                if ($existingUser->username === $request->username) {
                    return response()->json(['ok' => false, 'message' => 'Username already taken'], 409);
                }
                if ($existingUser->email === $request->email) {
                    return response()->json(['ok' => false, 'message' => 'Email already registered'], 409);
                }
            }

            // Generate unique user ID
            $userId = $this->generateUserId();

            // Prepare user data - only include fields that exist in Wo_Users table
            $userData = [
                'user_id' => $userId,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'verified' => '0', // Email verification required
                'active' => '1', // Active by default
                'avatar' => '',
                'cover' => '',
            ];

            // Add optional fields if they exist in the database
            if ($request->has('first_name')) {
                $userData['first_name'] = $request->first_name;
            }
            if ($request->has('last_name')) {
                $userData['last_name'] = $request->last_name;
            }
            if ($request->has('gender')) {
                $userData['gender'] = $request->gender;
            }
            if ($request->has('birthday')) {
                $userData['birthday'] = $request->birthday;
            }
            if ($request->has('country_id')) {
                $userData['country_id'] = $request->country_id;
            }
            if ($request->has('timezone')) {
                $userData['timezone'] = $request->timezone;
            }
            if ($request->has('language')) {
                $userData['language'] = $request->language;
            }

            // Create the user
            $user = User::create($userData);

            // Create session token
            $token = Str::random(64);
            DB::table('Wo_AppsSessions')->insert([
                'user_id' => $user->user_id,
                'session_id' => $token,
                'platform' => $request->platform ?? 'phone',
                'time' => time(),
            ]);

            // Handle referrer if provided
            if ($request->referrer) {
                $this->handleReferrer($user->user_id, $request->referrer);
            }

            // Send welcome email (placeholder for now)
            $this->sendWelcomeEmail($user);

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Account created successfully',
                'data' => [
                    'user_id' => $user->user_id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'first_name' => $user->first_name ?? '',
                    'last_name' => $user->last_name ?? '',
                    'display_name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->username,
                    'avatar_url' => $user->avatar_url,
                    'verified' => $user->verified === '1',
                    'active' => $user->active,
                    'joined_at' => date('c', $user->joined),
                    'token' => $token,
                    'requires_verification' => $user->verified === '0',
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('User registration failed: ' . $e->getMessage(), [
                'username' => $request->username,
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Registration failed. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify email address
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // In a real implementation, you would check the verification code
            // For now, we'll just mark the user as verified
            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                return response()->json(['ok' => false, 'message' => 'User not found'], 404);
            }

            if ($user->verified === '1') {
                return response()->json(['ok' => false, 'message' => 'Email already verified'], 409);
            }

            $user->update(['verified' => '1']);

            return response()->json([
                'ok' => true,
                'message' => 'Email verified successfully',
                'data' => [
                    'user_id' => $user->user_id,
                    'email' => $user->email,
                    'verified' => true,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Email verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resend verification email
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                return response()->json(['ok' => false, 'message' => 'User not found'], 404);
            }

            if ($user->verified === '1') {
                return response()->json(['ok' => false, 'message' => 'Email already verified'], 409);
            }

            // Send verification email (placeholder for now)
            $this->sendVerificationEmail($user);

            return response()->json([
                'ok' => true,
                'message' => 'Verification email sent successfully',
                'data' => [
                    'email' => $user->email,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to resend verification email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check username availability
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function checkUsername(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|min:3|max:32|regex:/^[a-zA-Z0-9_]+$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid username format',
                'errors' => $validator->errors()
            ], 422);
        }

        $username = $request->username;
        $exists = User::where('username', $username)->exists();

        return response()->json([
            'ok' => true,
            'data' => [
                'username' => $username,
                'available' => !$exists,
                'message' => $exists ? 'Username is already taken' : 'Username is available',
            ]
        ]);
    }

    /**
     * Check email availability
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid email format',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;
        $exists = User::where('email', $email)->exists();

        return response()->json([
            'ok' => true,
            'data' => [
                'email' => $email,
                'available' => !$exists,
                'message' => $exists ? 'Email is already registered' : 'Email is available',
            ]
        ]);
    }

    /**
     * Generate unique user ID
     * 
     * @return int
     */
    private function generateUserId(): int
    {
        do {
            $userId = rand(100000, 999999);
        } while (User::where('user_id', $userId)->exists());

        return $userId;
    }

    /**
     * Handle referrer logic
     * 
     * @param int $userId
     * @param string $referrer
     * @return void
     */
    private function handleReferrer(int $userId, string $referrer): void
    {
        // Find referrer user
        $referrerUser = User::where('username', $referrer)->first();
        
        if ($referrerUser) {
            // Update user's referrer info
            User::where('user_id', $userId)->update([
                'ref_user_id' => $referrerUser->user_id,
                'referrer' => $referrer,
            ]);

            // In a real implementation, you might give rewards to the referrer
            // or track referral statistics
        }
    }

    /**
     * Send welcome email
     * 
     * @param User $user
     * @return void
     */
    private function sendWelcomeEmail(User $user): void
    {
        // In a real implementation, you would send a welcome email
        // For now, we'll just log the action
        Log::info("Welcome email sent to user {$user->user_id} ({$user->email})");
    }

    /**
     * Send verification email
     * 
     * @param User $user
     * @return void
     */
    private function sendVerificationEmail(User $user): void
    {
        // In a real implementation, you would send a verification email
        // For now, we'll just log the action
        Log::info("Verification email sent to user {$user->user_id} ({$user->email})");
    }
}


