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
                'background' => '#1E293B',
                'surface' => '#FFFFFF',
                'emphasis' => '#F5F6FB',
                'on-emphasis' => '#1E1B4B',
                'primary' => '#1D4ED8',
                'primary-darken-1' => '#1E40AF',
                'secondary' => '#7C3AED',
                'secondary-darken-1' => '#6D28D9',
                'error' => '#DC2626',
                'info' => '#0369A1',
                'success' => '#047857',
                'warning' => '#B45309',
                'map-accent' => '#FFD700',
                'nav-accent' => '#60A5FA',
                'text-primary' => '#0F172A',
                'text-secondary' => '#64748B',
                'border-light' => '#E0E3F0',
                'on-primary' => '#FFFFFF',
                'on-secondary' => '#FFFFFF',
                'on-error' => '#FFFFFF',
                'on-info' => '#FFFFFF',
                'on-success' => '#FFFFFF',
                'on-warning' => '#FFFFFF',
            ],
            'variables' => [
                'selected-opacity' => '0.25',
            ],
        ],
        'dark' => [
            'colors' => [
                'background' => '#0F172A',
                'surface' => '#1F2336',
                'emphasis' => '#2B3A59',
                'on-emphasis' => '#EEF0FF',
                'primary' => '#60A5FA',
                'primary-darken-1' => '#3B82F6',
                'secondary' => '#A78BFA',
                'secondary-darken-1' => '#8B5CF6',
                'error' => '#F87171',
                'info' => '#38BDF8',
                'success' => '#34D399',
                'warning' => '#FBBF24',
                'map-accent' => '#FDE047',
                'nav-accent' => '#60A5FA',
                'text-primary' => '#F1F5F9',
                'text-secondary' => '#94A3B8',
                'border-light' => '#323754',
                'on-primary' => '#0F172A',
                'on-secondary' => '#0F172A',
                'on-error' => '#0F172A',
                'on-info' => '#0F172A',
                'on-success' => '#0F172A',
                'on-warning' => '#0F172A',
            ],
            'variables' => [
                'selected-opacity' => '0.25',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Login page
    |--------------------------------------------------------------------------
    |
    | URL of the login page of the application if users don't sign in with
    | their e-mail and password, e.g. when using single sign-on. If the
    | session expires while editing, the admin panel opens this page in a new
    | tab instead of asking for the password and continues afterwards.
    | The "_url_" placeholder is replaced by the URL of the admin panel,
    | e.g. "https://example.com/login?redirect=_url_", so the login page can
    | return there after the user signed in.
    |
    */
    'login' => env( 'CMS_ADMIN_LOGIN' ),

    /*
    |--------------------------------------------------------------------------
    | Proxy settings
    |--------------------------------------------------------------------------
    |
    | The proxy settings define the maximum size of the file that can be
    | downloaded via the proxy in MB and the timeout for streaming the file
    | in seconds. The default values are 10 MB and 30 seconds, respectively.
    |
    | ttl is the lifetime in seconds of the signed, user-bound capability
    | token that authenticates proxy requests. It travels in the proxy URL, so
    | it is kept short; the admin client refreshes it before it expires.
    |
    */
    'proxy' => [
        'maxsize' => env( 'CMS_PROXY_MAXSIZE', 10 ), // in MB
        'timeout' => env( 'CMS_PROXY_TIMEOUT', 30 ), // in seconds
        'ttl' => env( 'CMS_PROXY_TTL', 3600 ), // in seconds
        'middleware' => ['web', 'auth', 'throttle:cms-proxy'],
    ],
];
