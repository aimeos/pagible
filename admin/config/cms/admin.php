<?php

return [

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
