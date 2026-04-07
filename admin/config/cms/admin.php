<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin theme colors
    |--------------------------------------------------------------------------
    |
    | Vuetify color scheme for the admin panel. Passed as JSON to the Vue SPA
    | via the data-theme attribute. Each theme key (light/dark) must contain
    | a 'colors' array with Vuetify color tokens.
    |
    */
    'colors' => [
        'light' => [
            'colors' => [
                'background' => '#F8FAFC',
                'surface' => '#FFFFFF',
                'primary' => '#002F6C',
                'primary-darken-1' => '#001F4D',
                'secondary' => '#10B981',
                'secondary-darken-1' => '#059669',
                'error' => '#EF4444',
                'info' => '#3B82F6',
                'success' => '#10B981',
                'warning' => '#F59E0B',
                'map-accent' => '#FFD700',
                'text-primary' => '#0F172A',
                'text-secondary' => '#64748B',
                'border-light' => '#E2E8F0',
            ],
        ],
        'dark' => [
            'colors' => [
                'background' => '#0F172A',
                'surface' => '#1E293B',
                'primary' => '#3B82F6',
                'primary-darken-1' => '#2563EB',
                'secondary' => '#34D399',
                'secondary-darken-1' => '#10B981',
                'error' => '#F87171',
                'info' => '#60A5FA',
                'success' => '#34D399',
                'warning' => '#FBBF24',
                'map-accent' => '#FDE047',
                'text-primary' => '#F1F5F9',
                'text-secondary' => '#94A3B8',
                'border-light' => '#334155',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Proxy settings
    |--------------------------------------------------------------------------
    |
    | The proxy settings define the maximum length of the file that can be
    | downloaded via the proxy in MB and the timeout for streaming the file
    | in seconds. The default values are 10 MB and 30 seconds, respectively.
    |
    */
    'proxy' => [
        'max-length' => env( 'CMS_PROXY_MAX_LENGTH', 10 ), // in MB
        'stream_timeout' => env( 'CMS_PROXY_STREAM_TIMEOUT', 30 ), // in seconds
        'middleware' => ['web', 'auth', 'throttle:cms-proxy'],
    ],
];
