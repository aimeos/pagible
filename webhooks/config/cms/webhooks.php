<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


return [
    'enabled' => env( 'CMS_WEBHOOKS_ENABLED', false ),

    'queue' => [
        'connection' => env( 'CMS_WEBHOOKS_QUEUE_CONNECTION' ),
        'name' => env( 'CMS_WEBHOOKS_QUEUE', 'cms-webhooks' ),
        // Seconds all deliveries to a destination are paused after consecutive temporary failures,
        // the last value repeats until "max_age" is reached. After each pause, one delivery probes
        // the destination and the others follow if it's available again.
        'backoff' => [30, 120, 600, 1800],
        'max_age' => 86400,
    ],

    // Seconds the previous secret still signs deliveries after rotating a subscription secret
    'rotation_grace' => 86400,

    // Queue workers abort deliveries after both timeouts plus 10 seconds for resolving the host name
    // and recording the result, the "retry_after" setting of the queue connection must be greater
    'http' => [
        'connect_timeout' => 3,
        'timeout' => 10,
    ],

    // Subscriptions per tenant, lowering them doesn't delete or deactivate existing subscriptions
    'limits' => [
        'total' => 100,
        'active' => 25,
    ],

    // Operator-defined webhooks for all tenants unless restricted by "tenants". They are not shown
    // in the admin panel and may target internal HTTP(S) services, including private and loopback
    // addresses. Requests are signed like Standard Webhooks (https://www.standardwebhooks.com),
    // so the secret is "whsec_" and a base64 encoded key, e.g. "whsec_$(openssl rand -base64 32)".
    // To rotate it, use a list of the new and the previous secret to sign each request with both.
    // Invalid endpoints are skipped and logged, find them with "php artisan cms:webhooks:check".
    'endpoints' => [
        // 'indexer' => [
        //     'url' => env( 'CMS_WEBHOOK_INDEXER_URL' ),
        //     'secret' => env( 'CMS_WEBHOOK_INDEXER_SECRET' ), // or [new, previous] for rotation
        //     'events' => ['page.published', 'page.deleted'],
        //     'tenants' => ['tenant-id'],             // optional, default: all tenants
        //     'ca' => '/etc/ssl/certs/internal-ca.pem', // optional CA bundle for private HTTPS
        // ],
    ],

    // IP addresses and CIDR ranges denied for all subscriptions and endpoints, e.g. ['10.1.0.0/16', '10.2.0.5'].
    // An invalid entry blocks all deliveries.
    'deny_cidrs' => [],
];
