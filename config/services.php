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

    // ─── Firebase Authentication ─────────────────────────────────
    'firebase' => [
        'project_id'  => env('GOOGLE_CLOUD_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    // ─── eSewa Payment Gateway ───────────────────────────────────
    'esewa' => [
        'merchant_code' => env('ESEWA_MERCHANT_CODE'),
        'secret_key'    => env('ESEWA_SECRET_KEY'),
        'base_url'      => env('ESEWA_BASE_URL', 'https://rc-epay.esewa.com.np'),
        'verify_url'    => env('ESEWA_VERIFY_URL', 'https://rc-epay.esewa.com.np/api/epay/transaction/status/'),
    ],

    // ─── OpenRouter (AI Chat + Whisper STT) ─────────────────────
    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'model'   => env('OPENROUTER_MODEL', 'google/gemini-2.0-flash-001'),
    ],

    // ─── Microsoft Azure Neural TTS ────────────────────────────
    'azure_speech' => [
        'key'    => env('AZURE_SPEECH_KEY'),
        'region' => env('AZURE_SPEECH_REGION', 'centralindia'),
    ],

];
