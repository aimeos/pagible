# Pagible Webhooks

Signed, tenant-scoped webhook notifications for [Pagible CMS](https://pagible.com) lifecycle events,
delivered through the Laravel queue. The core package doesn't need an event bus, an outbox or any
webhook dependency.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-webhooks
php artisan cms:install:webhooks
php artisan migrate
```

Enable webhooks and run a dedicated worker on an asynchronous queue connection:

```dotenv
CMS_WEBHOOKS_ENABLED=true
CMS_WEBHOOKS_QUEUE_CONNECTION=redis
CMS_WEBHOOKS_QUEUE=cms-webhooks
```

```bash
php artisan queue:work redis --queue=cms-webhooks
```

The `sync` driver delivers immediately without a worker, but failed deliveries aren't retried. The
`null` driver queues nothing and logs `cms.webhook.delivery_blocked` with the reason `invalid_queue`.

## Configuration

Settings are in `config/cms/webhooks.php`:

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `false` | Turn webhooks on (`CMS_WEBHOOKS_ENABLED`) |
| `queue.connection` | app default | Queue connection (`CMS_WEBHOOKS_QUEUE_CONNECTION`) |
| `queue.name` | `cms-webhooks` | Queue name (`CMS_WEBHOOKS_QUEUE`) |
| `timeout` | `10` | Seconds to wait for the response |
| `limit` | `25` | Subscriptions per tenant |
| `endpoints` | `[]` | [Operator endpoints](#operator-endpoints) for internal services |
| `deny_cidrs` | `[]` | IP addresses and CIDR ranges denied for all destinations, e.g. `['10.1.0.0/16', '10.2.0.5']` |

Lowering the limit doesn't delete existing subscriptions, but none can be added until the tenant is
below the limit again. An invalid `deny_cidrs` entry blocks all deliveries until it's fixed.

## Operations

A broken webhook configuration never stops the application, it only stops the affected deliveries.
Run the check on every deploy. It lists each problem with the setting to fix and fails if there are any:

```bash
php artisan cms:webhooks:check
```

The command also reports:

* a queue `retry_after` (or broker visibility timeout) too low for the delivery timeout
* an `array` or `null` cache store, which can't pause destinations or throttle the log entries
  across servers
* subscriptions whose secrets were encrypted with a key that isn't available any more. This is only
  a warning because only the tenants can rotate their secrets.

### Workers and timeouts

Each destination is a separate job and a slow endpoint occupies a worker for up to the HTTP
timeout. Monitor queue depth, oldest job age and failed jobs, and add workers if scheduled
publications create bursts.

Workers abort a delivery after `timeout` + 13 seconds for connecting, resolving the host name and
recording the result (23 seconds by default). Set the queue's `retry_after` higher, otherwise
running deliveries are released twice.

Resolving the host name uses up the response time, and a single DNS query can't be interrupted.
Configure short resolver timeouts on the worker hosts, e.g. `options timeout:1 attempts:2` in
`/etc/resolv.conf`. Otherwise unresponsive name servers keep deliveries running until the worker
aborts them without recording the failure.

### Retries

Temporary failures pause **all** deliveries to that destination for 30 seconds, and consecutive ones
for 2, 10 and then 30 minutes each:

* timeouts, connection, TLS and DNS errors
* HTTP 408, 425, 429 and 5xx responses

A `Retry-After` header in seconds extends the pause, up to 30 minutes.
After the pause, one delivery probes the destination while the others wait. Any other response,
including 4xx, resets the backoff and resumes all deliveries. Responses like 400, 404 or 410 are
terminal and never retried. Unexpected errors, e.g. database errors, go to the exception handler and
are retried with the same delays.

Deliveries still queued after 24 hours are dropped. A delivery that would only resume after
it expires comes back one minute before instead, and is sent if the pause was lifted in the
meantime (e.g. by a successful test event), otherwise it's recorded as failed.

The pause state lives in the application cache, which must be shared by all servers and workers.
Without it, deliveries are retried one by one.

Deliveries that can't be pushed to the queue, e.g. because the queue server is down, are lost. The
error is reported to the exception handler and the subscriptions show "Queue unavailable".

Pagible doesn't limit the payload size, so the queue backend and the receivers must accept the
encrypted queue messages and HTTP requests.

### Log entries

`delivery_retried`, `delivery_expired`, `delivery_blocked` and `endpoint_invalid` are logged at most
once every 10 minutes for the same problem and destination:

| Entry | Logged when |
|-------|-------------|
| `cms.webhook` | A subscription was `created`, `updated`, `secret_rotated` or `deleted`, with the editor, ID, status, events, endpoint and changed fields |
| `cms.webhook.delivered` | An operator endpoint received a delivery (only if `CMS_LOG_CHANNEL` is set) |
| `cms.webhook.delivery_retried` | A delivery failed temporarily, with the reason and HTTP status |
| `cms.webhook.delivery_failed` | A delivery failed for good |
| `cms.webhook.delivery_expired` | A delivery was queued for more than 24 hours, i.e. workers can't keep up or were stopped too long |
| `cms.webhook.delivery_blocked` | Nothing was queued because of `invalid_queue`, `queue_failed` or `invalid_policy` |
| `cms.webhook.endpoint_invalid` | An operator endpoint is misconfigured and was skipped |

## Subscriptions

Tenants manage their subscriptions in the admin panel or through GraphQL.

* New subscriptions are inactive unless created with `status: true`.
* The secret is only returned by the create and rotate operations, store it then.
* The URL isn't returned, only the `endpoint` without the query string and the last path segment,
  which may contain credentials, e.g. `https://hooks.slack.com/services/T0/B0/` for
  `https://hooks.slack.com/services/T0/B0/XXXX`.
* The URL can't be changed, delete the subscription and add a new one instead. The new one gets its
  own secret, so requests signed for one destination can't be replayed to the other.
* The optional `name` (up to 100 characters, e.g. "Shop sync") tells subscriptions to the same
  endpoint apart. Names are visible to all webhook editors, so don't put secrets in them. In
  GraphQL, an omitted `name` keeps the current one and an empty one removes it.
* Changing the events cancels queued deliveries of the removed events. Queued deliveries are dropped
  while the subscription is inactive.
* Rotating the secret keeps the status and queued deliveries. For 24 hours, requests are signed with
  the new and the previous secret, and rotating again drops the older one.
* "Test" in the edit dialog of the admin panel (`pingWebhook` mutation) immediately sends a signed
  `webhook.ping` event, even to inactive subscriptions, and returns the HTTP status or the error.
  Test events aren't retried and don't change the health, but a successful one resumes a paused
  destination.
* GraphQL returns dates in UTC, e.g. `2026-09-15T12:00:00.000000Z`.

### Health

After each attempt, `last_success_at` (updated at most once a minute) or `last_error` (reason, HTTP
status and time) is updated. The admin panel shows the last error, paused subscriptions with the end
of the pause (`paused_until`), and a warning above the list if webhooks are disabled or blocked
(`cmsWebhookServer` in GraphQL). Response bodies aren't read, only the HTTP status
counts. Health updates don't change `updated_at`.

| Reason | Meaning |
|--------|---------|
| `http_error` | HTTP error status, 3xx is shown as "Redirects aren't followed" |
| `timeout`, `connection_failed`, `resolution_failed`, `transport_error` | Network errors |
| `response_headers_too_large` | The response headers are too large |
| `destination_not_allowed` | The URL points to a denied address |
| `invalid_policy` | Blocked because of an invalid `deny_cidrs` entry |
| `queue_failed` | The delivery couldn't be pushed to the queue |
| `invalid_encryption` | The secret can't be decrypted with the current keys |
| `delivery_failed` | The queue worker failed |

## Receiving webhooks

These events are sent:

* `page.published`, `page.moved`, `page.deleted`, `page.restored`, `page.purged`
* `element.published`, `element.deleted`, `element.restored`, `element.purged`
* `file.published`, `file.deleted`, `file.restored`, `file.purged`

The payload contains the event name, the tenant, the UTC event time (the same for all attempts) and
the content and version IDs. Page events also contain the route if available:

```json
{
  "event": "page.published",
  "tenant_id": "tenant",
  "timestamp": "2026-09-14T12:00:00.000+00:00",
  "data": {
    "id": "01995d6a-cb84-7218-9bb9-79063c4bf681",
    "version_id": "01995d6a-cb84-7218-9bb9-79063c4bf682",
    "path": "products/example",
    "domain": "example.com"
  }
}
```

Bulk events contain an ordered array of `{id, version_id}` references in `data`. Fetch the details
through the CMS API if needed.

With a `Tenancy` callback registered, events without a tenant are dropped, so don't use the default
(empty) tenant ID for a site in multi-tenant installations.

### Verifying requests

Requests are signed like [Standard Webhooks](https://www.standardwebhooks.com), so their
[libraries](https://github.com/standard-webhooks/standard-webhooks) can verify them:

* `webhook-id`: delivery ID, the same for all attempts
* `webhook-timestamp`: Unix time of the attempt
* `webhook-signature`: `v1,<base64 hmac>`, space separated if signed with two secrets during a rotation

Without a library, verify each request before processing it:

1. Reject missing or malformed headers.
2. Reject a `webhook-timestamp` outside your allowed clock skew, e.g. five minutes.
3. Compute `v1,` + base64 of the HMAC-SHA256 over `<webhook-id>.<webhook-timestamp>.<raw body>`,
   keyed with the base64 decoded part of the secret after `whsec_`.
4. Accept the request if any received signature matches in a constant-time comparison.
5. Claim the `webhook-id` atomically and return 2xx for duplicates. Keep the claims for at least
   24 hours plus the clock skew.

A Laravel receiver (in `routes/api.php` or excluded from CSRF verification):

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::post( '/hooks/cms', function( Request $request ) {

    $id = (string) $request->header( 'webhook-id' );
    $time = (string) $request->header( 'webhook-timestamp' );
    $signatures = explode( ' ', (string) $request->header( 'webhook-signature' ) );

    if( $id === '' || !ctype_digit( $time ) || abs( time() - (int) $time ) > 300 ) {
        abort( 401 );
    }

    // The key is the base64 part of the "whsec_..." secret and the body is the raw one,
    // not the decoded and re-encoded JSON
    $key = base64_decode( substr( (string) config( 'services.cms.webhook_secret' ), 6 ) );
    $hmac = hash_hmac( 'sha256', $id . '.' . $time . '.' . $request->getContent(), $key, true );
    $expected = 'v1,' . base64_encode( $hmac );

    if( !array_filter( $signatures, fn( $signature ) => hash_equals( $expected, $signature ) ) ) {
        abort( 401 );
    }

    // Delivered before: 24 hours delivery age plus 5 minutes clock skew
    if( !Cache::add( 'cms-delivery:' . $id, true, 86700 ) ) {
        return response()->noContent();
    }

    try {
        $event = json_decode( $request->getContent(), true, 512, JSON_THROW_ON_ERROR );
        // Process $event['event'] and $event['data'] ...
    } catch( \Throwable $e ) {
        Cache::forget( 'cms-delivery:' . $id ); // so the retry isn't skipped
        throw $e;
    }

    return response()->noContent();
} );
```

Deliveries can arrive out of order, so treat each one as a hint and fetch the current state through
the CMS API, or ignore events older than the last one processed for the same item. Return 2xx for
events you don't handle, e.g. `webhook.ping`, so they aren't recorded as failed.

## Destination security

|  | Subscriptions | Operator endpoints |
|--|---------------|--------------------|
| Scheme and port | HTTPS on port 443 | HTTP or HTTPS on any port |
| Private and loopback addresses | Blocked | Allowed |
| Link-local (e.g. `169.254.169.254`), multicast, transition and reserved addresses | Blocked | Blocked |
| `deny_cidrs` | Blocked | Blocked |
| Host names from `/etc/hosts` | No | Yes |

For both, redirects and environment proxies are disabled. DNS is resolved right before each call and
the allowed addresses are pinned in cURL, which falls back to the next one if a server is unreachable.
The delivery is rejected if no allowed address remains.

## Operator endpoints

Internal services such as search indexers or cache purgers are configured in
`config/cms/webhooks.php` instead of the admin panel. They receive the events of all tenants, which
the `tenant_id` of the payload tells apart:

```php
'endpoints' => [
    'indexer' => [
        'url' => env( 'CMS_WEBHOOK_INDEXER_URL' ),        // e.g. http://indexer:8080/cms
        'secret' => env( 'CMS_WEBHOOK_INDEXER_SECRET' ),  // "whsec_..." or a list
        'events' => ['page.published', 'page.deleted'],
    ],
],
```

* Names may contain letters, digits, `_` and `-`.
* Create secrets with `echo "whsec_$(openssl rand -base64 32)"`. To rotate one, use a list of the new
  and the previous secret, empty entries are ignored:
  `'secret' => [env( 'CMS_WEBHOOK_INDEXER_SECRET' ), env( 'CMS_WEBHOOK_INDEXER_PREVIOUS_SECRET' )]`
* Prefer HTTPS if the traffic leaves the host. Certificates of a private CA must be trusted by the
  worker hosts, e.g. added with `update-ca-certificates` or by the `curl.cainfo` setting in `php.ini`.
* Endpoints are validated for each event. Invalid ones are skipped and logged while all others still
  receive the event.
* A changed URL or secret applies to queued deliveries too, removing an event cancels its queued
  deliveries. If the old URL was paused, the new one waits until the pause ends.
* Endpoints aren't shown in the admin panel and report through the log only.

## Maintenance

### Rotating `APP_KEY`

Secrets and queued deliveries are encrypted with the application key and aren't re-encrypted with a
new one. Keep the old key in `APP_PREVIOUS_KEYS` so they can still be decrypted. If the key was
rotated because it leaked, consider the secrets compromised too and let the tenants rotate them.

Once the old key is removed, deliveries still queued with it fail, and subscriptions whose secret
wasn't rotated since fail without retries until the tenant rotates the secret or deletes them. The
admin panel asks for the rotation and `php artisan cms:webhooks:check` reports the number of affected
subscriptions.

### Removing a tenant

Subscriptions are stored in the `cms_webhooks` table by `tenant_id`, so delete them together with the
other data of the tenant. Queued deliveries of deleted subscriptions are dropped.

## Admin translations

The webhook panel keeps its gettext sources in `admin/i18n`. Use `$pgettext('webhooks', ...)` for UI
strings, then update and build the catalogs:

```bash
cd admin
npm run gettext:extract
npm run build
```

The compiled JSON catalogs are staged in the ignored `admin/public/i18n` directory and published to
`admin/dist/i18n` with the panel bundle. Commit the PO sources and `admin/dist`, not the staging copies.

## Admin tests

The component tests in `admin/cypress` run with the Cypress setup of the Pagible admin package, so
they only work in the monorepo and its npm dependencies must be installed in `../admin` first:

```bash
cd admin
npm run test:unit
```

The tests also mount the committed `admin/dist` bundle and CI fails if it doesn't match the sources,
so rebuild and commit it together with every change in `admin/src` or `admin/i18n`.
