<?php

return [

    /*
    | Days a newly registered gym can use the software before it must pick a paid plan.
    */
    'trial_days' => (int) env('GYM_TRIAL_DAYS', 14),

    'default_saas_plan' => env('GYM_DEFAULT_SAAS_PLAN', 'basic'),

    /*
    | Platform owner, created as super admin by "php artisan db:seed".
    | Leave the password empty to have a random one generated and printed.
    */
    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL', 'admin@syberinfo.com.au'),
        'name' => env('SUPER_ADMIN_NAME', 'Syber Info Admin'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

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
        'height_unit' => 'cm', // cm, or ft for feet and inches
        'review_request_enabled' => false,
        'review_request_after_visits' => 10,
        'google_review_url' => null,
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

    /*
    | Gyms can serve their website, signup page and member app on their own
    | domain. They point a CNAME at cname_target. With Cloudflare for SaaS
    | configured, SSL certificates are issued automatically; otherwise add
    | each domain to the CloudPanel site and issue a certificate there.
    */
    'custom_domains' => [
        'cname_target' => env('CUSTOM_DOMAIN_TARGET'),
        'cloudflare_zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'cloudflare_api_token' => env('CLOUDFLARE_API_TOKEN'),
    ],

    /*
    | One Meta (Facebook) app for the whole platform receives lead-ad webhooks
    | for every gym's page. Each gym connects its own page in Gym settings.
    */
    'meta' => [
        'app_secret' => env('META_APP_SECRET'),
        'verify_token' => env('META_VERIFY_TOKEN'),
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
    ],

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

];
