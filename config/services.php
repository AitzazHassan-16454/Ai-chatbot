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

    'gemini' => [
        'api_key'           => env('GEMINI_API_KEY'),
        'rpm_limit'         => env('GEMINI_RPM_LIMIT', 10),
        'requests_day_limit' => env('GEMINI_REQUESTS_DAY_LIMIT', 250),
    ],

    'n8n' => [
        'webhook_url' => env('N8N_WEBHOOK_URL', 'http://localhost:5678/webhook'),
        'webhook_secret' => env('N8N_WEBHOOK_SECRET'),
        'feedback_url' => env('N8N_FEEDBACK_WEBHOOK_URL', 'http://localhost:5678/webhook/feedback-webhook'),
    ],

    'serpapi' => [
        'api_key' => env('SERPAPI_KEY', ''),
    ],

    'job_alerts' => [
        'api_key' => env('JOB_ALERTS_API_KEY', ''),
    ],

];
