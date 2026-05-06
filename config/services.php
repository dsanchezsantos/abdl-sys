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

    'nowigo' => [
        'base_url'    => env('NOWIGO_API_URL', 'https://feiradolivro-saquarema2025.nowigo.com.br/app/sale.mdl'),
        'limit_pages' => env('NOWIGO_SYNC_LIMIT'), // Para testes
    ],

    'gotenberg' => [
        'url' => env('GOTENBERG_ENDPOINT', 'http://gotenberg:3000'),
    ],

    'quickchart' => [
        'url' => env('QUICKCHART_URL', 'http://quickchart:3400'),
    ],

];
