<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActivityController extends Controller
{
    public function recent(Request $request): JsonResponse
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $limit = max(1, min((int) $request->query('limit', 5), 20));
        $activities = [];

        try {
            if (Schema::hasTable('Wo_Comments')) {
                $activities = array_merge($activities, $this->getCommentActivities((string) $userId));
            }
            if (Schema::hasTable('Wo_Posts')) {
                $activities = array_merge($activities, $this->getPostActivities((string) $userId));
            }

            usort($activities, fn (array $a, array $b) => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));

            $activities = array_slice($activities, 0, $limit);
            foreach ($activities as &$activity) {
                $activity['time_text'] = $this->formatActivityTime((int) ($activity['time'] ?? 0));
            }
            unset($activity);

            return response()->json([
                'ok' => true,
                'data' => $activities,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to fetch recent activity',
                'data' => [],
            ], 500);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getCommentActivities(string $userId): array
    {
        $comments = DB::table('Wo_Comments')
            ->where('user_id', $userId)
            ->orderByDesc('time')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        $items = [];
        foreach ($comments as $comment) {
            $post = DB::table('Wo_Posts')
                ->where('post_id', $comment->post_id)
                ->orWhere('id', $comment->post_id)
                ->first();

            if (!$post) {
                continue;
            }

            $owner = DB::table('Wo_Users')->where('user_id', $post->user_id)->first();
            $ownerName = $this->getUserDisplayName($owner);
            $postTypeLabel = $this->getActivityPostTypeLabel($post);
            $commentText = trim(strip_tags((string) ($comment->text ?? '')));

            $items[] = [
                'id' => 'comment_' . $comment->id,
                'type' => 'comment',
                'time' => (int) ($comment->time ?? time()),
                'summary' => 'Commented on ' . $postTypeLabel . ' posted',
                'detail' => 'by ' . $ownerName . '.',
                'detail_user_id' => (int) ($post->user_id ?? 0),
                'detail_url' => $post->user_id ? '/profile/' . $post->user_id : null,
                'quote' => $commentText !== '' ? $this->truncateText($commentText, 120) : null,
                'post_id' => (int) ($post->post_id ?? $post->id ?? 0),
                'post_url' => '/post/' . ($post->post_id ?? $post->id),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getPostActivities(string $userId): array
    {
        $posts = DB::table('Wo_Posts')
            ->where('user_id', $userId)
            ->where('active', '1')
            ->orderByDesc('time')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $items = [];
        foreach ($posts as $post) {
            $postText = trim(strip_tags((string) ($post->postText ?? '')));
            $postPublicId = (int) ($post->post_id ?? $post->id ?? 0);
            $recipientId = (int) ($post->recipient_id ?? 0);
            $isShare = ((int) ($post->shared_from ?? 0) > 0) || ((int) ($post->parent_id ?? 0) > 0);
            $postType = strtolower((string) ($post->postType ?? ''));
            $typeLabel = $this->getActivityPostTypeLabel($post);

            if ($postType === 'birthday' && $recipientId > 0 && (string) $recipientId !== $userId) {
                $recipient = DB::table('Wo_Users')->where('user_id', $recipientId)->first();
                $recipientName = $this->getUserDisplayName($recipient);
                $items[] = [
                    'id' => 'post_' . $post->id,
                    'type' => 'birthday_wish',
                    'time' => (int) ($post->time ?? time()),
                    'summary' => 'Wished ' . $recipientName . ' a happy birthday on their timeline.',
                    'detail' => null,
                    'quote' => $postText !== '' ? $this->truncateText($postText, 120) : null,
                    'post_id' => $postPublicId,
                    'post_url' => '/post/' . $postPublicId,
                    'detail_user_id' => $recipientId,
                    'detail_url' => '/profile/' . $recipientId,
                ];
                continue;
            }

            if ($isShare) {
                $summary = 'Shared a ' . strtolower($typeLabel) . '.';
                if ($recipientId > 0 && (string) $recipientId !== $userId) {
                    $recipient = DB::table('Wo_Users')->where('user_id', $recipientId)->first();
                    $recipientName = $this->getUserDisplayName($recipient);
                    $summary = 'Shared a ' . strtolower($typeLabel) . ' on ' . $recipientName . '\'s timeline.';
                }

                $items[] = [
                    'id' => 'post_' . $post->id,
                    'type' => 'share',
                    'time' => (int) ($post->time ?? time()),
                    'summary' => $summary,
                    'detail' => null,
                    'quote' => $postText !== '' ? $this->truncateText($postText, 120) : null,
                    'post_id' => $postPublicId,
                    'post_url' => '/post/' . $postPublicId,
                ];
                continue;
            }

            if ($recipientId > 0 && (string) $recipientId !== $userId) {
                $recipient = DB::table('Wo_Users')->where('user_id', $recipientId)->first();
                $recipientName = $this->getUserDisplayName($recipient);
                $items[] = [
                    'id' => 'post_' . $post->id,
                    'type' => 'wall_post',
                    'time' => (int) ($post->time ?? time()),
                    'summary' => 'Wrote on ' . $recipientName . '\'s timeline.',
                    'detail' => null,
                    'quote' => $postText !== '' ? $this->truncateText($postText, 120) : null,
                    'post_id' => $postPublicId,
                    'post_url' => '/post/' . $postPublicId,
                    'detail_user_id' => $recipientId,
                    'detail_url' => '/profile/' . $recipientId,
                ];
                continue;
            }

            if ($postText !== '') {
                $summary = 'Posted your status.';
                $quote = $this->truncateText($postText, 120);
            } else {
                $summary = 'Posted a ' . strtolower($typeLabel) . '.';
                $quote = null;
            }

            $items[] = [
                'id' => 'post_' . $post->id,
                'type' => 'post',
                'time' => (int) ($post->time ?? time()),
                'summary' => $summary,
                'detail' => null,
                'quote' => $quote,
                'post_id' => $postPublicId,
                'post_url' => '/post/' . $postPublicId,
            ];
        }

        return $items;
    }

    private function getUserDisplayName(?object $user): string
    {
        if (!$user) {
            return 'Unknown User';
        }

        $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $fullName !== ''
            ? $fullName
            : ($user->name ?? $user->username ?? 'Unknown User');
    }

    private function getActivityPostTypeLabel(object $post): string
    {
        $type = strtolower((string) ($post->postType ?? ''));

        if (in_array($type, ['video', 'youtube'], true) || !empty($post->postYoutube)) {
            return 'Video';
        }
        if (in_array($type, ['photo', 'album', 'picture'], true) || !empty($post->postPhoto)) {
            return 'Photo';
        }
        if ($type === 'link' || !empty($post->postLink)) {
            return 'Link';
        }
        if ($type === 'birthday') {
            return 'Birthday wish';
        }
        if ($type === 'file' || !empty($post->postFile)) {
            return 'File';
        }

        return 'Post';
    }

    private function truncateText(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLength - 3)) . '...';
    }

    private function formatActivityTime(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return 'Just now';
        }

        $time = time() - $timestamp;

        if ($time < 60) {
            return 'Just now';
        }
        if ($time < 3600) {
            $value = max(1, (int) floor($time / 60));

            return $value . ' ' . ($value === 1 ? 'Minute' : 'Minutes') . ' Ago';
        }
        if ($time < 86400) {
            $value = max(1, (int) floor($time / 3600));

            return $value . ' ' . ($value === 1 ? 'Hour' : 'Hours') . ' Ago';
        }
        if ($time < 2592000) {
            $value = max(1, (int) floor($time / 86400));

            return $value . ' ' . ($value === 1 ? 'Day' : 'Days') . ' Ago';
        }
        if ($time < 31536000) {
            $value = max(1, (int) floor($time / 2592000));

            return $value . ' ' . ($value === 1 ? 'Month' : 'Months') . ' Ago';
        }

        $value = max(1, (int) floor($time / 31536000));

        return $value . ' ' . ($value === 1 ? 'Year' : 'Years') . ' Ago';
    }
}
