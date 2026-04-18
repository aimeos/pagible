<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache store
    |--------------------------------------------------------------------------
    |
    | Use the cache store defined in ./config/cache.php to store rendered pages
    | for fast response times.
    |
    */
    'cache' => env( 'APP_DEBUG' ) ? 'array' : 'file',

    /*
    |--------------------------------------------------------------------------
    | Theme TTL
    |--------------------------------------------------------------------------
    |
    | Time-to-live (TTL) for cached theme data in seconds. Set to 0 to disable
    | caching.
    |
    */
    'ttl' => env( 'CMS_THEME_TTL', env( 'APP_DEBUG' ) ? 0 : 86400 ),

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy (CSP)
    |--------------------------------------------------------------------------
    |
    | Define additional Content Security Policy (CSP) directives.
    | The default settings already allow loading from the same origin.
    */
    'csp' => [
        'media-src' => env( 'CMS_CSP_MEDIA_SRC' ),
        'style-src' => env( 'CMS_CSP_STYLE_SRC', 'https://hcaptcha.com https://*.hcaptcha.com' ),
        'frame-src' => env( 'CMS_CSP_FRAME_SRC', 'https://hcaptcha.com https://*.hcaptcha.com' ),
        'script-src' => env( 'CMS_CSP_SCRIPT_SRC', 'https://hcaptcha.com https://*.hcaptcha.com' ),
        'connect-src' => env( 'CMS_CSP_CONNECT_SRC', 'https://hcaptcha.com https://*.hcaptcha.com' ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme storage disk
    |--------------------------------------------------------------------------
    |
    | Filesystem disk for tenant-uploaded themes. Disabled unless configured.
    | Themes on this disk are discovered per-tenant and their views synced
    | to local storage for Blade compilation.
    |
    */
    'disk' => env( 'CMS_THEME_DISK' ),

    /*
    |--------------------------------------------------------------------------
    | Page catch-all route configuration
    |--------------------------------------------------------------------------
    |
    | Configuration array for the catch-all page route. Supports all Laravel
    | route group options such as 'prefix', 'middleware', 'domain', 'where',
    | 'as', etc. Set to null to disable the page route entirely.
    |
    */
    'pageroute' => json_decode( env( 'CMS_PAGEROUTE', '{}' ), true ),

    /*
    |--------------------------------------------------------------------------
    | Sitemap URL path
    |--------------------------------------------------------------------------
    |
    | The URL path prefix for the XML sitemap. The sitemap index will be
    | available at /{sitemap}.xml and chunks at /{sitemap}-{page}.xml.
    |
    */
    'sitemap' => env( 'CMS_SITEMAP', 'sitemap' ),
];
