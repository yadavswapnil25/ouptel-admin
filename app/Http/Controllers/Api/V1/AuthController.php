<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

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
}


