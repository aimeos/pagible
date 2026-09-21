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
| `queue.backoff` | `[30, 120, 600, 1800]` | Seconds to pause a destination after consecutive temporary failures, the last value repeats |
| `queue.max_age` | `86400` | Seconds until queued deliveries expire |
| `rotation_grace` | `86400` | Seconds the previous secret still signs requests after a rotation, `0` disables it |
| `http.connect_timeout` | `3` | Seconds to connect |
| `http.timeout` | `10` | Seconds to wait for the response |
| `limits.total` | `100` | Subscriptions per tenant |
| `limits.active` | `25` | Active subscriptions per tenant |
| `endpoints` | `[]` | [Operator endpoints](#operator-endpoints) for internal services |
| `deny_cidrs` | `[]` | IP addresses and CIDR ranges denied for all destinations, e.g. `['10.1.0.0/16', '10.2.0.5']` |

Lowering the limits doesn't delete or deactivate existing subscriptions, but none can be added or
activated until the tenant is below the limit again. An invalid `deny_cidrs` entry blocks all
deliveries until it's fixed.

## Operations

A broken webhook configuration never stops the application, it only stops the affected deliveries.
Run the check on every deploy. It lists each problem with the setting to fix and fails if there are any:

```bash
php artisan cms:webhooks:check [--resolve]
```

`--resolve` also resolves the endpoint host names and checks the addresses against `deny_cidrs`. Run
it on a host with the same DNS as the queue workers. The command also reports:

* a queue `retry_after` (or broker visibility timeout) too low for the delivery timeout
* an `array` or `null` cache store, which can't pause destinations or throttle the log entries
  across servers
* a stalled queue, i.e. no delivery was processed for 10 minutes while new ones were queued. This
  is only a warning, so the deploy which starts the workers again isn't blocked.
* subscriptions encrypted with a key that isn't available any more, also only a warning

### Workers and timeouts

Each destination is a separate job and a slow endpoint occupies a worker for up to the HTTP
timeouts. Monitor queue depth, oldest job age and failed jobs, and add workers if scheduled
publications create bursts.

Workers abort a delivery after `connect_timeout` + `timeout` + 10 seconds for resolving the host name
and recording the result (23 seconds by default). Set the queue's `retry_after` higher, otherwise
running deliveries are released twice.

Resolving the host name uses up the response time, and a single DNS query can't be interrupted.
Configure short resolver timeouts on the worker hosts, e.g. `options timeout:1 attempts:2` in
`/etc/resolv.conf`. Otherwise unresponsive name servers keep deliveries running until the worker
aborts them without recording the failure.

### Retries

Temporary failures pause **all** deliveries to that destination for the next `queue.backoff` delay:

* timeouts, connection, TLS and DNS errors
* HTTP 408, 425, 429 and 5xx responses

A `Retry-After` header (seconds or HTTP date) extends the pause, up to the longest backoff delay.
After the pause, one delivery probes the destination while the others wait. Any other response,
including 4xx, resets the backoff and resumes all deliveries. Responses like 400, 404 or 410 are
terminal and never retried. Unexpected errors, e.g. database errors, go to the exception handler and
are retried with the same delays.

Deliveries still queued after `queue.max_age` are dropped. A delivery that would only resume after
it expires comes back one minute before instead, and is sent if the pause was lifted in the
meantime (e.g. by a successful test event), otherwise it's recorded as failed.

The pause state lives in the application cache, which must be shared by all servers and workers.
Without it, deliveries are retried one by one.

Deliveries that can't be pushed to the queue, e.g. because the queue server is down, are lost. The
error is reported to the exception handler and the subscriptions show "Queue unavailable".

Pagible doesn't limit the payload size, so the queue backend and the receivers must accept the
encrypted queue messages and HTTP requests.

### Log entries

Warnings are throttled to one every 10 minutes for the same problem and destination:

| Entry | Logged when |
|-------|-------------|
| `cms.webhook` | A subscription was `created`, `updated`, `destination_replaced`, `secret_rotated`, `deleted` or `purged`, with the editor, ID, status, events, endpoint and changed fields |
| `cms.webhook.delivered` | An operator endpoint received a delivery (only if `CMS_LOG_CHANNEL` is set) |
| `cms.webhook.delivery_retried` | A delivery failed temporarily, with the reason and HTTP status |
| `cms.webhook.delivery_failed` | A delivery failed for good |
| `cms.webhook.delivery_expired` | A delivery exceeded `queue.max_age`, i.e. workers can't keep up or were stopped too long |
| `cms.webhook.delivery_blocked` | Nothing was queued because of `invalid_queue`, `queue_failed` or `invalid_policy` |
| `cms.webhook.endpoint_invalid` | An operator endpoint is misconfigured and was skipped |

## Subscriptions

Tenants manage their subscriptions in the admin panel or through GraphQL.

* New subscriptions are inactive unless created with `status: true`, replaced ones are always inactive.
* The secret is only returned by the create, replace and rotate operations, store it then.
* The optional `name` (up to 100 characters, e.g. "Shop sync") tells subscriptions to the same host
  apart, because only the scheme and host of the `endpoint` are shown. Control characters are
  replaced and names are visible to all webhook editors, so don't put secrets in them. In GraphQL,
  an omitted `name` keeps the current one and an empty one removes it.
* Changing the events cancels queued deliveries of the removed events. Deactivating or replacing the
  destination cancels all queued deliveries.
* Rotating the secret keeps the status and queued deliveries. During `rotation_grace`, requests are
  signed with the new and the previous secret, and rotating again drops the older one. Replacing the
  destination discards the previous secret immediately.
* "Send test event" in the admin panel (`pingWebhook` mutation) immediately sends a signed
  `webhook.ping` event, even to inactive subscriptions, and returns the HTTP status or the error.
  Test events aren't retried and don't change the health, but a successful one resumes a paused
  destination. Each subscription can be tested once every 10 seconds.
* GraphQL returns dates in UTC, e.g. `2026-09-15T12:00:00.000000Z`.

### Health

After each attempt, `last_success_at` (updated at most once a minute) or `last_error` (reason, HTTP
status and time) is updated. The admin panel shows the last error, paused subscriptions with the end
of the pause (`paused_until`), and a warning above the list if webhooks are disabled, blocked or the
queue stalled (`cmsWebhookServer` in GraphQL). Response bodies over 16 KB aren't read, only the
HTTP status counts. Health updates don't change `updated_at`.

| Reason | Meaning |
|--------|---------|
| `http_error` | HTTP error status, 3xx is shown as "Redirects aren't followed" |
| `timeout`, `connection_failed`, `resolution_failed`, `tls_error`, `transport_error` | Network errors |
| `response_headers_too_large` | The response headers are too large |
| `destination_not_allowed` | The URL points to a denied address |
| `invalid_policy` | Blocked because of an invalid `deny_cidrs` entry |
| `queue_failed` | The delivery couldn't be pushed to the queue |
| `invalid_encryption` | The destination can't be decrypted with the current keys |
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
   `queue.max_age` plus the clock skew.

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
the allowed addresses are pinned in cURL, which falls back to the next one if a server is unreachable
(libcurl 7.59+). The delivery is rejected if no allowed address remains.

## Operator endpoints

Internal services such as search indexers or cache purgers are configured in
`config/cms/webhooks.php` instead of the admin panel:

```php
'endpoints' => [
    'indexer' => [
        'url' => env( 'CMS_WEBHOOK_INDEXER_URL' ),        // e.g. http://indexer:8080/cms
        'secret' => env( 'CMS_WEBHOOK_INDEXER_SECRET' ),  // "whsec_..." or a list
        'events' => ['page.published', 'page.deleted'],
        'tenants' => ['tenant-id'],                       // optional, default: all tenants
        'ca' => '/etc/ssl/certs/internal-ca.pem',         // optional CA bundle for private HTTPS
    ],
],
```

* Names may contain letters, digits, `_` and `-`.
* Create secrets with `echo "whsec_$(openssl rand -base64 32)"`. To rotate one, use a list of the new
  and the previous secret, empty entries are ignored:
  `'secret' => [env( 'CMS_WEBHOOK_INDEXER_SECRET' ), env( 'CMS_WEBHOOK_INDEXER_PREVIOUS_SECRET' )]`
* Unset optional values are ignored, but an empty `tenants` list invalidates the endpoint.
* Prefer HTTPS with `ca` if the traffic leaves the host.
* Endpoints are validated for each event. Invalid ones are skipped and logged while all others still
  receive the event.
* Changing the URL cancels queued deliveries for the old one, removing an event or tenant cancels
  its queued deliveries. Changed secrets apply to queued deliveries too.
* Endpoints aren't shown in the admin panel and report through the log only.

## Maintenance

### Re-encrypting after an `APP_KEY` rotation

Destinations, secrets and queued jobs are encrypted with the application key. Keep the old keys in
`APP_PREVIOUS_KEYS`, stop or drain the webhook workers and run:

```bash
php artisan cms:webhooks:reencrypt [--tenant=<id>]
```

This also deletes previous secrets whose rotation grace period has ended. Subscriptions whose key is
no longer available are skipped and listed with their tenants, e.g. `Affected tenants: shop-a (2)`,
and the command fails. Their deliveries fail without retries until you add the key to
`APP_PREVIOUS_KEYS` again and rerun the command, or the tenant replaces the URL or deletes them.
Subscriptions changed while the command runs are reported too, so rerun it afterwards.

### Removing a tenant

```bash
php artisan cms:webhooks:purge tenant-id
```

Deletes all subscriptions of the tenant. If they're changed at the same time, nothing is deleted and
the command can be run again.

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
