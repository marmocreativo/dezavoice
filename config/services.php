<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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
    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    'retell' => [
        'api_key' => env('RETELL_API_KEY'),
        'base_url' => env('RETELL_BASE_URL', 'https://api.retellai.com'),
        'web_test_agent_id' => env('RETELL_WEB_TEST_AGENT_ID'),
        'web_test_agent_version' => env('RETELL_WEB_TEST_AGENT_VERSION'),
        'web_test_max_minutes' => (int) env('RETELL_WEB_TEST_MAX_MINUTES', 5),
        'phone_agent_id' => env('RETELL_PHONE_AGENT_ID'),
        'phone_agent_version' => env('RETELL_PHONE_AGENT_VERSION'),
        'phone_max_minutes' => (int) env('RETELL_PHONE_MAX_MINUTES', 10),
    ],
    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:soporte@dezavoice.com'),
    ],

];
