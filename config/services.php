
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for such information.
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

    // API.Bible
    'api_bible' => [
        'key' => env('API_BIBLE_API_KEY'),
        'base_url' => env(
            'API_BIBLE_BASE_URL',
            'https://rest.api.bible/v1'
        ),
        'timeout' => (int) env('API_BIBLE_TIMEOUT', 10),

        // API.Bible terms: cached Scripture must be cleared every 14 days or
        // less. ApiBibleClient clamps this to 14 days regardless of the env.
        'cache_ttl' => (int) env('API_BIBLE_CACHE_TTL', 86400),

        // Copyrighted translations (e.g. NLT) are served through API.Bible's
        // licence, which on the Starter plan covers NON-COMMERCIAL use only.
        // Public-domain translations are always allowed; copyrighted ones
        // stay "licence required" until an operator confirms the plan/terms.
        'allow_copyrighted' => (bool) env('API_BIBLE_ALLOW_COPYRIGHTED', false),

        // Phase 27.10 — translation codes that are linked to API.Bible but not
        // yet cleared for this deployment (e.g. the account's plan / intended
        // use is unconfirmed). They show "Licence verification required" and
        // make no API calls. Set to an empty value to clear the list.
        'pending_verification' => array_values(array_filter(array_map(
            'trim',
            explode(',', strtoupper((string) env('API_BIBLE_PENDING_VERIFICATION', 'ASV')))
        ))),

        // Quota awareness. 5,000/month is the Starter plan allowance per
        // API.Bible's published pricing (checked 2026-10-16). These counters
        // are application-side estimates; the API.Bible dashboard is the
        // authoritative usage record.
        'monthly_limit' => (int) env('API_BIBLE_MONTHLY_LIMIT', 5000),
        'warning_threshold' => (float) env('API_BIBLE_WARNING_THRESHOLD', 0.8),
    ],

];
