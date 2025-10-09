<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MyInformationController extends Controller
{
    /**
     * Non-allowed user data fields (sensitive information)
     */
    private $nonAllowed = [
        'password',
        'email_code',
        'sms_code',
        'src',
        'ip_address',
        'password_reset_code',
        'social_login',
        'wallet',
        'balance',
        'ref_user_id',
        'referrer',
        'admin',
        'two_factor',
        'two_factor_verified',
        'two_factor_method',
        'new_email',
        'new_phone',
    ];

    /**
     * Get user's complete information (mimics WoWonder download_info.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getMyInformation(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'No session sent.'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'Session id is wrong.'
                ]
            ], 401);
        }

        // Validate data parameter
        $validator = Validator::make($request->all(), [
            'data' => 'required|string', // Comma-separated: my_information,posts,pages,groups,followers,following,friends
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => $validator->errors()->all()
            ], 422);
        }

        try {
            // Parse data parameter
            $fetch = explode(',', $request->input('data'));
            $dataTypes = array_flip(array_map('trim', $fetch));

            $userInfo = [];

            // Get user's basic information and settings
            if (isset($dataTypes['my_information'])) {
                $userData = User::where('user_id', $tokenUserId)->first();
                if ($userData) {
                    $settings = $userData->toArray();
                    
                    // Remove sensitive fields
                    foreach ($this->nonAllowed as $field) {
                        unset($settings[$field]);
                    }

                    // Get sessions
                    $settings['sessions'] = DB::table('Wo_AppsSessions')
                        ->where('user_id', $tokenUserId)
                        ->get()
                        ->toArray();

                    // Get blocked users
                    $settings['blocked_users'] = DB::table('Wo_Blocks')
                        ->join('Wo_Users', 'Wo_Blocks.blocked', '=', 'Wo_Users.user_id')
                        ->where('Wo_Blocks.blocker', $tokenUserId)
                        ->select('Wo_Users.user_id', 'Wo_Users.username', 'Wo_Users.name', 'Wo_Users.email')
                        ->get()
                        ->toArray();

                    // Get transactions (if table exists)
                    try {
                        $settings['transactions'] = DB::table('Wo_PaymentTransactions')
                            ->where('user_id', $tokenUserId)
                            ->orderBy('id', 'DESC')
                            ->limit(100)
                            ->get()
                            ->toArray();
                    } catch (\Exception $e) {
                        $settings['transactions'] = [];
                    }

                    // Get referrers (if table exists)
                    try {
                        $settings['referrers'] = DB::table('Wo_Users')
                            ->where('ref_user_id', $tokenUserId)
                            ->select('user_id', 'username', 'name', 'email', 'registered')
                            ->get()
                            ->toArray();
                    } catch (\Exception $e) {
                        $settings['referrers'] = [];
                    }

                    $userInfo['my_information'] = $settings;
                }
            }

            // Get user's posts
            if (isset($dataTypes['posts'])) {
                $userInfo['posts'] = DB::table('Wo_Posts')
                    ->where('user_id', $tokenUserId)
                    ->where('active', 1)
                    ->orderBy('post_id', 'DESC')
                    ->limit(10000)
                    ->get()
                    ->toArray();
            }

            // Get user's pages
            if (isset($dataTypes['pages'])) {
                $userInfo['pages'] = DB::table('Wo_Pages')
                    ->where('user_id', $tokenUserId)
                    ->orderBy('page_id', 'DESC')
                    ->get()
                    ->toArray();
            }

            // Get user's groups
            if (isset($dataTypes['groups'])) {
                $userInfo['groups'] = DB::table('Wo_GroupMembers')
                    ->join('Wo_Groups', 'Wo_GroupMembers.group_id', '=', 'Wo_Groups.id')
                    ->where('Wo_GroupMembers.user_id', $tokenUserId)
                    ->select('Wo_Groups.*')
                    ->get()
                    ->toArray();
            }

            // Get followers
            if (isset($dataTypes['followers'])) {
                $userInfo['followers'] = DB::table('Wo_Followers')
                    ->join('Wo_Users', 'Wo_Followers.follower_id', '=', 'Wo_Users.user_id')
                    ->where('Wo_Followers.following_id', $tokenUserId)
                    ->select('Wo_Users.user_id', 'Wo_Users.username', 'Wo_Users.name', 'Wo_Users.email', 'Wo_Users.avatar')
                    ->limit(100000)
                    ->get()
                    ->toArray();
            }

            // Get following
            if (isset($dataTypes['following'])) {
                $userInfo['following'] = DB::table('Wo_Followers')
                    ->join('Wo_Users', 'Wo_Followers.following_id', '=', 'Wo_Users.user_id')
                    ->where('Wo_Followers.follower_id', $tokenUserId)
                    ->select('Wo_Users.user_id', 'Wo_Users.username', 'Wo_Users.name', 'Wo_Users.email', 'Wo_Users.avatar')
                    ->limit(100000)
                    ->get()
                    ->toArray();
            }

            // Get friends
            if (isset($dataTypes['friends'])) {
                $userInfo['friends'] = DB::table('Wo_Friends')
                    ->join('Wo_Users', function($join) use ($tokenUserId) {
                        $join->on(function($query) use ($tokenUserId) {
                            $query->on('Wo_Friends.friend_id', '=', 'Wo_Users.user_id')
                                  ->where('Wo_Friends.user_id', $tokenUserId);
                        })
                        ->orOn(function($query) use ($tokenUserId) {
                            $query->on('Wo_Friends.user_id', '=', 'Wo_Users.user_id')
                                  ->where('Wo_Friends.friend_id', $tokenUserId);
                        });
                    })
                    ->where('Wo_Friends.status', '2') // Accepted friends only
                    ->select('Wo_Users.user_id', 'Wo_Users.username', 'Wo_Users.name', 'Wo_Users.email', 'Wo_Users.avatar')
                    ->limit(100000)
                    ->get()
                    ->toArray();
            }

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'user_info' => $userInfo,
                'message' => 'User information retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'Failed to get user information: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Download user information as file (mimics WoWonder download_info.php file generation)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function downloadMyInformation(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'No session sent.'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'Session id is wrong.'
                ]
            ], 401);
        }

        // Validate data parameter
        $validator = Validator::make($request->all(), [
            'data' => 'required|string', // Comma-separated: my_information,posts,pages,groups,followers,following,friends
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => $validator->errors()->all()
            ], 422);
        }

        try {
            // Get user information
            $infoRequest = new Request(['data' => $request->input('data')]);
            $infoRequest->headers->set('Authorization', $authHeader);
            $userInfo = $this->getMyInformation($infoRequest);
            $userInfoData = json_decode($userInfo->content(), true);

            if ($userInfoData['api_status'] !== '200') {
                return response()->json($userInfoData, 500);
            }

            // Generate HTML content
            $html = $this->generateHtmlReport($userInfoData['user_info'], $tokenUserId);

            // Create directory structure
            $year = date('Y');
            $month = date('m');
            $day = date('d');
            $dir = "upload/files/{$year}/{$month}";
            
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir, 0755, true);
            }

            // Generate unique filename
            $filename = Str::random(40) . "_{$day}_" . md5(time()) . "_info.html";
            $filePath = "{$dir}/{$filename}";

            // Save HTML file
            Storage::disk('public')->put($filePath, $html);

            // Delete old info file if exists
            $user = User::where('user_id', $tokenUserId)->first();
            if ($user && $user->info_file) {
                Storage::disk('public')->delete($user->info_file);
            }

            // Update user's info_file
            DB::table('Wo_Users')
                ->where('user_id', $tokenUserId)
                ->update(['info_file' => $filePath]);

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Your information file is ready for download',
                'link' => asset('storage/' . $filePath),
                'file_path' => $filePath
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to generate information file: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Generate HTML report from user information
     */
    private function generateHtmlReport(array $userInfo, int $userId): string
    {
        $user = User::where('user_id', $userId)->first();
        $siteName = config('app.name', 'Ouptel');
        
        $html = "<!DOCTYPE html>\n";
        $html .= "<html>\n<head>\n";
        $html .= "<meta charset='UTF-8'>\n";
        $html .= "<title>My Information - {$siteName}</title>\n";
        $html .= "<style>\n";
        $html .= "body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }\n";
        $html .= ".container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }\n";
        $html .= "h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }\n";
        $html .= "h2 { color: #555; margin-top: 30px; }\n";
        $html .= "table { width: 100%; border-collapse: collapse; margin: 20px 0; }\n";
        $html .= "th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }\n";
        $html .= "th { background-color: #007bff; color: white; }\n";
        $html .= "tr:hover { background-color: #f5f5f5; }\n";
        $html .= ".info-item { margin: 10px 0; }\n";
        $html .= ".info-label { font-weight: bold; color: #555; }\n";
        $html .= ".info-value { color: #333; }\n";
        $html .= ".section { margin: 30px 0; padding: 20px; background: #f9f9f9; border-left: 4px solid #007bff; }\n";
        $html .= "</style>\n";
        $html .= "</head>\n<body>\n";
        $html .= "<div class='container'>\n";
        $html .= "<h1>My Information - {$siteName}</h1>\n";
        $html .= "<p>Generated on: " . date('F j, Y, g:i a') . "</p>\n";
        $html .= "<p>User: {$user->name} (@{$user->username})</p>\n";

        // My Information Section
        if (isset($userInfo['my_information'])) {
            $html .= "<div class='section'>\n";
            $html .= "<h2>Account Information</h2>\n";
            $info = $userInfo['my_information'];
            
            $html .= "<div class='info-item'><span class='info-label'>Username:</span> <span class='info-value'>{$info['username']}</span></div>\n";
            $html .= "<div class='info-item'><span class='info-label'>Email:</span> <span class='info-value'>{$info['email']}</span></div>\n";
            $html .= "<div class='info-item'><span class='info-label'>Name:</span> <span class='info-value'>{$info['name']}</span></div>\n";
            $html .= "<div class='info-item'><span class='info-label'>Gender:</span> <span class='info-value'>{$info['gender']}</span></div>\n";
            
            if (isset($info['sessions']) && count($info['sessions']) > 0) {
                $html .= "<h3>Active Sessions (" . count($info['sessions']) . ")</h3>\n";
                $html .= "<table><tr><th>Session ID</th><th>Platform</th><th>Time</th></tr>\n";
                foreach ($info['sessions'] as $session) {
                    $session = (array) $session;
                    $html .= "<tr><td>{$session['session_id']}</td><td>{$session['platform_type']}</td><td>" . date('Y-m-d H:i:s', $session['time']) . "</td></tr>\n";
                }
                $html .= "</table>\n";
            }

            if (isset($info['blocked_users']) && count($info['blocked_users']) > 0) {
                $html .= "<h3>Blocked Users (" . count($info['blocked_users']) . ")</h3>\n";
                $html .= "<table><tr><th>Username</th><th>Name</th></tr>\n";
                foreach ($info['blocked_users'] as $blocked) {
                    $blocked = (array) $blocked;
                    $html .= "<tr><td>@{$blocked['username']}</td><td>{$blocked['name']}</td></tr>\n";
                }
                $html .= "</table>\n";
            }
            
            $html .= "</div>\n";
        }

        // Posts Section
        if (isset($userInfo['posts'])) {
            $html .= "<div class='section'>\n";
            $html .= "<h2>Posts (" . count($userInfo['posts']) . ")</h2>\n";
            if (count($userInfo['posts']) > 0) {
                $html .= "<table><tr><th>Post ID</th><th>Text</th><th>Privacy</th><th>Time</th></tr>\n";
                foreach ($userInfo['posts'] as $post) {
                    $post = (array) $post;
                    $postText = substr($post['postText'] ?? '', 0, 100);
                    $html .= "<tr><td>{$post['post_id']}</td><td>{$postText}</td><td>{$post['postPrivacy']}</td><td>" . date('Y-m-d H:i:s', $post['time']) . "</td></tr>\n";
                }
                $html .= "</table>\n";
            } else {
                $html .= "<p>No posts found.</p>\n";
            }
            $html .= "</div>\n";
        }

        // Pages Section
        if (isset($userInfo['pages'])) {
            $html .= "<div class='section'>\n";
            $html .= "<h2>Pages (" . count($userInfo['pages']) . ")</h2>\n";
            if (count($userInfo['pages']) > 0) {
                $html .= "<table><tr><th>Page Name</th><th>Category</th><th>Likes</th></tr>\n";
                foreach ($userInfo['pages'] as $page) {
                    $page = (array) $page;
                    $html .= "<tr><td>{$page['page_name']}</td><td>{$page['category']}</td><td>" . ($page['likes'] ?? 0) . "</td></tr>\n";
                }
                $html .= "</table>\n";
            } else {
                $html .= "<p>No pages found.</p>\n";
            }
            $html .= "</div>\n";
        }

        // Groups Section
        if (isset($userInfo['groups'])) {
            $html .= "<div class='section'>\n";
            $html .= "<h2>Groups (" . count($userInfo['groups']) . ")</h2>\n";
            if (count($userInfo['groups']) > 0) {
                $html .= "<table><tr><th>Group Name</th><th>Privacy</th><th>Members</th></tr>\n";
                foreach ($userInfo['groups'] as $group) {
                    $group = (array) $group;
                    $html .= "<tr><td>{$group['group_name']}</td><td>{$group['privacy']}</td><td>" . ($group['members'] ?? 0) . "</td></tr>\n";
                }
                $html .= "</table>\n";
            } else {
                $html .= "<p>No groups found.</p>\n";
            }
            $html .= "</div>\n";
        }

        // Followers Section
        if (isset($userInfo['followers'])) {
            $html .= "<div class='section'>\n";
            $html .= "<h2>Followers (" . count($userInfo['followers']) . ")</h2>\n";
            if (count($userInfo['followers']) > 0) {
                $html .= "<table><tr><th>Username</th><th>Name</th></tr>\n";
                foreach ($userInfo['followers'] as $follower) {
                    $follower = (array) $follower;
                    $html .= "<tr><td>@{$follower['username']}</td><td>{$follower['name']}</td></tr>\n";
                }
                $html .= "</table>\n";
            } else {
                $html .= "<p>No followers found.</p>\n";
            }
            $html .= "</div>\n";
        }

        // Following Section
        if (isset($userInfo['following'])) {
            $html .= "<div class='section'>\n";
            $html .= "<h2>Following (" . count($userInfo['following']) . ")</h2>\n";
            if (count($userInfo['following']) > 0) {
                $html .= "<table><tr><th>Username</th><th>Name</th></tr>\n";
                foreach ($userInfo['following'] as $following) {
                    $following = (array) $following;
                    $html .= "<tr><td>@{$following['username']}</td><td>{$following['name']}</td></tr>\n";
                }
                $html .= "</table>\n";
            } else {
                $html .= "<p>Not following anyone.</p>\n";
            }
            $html .= "</div>\n";
        }

        // Friends Section
        if (isset($userInfo['friends'])) {
            $html .= "<div class='section'>\n";
            $html .= "<h2>Friends (" . count($userInfo['friends']) . ")</h2>\n";
            if (count($userInfo['friends']) > 0) {
                $html .= "<table><tr><th>Username</th><th>Name</th></tr>\n";
                foreach ($userInfo['friends'] as $friend) {
                    $friend = (array) $friend;
                    $html .= "<tr><td>@{$friend['username']}</td><td>{$friend['name']}</td></tr>\n";
                }
                $html .= "</table>\n";
            } else {
                $html .= "<p>No friends found.</p>\n";
            }
            $html .= "</div>\n";
        }

        $html .= "</div>\n</body>\n</html>";

        return $html;
    }
}

