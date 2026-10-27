<?php

return [

    /*
    | Days a newly registered gym can use the software before it must pick a paid plan.
    */
    'trial_days' => (int) env('GYM_TRIAL_DAYS', 14),

    'default_saas_plan' => env('GYM_DEFAULT_SAAS_PLAN', 'basic'),

    /*
    | Set to e.g. "yourapp.com" to serve each gym on its own subdomain
    | (fitzone.yourapp.com). Leave empty to use paths (/app/fitzone).
    */
    'tenant_domain' => env('GYM_TENANT_DOMAIN'),

    // Gym slugs that would clash with system subdomains or paths.
    'reserved_slugs' => ['www', 'app', 'admin', 'member', 'api', 'mail', 'join', 'webhooks'],

    // Per-gym settings defaults; a gym can override these on its settings page.
    'defaults' => [
        'reminder_days_before' => [7, 3, 1],
        'dues_reminder_every_days' => 7,
        'channels' => ['whatsapp', 'email'],
        'send_birthday_wishes' => true,
        'send_anniversary_wishes' => true,
        'send_payment_receipts' => true,
        'allow_online_signup' => true,
    ],

    'whatsapp' => [
        // Meta WhatsApp Cloud API. Leave the token empty to only log messages.
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'default_country_code' => env('DEFAULT_COUNTRY_CODE', '91'),
    ],

    'sms' => [
        // MSG91 flow API. Leave the auth key empty to only log messages.
        'msg91_auth_key' => env('MSG91_AUTH_KEY'),
        'msg91_sender' => env('MSG91_SENDER_ID'),
        'msg91_flow_id' => env('MSG91_FLOW_ID'),
    ],

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

];
