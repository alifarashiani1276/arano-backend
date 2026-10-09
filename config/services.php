<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have a
    | conventional file for this type of information.
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

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    |
    | SMS driver:
    | - log       => مناسب محیط توسعه
    | - kavenegar => مناسب Production
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),

        'kavenegar' => [
            'api_key' => env('KAVENEGAR_API_KEY'),
            'otp_template' => env('KAVENEGAR_OTP_TEMPLATE'),
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    */

    'super_admin' => [
        'phone' => env('SUPER_ADMIN_PHONE'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],
];