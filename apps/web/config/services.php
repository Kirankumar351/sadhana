<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | Credentials for third party services. Nothing here has a default that would
    | work in production — a missing key must be an obvious failure, never a silent
    | fallback to somebody else's account.
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

    /*
    |--------------------------------------------------------------------------
    | Push delivery
    |--------------------------------------------------------------------------
    |
    | Web push via FCM is free and reaches anyone who granted permission, so it is the
    | default channel for everything.
    |
    | WhatsApp has by far the best open rate in this market — it is why Vol 1 treats it
    | as 25% of acquisition rather than an afterthought — but it costs money per message
    | and careless use gets the business account restricted, which would take the channel
    | away from every user at once. It is therefore reserved for messages where silence
    | genuinely costs someone something: a closing deadline, or a job they qualify for.
    |
    */

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
    ],

    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF text
    |--------------------------------------------------------------------------
    |
    | Poppler's pdftotext reads only the pages it is asked for, so a 70-page notification
    | costs milliseconds instead of loading the whole document into PHP. It is not a
    | credential, so a default is safe: on Linux it is found by name once poppler-utils is
    | installed. On Windows, give the full path to pdftotext.exe. Without it, ingestion
    | falls back to the slower PHP parser and still works.
    |
    */

    'pdftotext' => [
        'path' => env('PDFTOTEXT_PATH', 'pdftotext'),
    ],

];
