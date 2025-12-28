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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
	
	'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
	],

    // --- ВОТ ИСПРАВЛЕНИЕ: ДОБАВЬ ЭТОТ БЛОК ---
    'google_play' => [
        // Эта строка "пропустит" твою переменную из .env в кеш конфига
        'key_file' => env('GOOGLE_PLAY_KEY_FILE'), // (убедись, что в .env есть 'keys/service-account.json')
        'package_name' => env('GOOGLE_PLAY_PACKAGE', 'com.booka_app'),
    ],
    // --- КОНЕЦ БЛОКА ---

];