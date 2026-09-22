<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


return [
    'enabled' => env( 'CMS_WEBHOOKS_ENABLED', false ),

    'queue' => [
        'connection' => env( 'CMS_WEBHOOKS_QUEUE_CONNECTION' ),
        'name' => env( 'CMS_WEBHOOKS_QUEUE', 'cms-webhooks' ),
    ],

    // Seconds to wait for the response. Queue workers abort deliveries after this timeout plus 13 seconds
    // for connecting, resolving the host name and recording the result, the "retry_after" setting of the
    // queue connection must be greater
    'timeout' => 10,

    // Subscriptions per tenant, lowering it doesn't delete existing subscriptions
    'limit' => 25,

    // Operator-defined webhooks which receive the events of all tenants, the payload contains the tenant ID.
    // They are not shown in the admin panel and may target internal HTTP(S) services, including private
    // and loopback addresses. Requests are signed like Standard Webhooks (https://www.standardwebhooks.com),
    // so the secret is "whsec_" and a base64 encoded key, e.g. "whsec_$(openssl rand -base64 32)".
    // To rotate it, use a list of the new and the previous secret to sign each request with both.
    // Invalid endpoints are skipped and logged, find them with "php artisan cms:webhooks:check".
    'endpoints' => [
        // 'indexer' => [
        //     'url' => env( 'CMS_WEBHOOK_INDEXER_URL' ),
        //     'secret' => env( 'CMS_WEBHOOK_INDEXER_SECRET' ), // or [new, previous] for rotation
        //     'events' => ['page.published', 'page.deleted'],
        // ],
    ],

    // IP addresses and CIDR ranges denied for all subscriptions and endpoints, e.g. ['10.1.0.0/16', '10.2.0.5'].
    // An invalid entry blocks all deliveries.
    'deny_cidrs' => [],
];
