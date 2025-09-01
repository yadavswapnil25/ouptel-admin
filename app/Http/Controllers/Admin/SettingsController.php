<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    public function index()
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }

        return view('admin.settings.index');
    }

    public function websiteMode()
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }

        return view('admin.settings.website-mode');
    }

    public function generalConfiguration()
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }

        return view('admin.settings.general');
    }

    public function websiteInformation()
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }

        return view('admin.settings.website-info');
    }

    public function fileUploadConfiguration()
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }

        return view('admin.settings.file-upload');
    }

    public function emailSmsSetup()
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }

        return view('admin.settings.email-sms');
    }

    public function chatVideoAudio()
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }

        return view('admin.settings.chat-video-audio');
    }

    public function socialLoginSettings()
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }

        return view('admin.settings.social-login');
    }

    public function nodejsSettings()
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }

        return view('admin.settings.nodejs');
    }

    public function postsSettings()
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }

        return view('admin.settings.posts');
    }

    public function update(Request $request, $group)
    {
        $userId = session('admin_user_id');
        if (!$userId) {
            return redirect('/admin/login');
        }

        $validator = Validator::make($request->all(), $this->getValidationRules($group));
        
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        foreach ($request->except(['_token', '_method']) as $key => $value) {
            Setting::set($key, $value, 'string', $group);
        }

        return back()->with('success', 'Settings updated successfully!');
    }

    private function getValidationRules($group)
    {
        $rules = [];

        switch ($group) {
            case 'website_mode':
                $rules = [
                    'maintenance_mode' => 'boolean',
                    'registration_enabled' => 'boolean',
                    'login_enabled' => 'boolean',
                ];
                break;

            case 'general':
                $rules = [
                    'site_name' => 'required|string|max:255',
                    'site_description' => 'nullable|string|max:500',
                    'timezone' => 'required|string',
                    'language' => 'required|string',
                    'currency' => 'required|string',
                ];
                break;

            case 'website_info':
                $rules = [
                    'site_title' => 'required|string|max:255',
                    'site_keywords' => 'nullable|string|max:500',
                    'site_author' => 'nullable|string|max:255',
                    'contact_email' => 'nullable|email',
                    'contact_phone' => 'nullable|string|max:20',
                ];
                break;

            case 'file_upload':
                $rules = [
                    'max_file_size' => 'required|integer|min:1',
                    'allowed_extensions' => 'required|string',
                    'upload_path' => 'required|string',
                    'image_quality' => 'required|integer|min:1|max:100',
                ];
                break;

            case 'email_sms':
                $rules = [
                    'smtp_host' => 'nullable|string',
                    'smtp_port' => 'nullable|integer',
                    'smtp_username' => 'nullable|string',
                    'smtp_password' => 'nullable|string',
                    'sms_provider' => 'nullable|string',
                    'sms_api_key' => 'nullable|string',
                ];
                break;

            case 'chat_video_audio':
                $rules = [
                    'chat_enabled' => 'boolean',
                    'video_chat_enabled' => 'boolean',
                    'audio_chat_enabled' => 'boolean',
                    'agora_app_id' => 'nullable|string',
                    'agora_app_certificate' => 'nullable|string',
                ];
                break;

            case 'social_login':
                $rules = [
                    'google_client_id' => 'nullable|string',
                    'google_client_secret' => 'nullable|string',
                    'facebook_app_id' => 'nullable|string',
                    'facebook_app_secret' => 'nullable|string',
                    'twitter_client_id' => 'nullable|string',
                    'twitter_client_secret' => 'nullable|string',
                ];
                break;

            case 'nodejs':
                $rules = [
                    'nodejs_enabled' => 'boolean',
                    'socket_port' => 'nullable|integer',
                    'socket_host' => 'nullable|string',
                    'redis_enabled' => 'boolean',
                    'redis_host' => 'nullable|string',
                    'redis_port' => 'nullable|integer',
                ];
                break;

            case 'posts':
                $rules = [
                    'posts_enabled' => 'boolean',
                    'max_post_length' => 'required|integer|min:1',
                    'allow_images' => 'boolean',
                    'allow_videos' => 'boolean',
                    'auto_approve_posts' => 'boolean',
                ];
                break;
        }

        return $rules;
    }
}






