<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'bird' => [
        'key' => env('BIRD_API_KEY'),
        // Optional: derived from the key's region (bk_us1_... -> us1.platform.bird.com).
        'base_url' => env('BIRD_API_URL'),
        // Optional: override MAIL_FROM_* for Bird, e.g. onboarding@messagebird.dev
        // until your own sending domain is verified in Bird.
        'from_address' => env('BIRD_FROM_ADDRESS'),
        'from_name' => env('BIRD_FROM_NAME'),
        'timeout' => (int) env('BIRD_TIMEOUT', 15),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
