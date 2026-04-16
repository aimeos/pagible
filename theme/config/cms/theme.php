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
    | Use package catch-all page route
    |--------------------------------------------------------------------------
    |
    | If enabled, the package will register a catch-all route that will
    | match all requests and forward them to the CMS. Disable this option
    | if you need to register own routes before the catch-all route.
    |
    */
    'pageroute' => env( 'CMS_PAGEROUTE', true ),

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
