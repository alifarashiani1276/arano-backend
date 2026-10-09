<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | آدرس‌های مجاز از متغیر CORS_ALLOWED_ORIGINS خوانده می‌شوند
    | (چند آدرس با کاما، بدون فاصله و بدون اسلش آخر).
    | اگر متغیر تنظیم نشده باشد، مقدار پیش‌فرض زیر استفاده می‌شود.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'CORS_ALLOWED_ORIGINS',
            'https://aranogroup.ir,https://www.aranogroup.ir,http://localhost:5173'
        ))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // احراز هویت با Bearer Token است، پس کوکی لازم نیست.
    'supports_credentials' => false,

];