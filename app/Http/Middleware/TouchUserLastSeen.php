<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Keeps Wo_Users.lastseen fresh so presence ("Online" / "Last seen ...") tracks
 * real activity. Nothing else in the application writes that column; it was
 * previously only maintained by the legacy WoWonder front controller.
 *
 * The stale check lives in the WHERE clause, so this self-throttles to one
 * write per user per window no matter how many requests arrive, and needs no
 * cache or coordination between app servers. The write runs after the response
 * has been sent so it costs the caller nothing.
 */
class TouchUserLastSeen
{
    private const REFRESH_AFTER_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $userId = $request->attributes->get('apps_session_user_id');
        if (! $userId) {
            return;
        }

        $now = time();
        $staleBefore = $now - self::REFRESH_AFTER_SECONDS;

        try {
            DB::table('Wo_Users')
                ->where('user_id', (int) $userId)
                ->where(function ($query) use ($staleBefore) {
                    $query->whereNull('lastseen')
                        ->orWhere('lastseen', '<', $staleBefore);
                })
                ->update(['lastseen' => $now]);
        } catch (Throwable $e) {
            // Presence is best effort and must never break the request.
            report($e);
        }
    }
}
