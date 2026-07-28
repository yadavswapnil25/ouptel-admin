<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * When a Bearer apps-session token is present, ensure the user still exists
 * and is active. Clears orphaned sessions for deleted/banned accounts so
 * admin user deletion immediately logs the user out of the website.
 */
class EnsureAppsSessionUserExists
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $next($request);
        }

        $token = trim(substr($authHeader, 7));
        if ($token === '') {
            return $next($request);
        }

        $session = DB::table('Wo_AppsSessions')->where('session_id', $token)->first();
        // Missing session: leave to controllers (and allow login/register with stale headers).
        if (!$session) {
            return $next($request);
        }

        $user = DB::table('Wo_Users')
            ->where('user_id', $session->user_id)
            ->first(['user_id', 'active']);

        $active = $user ? (string) ($user->active ?? '') : '';
        $inactive = in_array($active, ['0', '2'], true);

        if (!$user || $inactive) {
            DB::table('Wo_AppsSessions')->where('session_id', $token)->delete();

            return response()->json([
                'ok' => false,
                'message' => 'Account unavailable. Please log in again.',
            ], 401);
        }

        $request->attributes->set('apps_session_user_id', (string) $session->user_id);

        return $next($request);
    }
}
