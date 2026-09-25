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

    'korapay' => [
        // Secret key: server-side only, used for resolve-account, subaccount creation, and charge
        // initialization. Public key: safe for the frontend, unused here since checkout redirects
        // to Korapay's hosted page rather than embedding their inline widget.
        'secret_key' => env('KORAPAY_SECRET_KEY'),
        'public_key' => env('KORAPAY_PUBLIC_KEY'),
    ],

    'escrow' => [
        // Matches Jumia's own marketplace return window (7 days after delivery) — see the Return
        // Policy page. VendorOrder::return_window_expires_at is set to delivered_at + this many
        // days, and the daily escrow:release-eligible command only auto-releases once it has passed.
        'return_window_days' => (int) env('ESCROW_RETURN_WINDOW_DAYS', 7),
    ],

    'smart_sms' => [
        // SmartSMSSolutions (smartsmssolutions.com) — used by App\Services\Sms\SmsService for
        // vendor phone-number OTP verification (KYC). IMPORTANT: this integration is built against
        // their long-standing documented GET endpoint (username/password/sender/recipient/message
        // query params against api.smartsmssolutions.com/smsapi.php), which is the only concrete,
        // verifiable API contract available at implementation time — their current docs site
        // (docs.smartsmssolutions.com) blocks automated access, so if your account dashboard shows
        // a newer token-based API instead, update SmsService::send() accordingly before relying on
        // this in production. Leaving username/password blank keeps the app in log-only mode (see
        // SmsService's own doc comment).
        'username' => env('SMARTSMS_USERNAME'),
        'password' => env('SMARTSMS_PASSWORD'),
        'sender_id' => env('SMARTSMS_SENDER_ID', 'ChinaInNG'),

        // Without a live gateway account, SmsService::send() silently no-ops (see its own doc
        // comment) rather than throwing — meaning in production a buyer/vendor who reaches a phone
        // OTP step never actually receives a code and is stuck there permanently, with no way
        // forward. Setting this to false skips the OTP entirely: a phone number is accepted and
        // marked verified immediately, no code sent or asked for — see BuyerSignupService and
        // VendorIdentityService. Flip back to true once a real SMS gateway is configured.
        'phone_otp_required' => (bool) env('PHONE_OTP_REQUIRED', true),
    ],

    'supabase' => [
        // Project URL, e.g. https://xxxxxxxxxxxx.supabase.co — from Project Settings -> API.
        'url' => env('SUPABASE_URL'),
        // The SERVICE ROLE key (not the anon key) — it bypasses storage policies, so every call
        // using it happens server-side only, never sent to the frontend.
        'service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
        // Two buckets: one public (product images, banners, store branding), one private (vendor
        // ID/CAC documents, only ever served via short-lived signed URLs). Create both in the
        // Supabase dashboard under Storage before running the app.
        'public_bucket' => env('SUPABASE_STORAGE_PUBLIC_BUCKET', 'public'),
        'private_bucket' => env('SUPABASE_STORAGE_PRIVATE_BUCKET', 'private'),
    ],

];
