<?php

return [
    'default' => env('ANALYTICS_DRIVER', 'none'),

    'drivers' => [
        'cloudflare' => [
            'siteTag' => env('CLOUDFLARE_SITETAG'),
            'token' => env('CLOUDFLARE_TOKEN'),
        ],
        'ga4' => [
            'propertyid' => env('GOOGLE_PROPERTYID'),
            'credentials' => json_decode(base64_decode(env('GOOGLE_AUTH', '')), true),
        ],
        'matomo' => [
            'url' => env('MATOMO_URL'),
            'token' => env('MATOMO_TOKEN'),
            'siteid' => env('MATOMO_SITEID'),
        ],
    ],

    'google' => [
        'auth' => json_decode(base64_decode(env('GOOGLE_AUTH', '')), true),
        'crux' => [
            'apikey' => env('CRUX_API_KEY'),
        ]
    ]
];
