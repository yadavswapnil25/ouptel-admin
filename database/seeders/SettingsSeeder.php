<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Basic Website Settings (matching WoWonder structure)
            ['name' => 'siteName', 'value' => 'Ouptel'],
            ['name' => 'siteTitle', 'value' => 'Ouptel - Connect with Friends'],
            ['name' => 'siteKeywords', 'value' => 'social, network, community, ouptel'],
            ['name' => 'siteDesc', 'value' => 'Ouptel is a modern social networking platform. Connect with friends, share moments, and build communities.'],
            ['name' => 'siteEmail', 'value' => 'contact@ouptel.com'],
            ['name' => 'defualtLang', 'value' => 'english'],
            
            // User Registration & Login
            ['name' => 'emailValidation', 'value' => '0'],
            ['name' => 'emailNotification', 'value' => '0'],
            ['name' => 'user_registration', 'value' => '1'],
            ['name' => 'user_lastseen', 'value' => '1'],
            ['name' => 'deleteAccount', 'value' => '1'],
            ['name' => 'connectivitySystem', 'value' => '0'],
            ['name' => 'profileVisit', 'value' => '1'],
            
            // System Settings
            ['name' => 'fileSharing', 'value' => '1'],
            ['name' => 'seoLink', 'value' => '1'],
            ['name' => 'cacheSystem', 'value' => '0'],
            ['name' => 'chatSystem', 'value' => '1'],
            ['name' => 'useSeoFrindly', 'value' => '1'],
            ['name' => 'developer_mode', 'value' => '0'],
            ['name' => 'maintenance_mode', 'value' => '0'],
            
            // Security & reCAPTCHA
            ['name' => 'reCaptcha', 'value' => '0'],
            ['name' => 'reCaptchaKey', 'value' => ''],
            ['name' => 'recaptcha_secret_key', 'value' => ''],
            ['name' => 'two_factor', 'value' => '0'],
            ['name' => 'login_auth', 'value' => '0'],
            ['name' => 'prevent_system', 'value' => '0'],
            ['name' => 'bad_login_limit', 'value' => '5'],
            ['name' => 'lock_time', 'value' => '15'],
            
            // File Upload Settings
            ['name' => 'maxUpload', 'value' => '96000000'],
            ['name' => 'maxCharacters', 'value' => '640'],
            ['name' => 'allowedExtenstion', 'value' => 'jpg,png,jpeg,gif,mkv,docx,zip,rar,pdf,doc,mp3,mp4,flv,wav,txt,mov,avi,webm,wav,mpeg'],
            
            // Social Login
            ['name' => 'AllLogin', 'value' => '0'],
            ['name' => 'googleLogin', 'value' => '0'],
            ['name' => 'facebookLogin', 'value' => '0'],
            ['name' => 'twitterLogin', 'value' => '0'],
            ['name' => 'linkedinLogin', 'value' => '0'],
            ['name' => 'VkontakteLogin', 'value' => '0'],
            ['name' => 'instagramLogin', 'value' => '0'],
            
            // API Keys
            ['name' => 'google_map_api', 'value' => ''],
            ['name' => 'youtube_api_key', 'value' => ''],
            ['name' => 'giphy_api', 'value' => ''],
            ['name' => 'googleAnalytics', 'value' => ''],
            
            // Email & SMS
            ['name' => 'smtp_or_mail', 'value' => 'mail'],
            ['name' => 'smtp_host', 'value' => ''],
            ['name' => 'smtp_username', 'value' => ''],
            ['name' => 'smtp_password', 'value' => ''],
            ['name' => 'smtp_port', 'value' => '587'],
            ['name' => 'smtp_encryption', 'value' => 'tls'],
            ['name' => 'sms_provider', 'value' => 'twilio'],
            ['name' => 'sms_phone_number', 'value' => ''],
            ['name' => 'sms_username', 'value' => ''],
            ['name' => 'sms_password', 'value' => ''],
            
            // Website Mode
            ['name' => 'website_mode', 'value' => 'facebook'],
            
            // Other Settings
            ['name' => 'censored_words', 'value' => ''],
            ['name' => 'message_seen', 'value' => '1'],
            ['name' => 'message_typing', 'value' => '1'],
            ['name' => 'age', 'value' => '1'],
            ['name' => 'online_sidebar', 'value' => '1'],
            ['name' => 'profile_back', 'value' => '1'],
            ['name' => 'profile_background_request', 'value' => 'all'],
            ['name' => 'connectivitySystemLimit', 'value' => '5000'],
            ['name' => 'invite_links_system', 'value' => '1'],
            ['name' => 'user_links_limit', 'value' => '10'],
            ['name' => 'expire_user_links', 'value' => 'day'],
            ['name' => 'cache_sidebar', 'value' => '1'],
            ['name' => 'update_user_profile', 'value' => '30'],
            ['name' => 'exchangerate_key', 'value' => ''],
            ['name' => 'auto_username', 'value' => '0'],
            ['name' => 'password_complexity_system', 'value' => '0'],
            ['name' => 'remember_device', 'value' => '1'],
            ['name' => 'two_factor_type', 'value' => 'email'],
            ['name' => 'google_authenticator', 'value' => '0'],
            ['name' => 'authy_settings', 'value' => '0'],
            ['name' => 'authy_token', 'value' => ''],
            ['name' => 'sms_or_email', 'value' => 'mail'],
            ['name' => 'user_limit', 'value' => '10'],
            ['name' => 'reserved_usernames_system', 'value' => '0'],
            ['name' => 'reserved_usernames', 'value' => ''],
            ['name' => 'disable_start_up', 'value' => '0'],
            ['name' => 'notify_new_post', 'value' => '1'],
            ['name' => 'developers_page', 'value' => '0'],
            ['name' => 'profile_privacy', 'value' => '1'],
            ['name' => 'directory_landing_page', 'value' => 'welcome'],
            ['name' => 'date_style', 'value' => 'Y-m-d'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['name' => $setting['name']],
                $setting
            );
        }
    }
}
