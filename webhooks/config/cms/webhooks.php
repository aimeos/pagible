<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


return [
    'enabled' => env( 'CMS_WEBHOOKS_ENABLED', false ),

    'queue' => [
        'connection' => env( 'CMS_WEBHOOKS_QUEUE_CONNECTION' ),
        'name' => env( 'CMS_WEBHOOKS_QUEUE', 'cms-webhooks' ),
        'timeout' => 25,
        'tries' => 4,
        'backoff' => [30, 120, 600],
        'max_age' => 86400,
    ],

    'http' => [
        'connect_timeout' => 3,
        'timeout' => 15,
        'max_body' => 16 * 1024,
        'max_headers' => 32 * 1024,
    ],

    'limits' => [
        'total' => 100,
        'active' => 25,
    ],

    'payload' => [
        'editor' => false,
    ],

    // Exact canonical hosts only. A host rule may explicitly relax schemes,
    // ports, internal CIDRs or the CA bundle used for that host.
    'hosts' => [],

    // Operator denials always win over host-specific internal allowances.
    'deny_cidrs' => [],
];
