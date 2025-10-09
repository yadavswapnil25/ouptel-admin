<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Get general settings (mimics WoWonder get_settings.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getSettings(Request $request): JsonResponse
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

        // Check if user exists
        $user = User::where('user_id', $tokenUserId)->first();
        if (!$user) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'Username is not exists.'
                ]
            ], 404);
        }

        try {
            // Get all config settings from Wo_Config table
            $config = DB::table('Wo_Config')->pluck('value', 'name')->toArray();
            
            if (empty($config)) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '6',
                        'error_text' => 'No settings available.'
                    ]
                ], 404);
            }

            // Remove sensitive configuration keys (as per old API)
            $nonAllowedConfig = [
                'reCaptchaKey',
                'google_map_api',
                'googleAnalytics',
                'AllLogin',
                'googleLogin',
                'facebookLogin',
                'twitterLogin',
                'linkedinLogin',
                'VkontakteLogin',
                'facebookAppId',
                'facebookAppKey',
                'googleAppId',
                'googleAppKey',
                'twitterAppId',
                'twitterAppKey',
                'linkedinAppId',
                'linkedinAppKey',
                'VkontakteAppId',
                'VkontakteAppKey',
                'smtp_username',
                'smtp_host',
                'smtp_password',
                'smtp_port',
                'smtp_encryption',
                'sms_or_email',
                'sms_username',
                'sms_password',
                'sms_phone_number',
                'eapi',
                'paypal_id',
                'paypal_secret',
                'paypal_mode',
                'weekly_price',
                'monthly_price',
                'yearly_price',
                'lifetime_price',
                'post_limit',
                'user_limit',
                'css_upload',
                'smooth_loading',
                'video_chat',
                'video_accountSid',
                'video_apiKeySid',
                'video_apiKeySecret',
                'video_configurationProfileSid',
                'monthly_boosts',
                'yearly_boosts',
                'lifetime_boosts',
                'instagramAppId',
                'instagramAppkey',
                'instagramLogin',
                'smtp_or_mail',
                'maintenance_mode',
                'developers_page',
                'order_posts_by',
                'groups',
                'pages',
                'games',
                'last_backup',
                'currency',
                'pro',
                'is_ok',
                'profile_privacy',
                'emailValidation',
                'emailNotification',
                'seoLink',
                'reCaptcha',
                'connectivitySystem',
                'profileVisit',
                'censored_words',
                'online_sidebar',
                'second_post_button',
                'useSeoFrindly',
                'cacheSystem',
                'sms_t_phone_number',
                'amazone_s3_s_key',
                'amazone_s3_key',
                'sms_twilio_username',
                'sms_twilio_password',
                'amazone_s3',
                'stripe_secret',
                'stripe_id',
                'siteEmail',
                'mime_types',
                'classified_currency_s',
                'classified_currency',
                'amount_ref',
                'amount_percent_ref',
                'widnows_app_api_id',
                'widnows_app_api_key',
                'android_api_id',
                'android_api_key',
                'ios_api_id',
                'ios_api_key',
            ];

            // Filter out non-allowed config
            foreach ($nonAllowedConfig as $key) {
                unset($config[$key]);
            }

            // Add additional metadata
            $config['windows_app_version'] = '1.0';
            $config['update_available'] = false;
            
            // Check for app updates
            if ($request->has('windows_app_version') || $request->has('app_version')) {
                $clientVersion = $request->input('windows_app_version') ?? $request->input('app_version');
                if (version_compare($config['windows_app_version'], $clientVersion, '>')) {
                    $config['update_available'] = true;
                }
            }

            // Add logo URL
            $logoExtension = $config['logo_extension'] ?? 'png';
            $config['logo_url'] = asset('images/logo.' . $logoExtension);
            
            // Add page categories (if available)
            try {
                $pageCategories = DB::table('Wo_PageCategory')->get()->toArray();
                $config['page_categories'] = $pageCategories;
            } catch (\Exception $e) {
                $config['page_categories'] = [];
            }

            // Add group categories (if available)
            try {
                $groupCategories = DB::table('Wo_GroupCategory')->get()->toArray();
                $config['group_categories'] = $groupCategories;
            } catch (\Exception $e) {
                $config['group_categories'] = [];
            }

            // Add blog categories (if available)
            try {
                $blogCategories = DB::table('Wo_BlogCategory')->get()->toArray();
                $config['blog_categories'] = $blogCategories;
            } catch (\Exception $e) {
                $config['blog_categories'] = [];
            }

            // Add product categories (if available)
            try {
                $productCategories = DB::table('Wo_ProductsCategory')->get()->toArray();
                $config['product_categories'] = $productCategories;
            } catch (\Exception $e) {
                $config['product_categories'] = [];
            }

            // Add job categories (if available)
            try {
                $jobCategories = DB::table('Wo_JobCategory')->get()->toArray();
                $config['job_categories'] = $jobCategories;
            } catch (\Exception $e) {
                $config['job_categories'] = [];
            }

            // Add user messages (encoded for client compatibility)
            $config['user_messages'] = base64_encode('Error while connecting to our servers.');

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'config' => $config
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to get settings: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Update user settings (mimics WoWonder update_user_data.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateUserData(Request $request): JsonResponse
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

        // Validate type parameter
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:general_settings,password_settings,privacy_settings,online_status,profile_settings,custom_settings',
            'user_data' => 'required|string', // JSON encoded string
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
            $user = User::where('user_id', $tokenUserId)->first();
            if (!$user) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '6',
                        'error_text' => 'User not found.'
                    ]
                ], 404);
            }

            $type = $request->input('type');
            $userData = json_decode($request->input('user_data'), true);
            
            if (!is_array($userData)) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '8',
                        'error_text' => 'Invalid user data format.'
                    ]
                ], 422);
            }

            $errors = [];
            $updated = false;

            switch ($type) {
                case 'general_settings':
                    $errors = $this->updateGeneralSettings($user, $userData);
                    break;
                    
                case 'password_settings':
                    $errors = $this->updatePasswordSettings($user, $userData, $token);
                    break;
                    
                case 'privacy_settings':
                    $errors = $this->updatePrivacySettings($user, $userData);
                    break;
                    
                case 'online_status':
                    $errors = $this->updateOnlineStatus($user, $userData);
                    break;
                    
                case 'profile_settings':
                    $errors = $this->updateProfileSettings($user, $userData);
                    break;
                    
                case 'custom_settings':
                    $errors = $this->updateCustomSettings($user, $userData);
                    break;
            }

            if (!empty($errors)) {
                return response()->json([
                    'api_status' => '500',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => $errors
                ], 422);
            }

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '9',
                    'error_text' => 'Failed to update user data: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Update general settings
     */
    private function updateGeneralSettings(User $user, array $userData): array
    {
        $errors = [];

        // Validate email
        if (isset($userData['email'])) {
            if ($userData['email'] != $user->email) {
                if (User::where('email', $userData['email'])->where('user_id', '!=', $user->user_id)->exists()) {
                    $errors[] = 'Email already exists';
                }
            }
            
            if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email is invalid';
            }
        }

        // Validate phone number
        if (isset($userData['phone_number']) && !empty($userData['phone_number'])) {
            if ($userData['phone_number'] != $user->phone_number) {
                if (User::where('phone_number', $userData['phone_number'])->where('user_id', '!=', $user->user_id)->exists()) {
                    $errors[] = 'Phone number already exists';
                }
            }
        }

        // Validate username
        if (isset($userData['username'])) {
            if ($userData['username'] != $user->username) {
                if (User::where('username', $userData['username'])->where('user_id', '!=', $user->user_id)->exists()) {
                    $errors[] = 'Username already exists';
                }
            }

            if (strlen($userData['username']) < 5 || strlen($userData['username']) > 32) {
                $errors[] = 'Username must be between 5 and 32 characters';
            }

            if (!preg_match('/^[\w]+$/', $userData['username'])) {
                $errors[] = 'Username contains invalid characters';
            }
        }

        if (empty($errors)) {
            $updateData = [];
            
            if (isset($userData['username'])) $updateData['username'] = $userData['username'];
            if (isset($userData['email'])) $updateData['email'] = $userData['email'];
            if (isset($userData['phone_number'])) $updateData['phone_number'] = $userData['phone_number'];
            if (isset($userData['gender'])) $updateData['gender'] = $userData['gender'] == 'female' ? 'female' : 'male';

            if (!empty($updateData)) {
                DB::table('Wo_Users')->where('user_id', $user->user_id)->update($updateData);
            }
        }

        return $errors;
    }

    /**
     * Update password settings
     */
    private function updatePasswordSettings(User $user, array $userData, string $sessionId): array
    {
        $errors = [];

        if (!isset($userData['current_password']) || !isset($userData['new_password']) || !isset($userData['repeat_new_password'])) {
            $errors[] = 'All password fields are required';
            return $errors;
        }

        // Verify current password
        if (!password_verify($userData['current_password'], $user->password)) {
            $errors[] = 'Current password is incorrect';
        }

        // Validate new password
        if ($userData['new_password'] != $userData['repeat_new_password']) {
            $errors[] = 'New passwords do not match';
        }

        if (strlen($userData['new_password']) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }

        if (empty($errors)) {
            $newPassword = password_hash($userData['new_password'], PASSWORD_DEFAULT);
            DB::table('Wo_Users')->where('user_id', $user->user_id)->update(['password' => $newPassword]);
            
            // Logout from all other sessions
            DB::table('Wo_AppsSessions')
                ->where('user_id', $user->user_id)
                ->where('session_id', '!=', $sessionId)
                ->delete();
        }

        return $errors;
    }

    /**
     * Update privacy settings
     */
    private function updatePrivacySettings(User $user, array $userData): array
    {
        $errors = [];

        $updateData = [];
        
        if (isset($userData['message_privacy']) && in_array($userData['message_privacy'], ['0', '1'])) {
            $updateData['message_privacy'] = $userData['message_privacy'];
        }
        
        if (isset($userData['follow_privacy']) && in_array($userData['follow_privacy'], ['0', '1'])) {
            $updateData['follow_privacy'] = $userData['follow_privacy'];
        }
        
        if (isset($userData['birth_privacy']) && in_array($userData['birth_privacy'], ['0', '1', '2'])) {
            $updateData['birth_privacy'] = $userData['birth_privacy'];
        }
        
        if (isset($userData['status']) && in_array($userData['status'], ['0', '1'])) {
            $updateData['status'] = $userData['status'];
        }

        if (!empty($updateData)) {
            DB::table('Wo_Users')->where('user_id', $user->user_id)->update($updateData);
        }

        return $errors;
    }

    /**
     * Update online status
     */
    private function updateOnlineStatus(User $user, array $userData): array
    {
        $errors = [];

        if (isset($userData['status']) && in_array($userData['status'], ['0', '1'])) {
            DB::table('Wo_Users')->where('user_id', $user->user_id)->update(['status' => $userData['status']]);
        }

        return $errors;
    }

    /**
     * Update profile settings
     */
    private function updateProfileSettings(User $user, array $userData): array
    {
        $errors = [];

        $updateData = [];
        
        if (isset($userData['first_name'])) $updateData['first_name'] = $userData['first_name'];
        if (isset($userData['last_name'])) $updateData['last_name'] = $userData['last_name'];
        if (isset($userData['about'])) $updateData['about'] = $userData['about'];
        if (isset($userData['facebook'])) $updateData['facebook'] = $userData['facebook'];
        if (isset($userData['google'])) $updateData['google'] = $userData['google'];
        if (isset($userData['linkedin'])) $updateData['linkedin'] = $userData['linkedin'];
        if (isset($userData['vk'])) $updateData['vk'] = $userData['vk'];
        if (isset($userData['instagram'])) $updateData['instagram'] = $userData['instagram'];
        if (isset($userData['twitter'])) $updateData['twitter'] = $userData['twitter'];
        if (isset($userData['youtube'])) $updateData['youtube'] = $userData['youtube'];

        if (!empty($updateData)) {
            DB::table('Wo_Users')->where('user_id', $user->user_id)->update($updateData);
        }

        return $errors;
    }

    /**
     * Update custom settings
     */
    private function updateCustomSettings(User $user, array $userData): array
    {
        $errors = [];

        // Only allow updating safe fields
        $allowedFields = [
            'first_name', 'last_name', 'about', 'website', 'working', 'working_link',
            'address', 'school', 'country_id', 'city', 'zip', 'language'
        ];

        $updateData = [];
        foreach ($userData as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updateData[$key] = $value;
            }
        }

        if (!empty($updateData)) {
            DB::table('Wo_Users')->where('user_id', $user->user_id)->update($updateData);
        }

        return $errors;
    }
}

