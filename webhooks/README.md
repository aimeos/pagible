# Pagible CMS webhooks

This optional package sends signed, tenant-scoped notifications for committed Pagible CMS lifecycle events. It uses the Laravel queue and does not add an event bus, outbox or webhook dependency to the core package.

## Installation

```bash
composer require aimeos/pagible-webhooks
php artisan cms:install:webhooks
php artisan migrate
```

For production, configure an asynchronous queue connection and run a dedicated worker for the `cms-webhooks` queue. The `sync` driver is supported for immediate delivery without a worker: every destination is still called even if another one fails, but failed deliveries aren't retried. With the `null` queue driver, no deliveries are queued and a `cms.webhook.delivery_blocked` warning with the reason `invalid_queue` is logged.

```dotenv
CMS_WEBHOOKS_ENABLED=true
CMS_WEBHOOKS_QUEUE_CONNECTION=redis
CMS_WEBHOOKS_QUEUE=cms-webhooks
```

```bash
php artisan queue:work redis --queue=cms-webhooks
```

A broken webhook configuration never stops the application, it only stops the affected deliveries.
The same problem is logged at most once every 10 minutes instead of once per event. Check the deny
list, the operator endpoints and the delivery queue as part of every deploy. The command lists each
problem with the setting to fix and exits with an error if there are any:

```bash
php artisan cms:webhooks:check
```

```
cms.webhooks.endpoints.indexer: The secret must be a string or a list of strings, each "whsec_" followed by at least 24 base64 encoded bytes like from "openssl rand -base64 32", empty entries are ignored
```

Add `--resolve` to also resolve the endpoint host names and check the addresses against the deny
list. Run it on a host with the same DNS as the queue workers, because the results depend on it.

Run enough workers for the expected delivery rate: each destination is an independent job and a
slow endpoint can occupy a worker for up to the configured webhook timeout (default: 3 seconds to
connect and 10 seconds for the response). Monitor queue depth, oldest-job age and failed-job rate,
and scale the dedicated workers when scheduled publications create bursts. Workers abort a delivery
after the sum of `cms.webhooks.http.connect_timeout` and `cms.webhooks.http.timeout` plus 5 seconds
for resolving the host name and 5 seconds for recording the result (default: 23 seconds), so
configure the queue connection's `retry_after` or broker visibility timeout above it, otherwise a
running delivery is released twice. `cms:webhooks:check` reports a lower value, as well as an
`array` or `null` cache store which can't pause failing destinations or throttle the log entries
across servers and workers.

The connect timeout doesn't limit resolving the host name, so the time it took is subtracted from
the time left for the response. If less than the connect timeout is left after resolving, the
delivery fails with `timeout` and is retried like any other temporary failure. A single DNS query can't be interrupted, so configure short resolver
timeouts on the queue worker hosts, e.g. `options timeout:1 attempts:2` in `/etc/resolv.conf`.
Otherwise, unresponsive name servers can keep a delivery running until the queue worker aborts it,
which neither records the failure nor pauses the destination.

If no queue worker processed any delivery for more than 10 minutes while new deliveries were
queued, e.g. because no worker runs for the `cms-webhooks` queue, `cms:webhooks:check` warns about
it for `cms.webhooks.queue.name` and the admin panel shows a warning above the list (`stalled_since`
of `cmsWebhookServer` in GraphQL) until a worker processes the next delivery. Deliveries which wait
for their next retry don't count. The warning doesn't make the command fail, so it doesn't stop
the deploy which starts the workers again.

If the deliveries can't be pushed to the queue, e.g. because the queue server is down, they are
lost, because nothing retries them. The error is reported to the exception handler, a
`cms.webhook.delivery_blocked` warning with the reason `queue_failed` is logged and the admin panel
shows "Queue unavailable" for the affected subscriptions until their next successful delivery.

Deliveries still queued after `cms.webhooks.queue.max_age` seconds (default: one day) are dropped
and logged as a `cms.webhook.delivery_expired` warning with the subscription ID or endpoint name,
at most once every 10 minutes per destination. These warnings mean that the workers can't keep up
with the events or were stopped for too long.

Temporary failures (timeouts, connection, TLS and DNS errors, HTTP 408, 425, 429 and 5xx
responses) pause all deliveries to the same destination instead of sending each queued delivery to
an unavailable receiver, and the deliveries are retried after the pause until they expire. The
pauses are configured in `cms.webhooks.queue.backoff` (default: 30 seconds, 2, 10 and 30 minutes):
each consecutive failure uses the next value and the last one repeats until `max_age` is reached.
A `Retry-After` response header (seconds or HTTP date) extends the pause accordingly, limited to
the longest backoff delay. Paused deliveries go back to the queue without calling the receiver.
If the pause ends after the delivery expires, the delivery comes back one minute before it expires
instead: it's sent if the pause was lifted in the meantime, e.g. by a successful test event, and
recorded as failed otherwise. After the pause, only one delivery probes the destination while the others wait for its
result, and failures of parallel deliveries during a pause don't advance the schedule. Any
response that isn't a temporary failure, including 4xx responses, shows that the receiver is
available again: it resets the schedule and resumes all deliveries. Each retry is logged as a
`cms.webhook.delivery_retried` warning with the reason and HTTP status, at most once every 10
minutes per destination, reason and status. A delivery which can't be retried before it expires is
recorded as failed. Other HTTP responses like 400, 404 or 410 are terminal and never retried.
Unexpected errors while processing a delivery, e.g. database errors, are reported to the exception
handler and the queue worker retries the delivery after the backoff delays until it expires.

The admin panel shows paused subscriptions with the end of the pause (`paused_until` in GraphQL).
The state is stored in the application cache, which must be shared by all servers and webhook
workers. Without a usable cache, destinations aren't paused and each delivery is retried on its own
after the backoff delays.

Pagible does not impose a separate outbound webhook payload-size limit. Core lifecycle events carry
bounded references, but the selected queue backend and receiver must accept the resulting encrypted
queue message and HTTP request sizes.

Provision subscriptions through the CMS admin panel or GraphQL. New subscriptions are inactive unless created with `status: true` and replaced subscriptions are always inactive. Store the secret returned by the create, replace or rotate operation; it is never returned by ordinary queries.

Subscriptions can have an optional `name` of up to 100 characters, e.g. "Shop sync", because the
admin panel and GraphQL only show the scheme and host of their destination (`endpoint`), which can
be the same for several subscriptions. The admin panel shows the name above the endpoint
and finds subscriptions by their name. Line breaks and other control characters are replaced by
spaces and bidirectional text controls, which can display a name reversed, are removed. Names are
visible to all editors allowed to manage webhooks, so don't put secrets in them.
When saving a subscription through GraphQL, an omitted `name` keeps the current one and an empty
name removes it.

Each tenant can have up to `cms.webhooks.limits.total` subscriptions (default: 100), of which
`cms.webhooks.limits.active` can be active (default: 25). Lowering the limits doesn't delete or
deactivate existing subscriptions: they are still listed, receive events and can be deleted, but
no subscription can be added or activated until the tenant is below the respective limit again.

Adding, changing, replacing, rotating and deleting subscriptions is logged as a `cms.webhook`
warning with the action (`created`, `updated`, `destination_replaced`, `secret_rotated` or
`deleted`), the editor, the subscription ID, its status, number of events and destination
(`endpoint`, like in GraphQL), and the previous destination (`old_endpoint`) if it was replaced.
Destinations which can't be decrypted any more are logged as `[invalid endpoint]`. Changes also
contain the names of the changed fields (`changes`, e.g. `["status", "events"]`), while saving a
subscription without changes isn't logged and keeps its editor.

Dates of subscriptions and the server status are returned by GraphQL in UTC as ISO 8601 strings
like `2026-09-15T12:00:00.000000Z`, independent of the application's timezone.

Changing the events of a subscription keeps its queued deliveries and the pause of its destination,
only queued deliveries of removed events are cancelled. Deactivating a subscription or replacing
its destination cancels all queued deliveries, so they aren't sent after activating it again.
Cancelled deliveries are dropped when a worker picks them up next, without waiting for a paused
destination.

Rotating the secret keeps the subscription's status and health and doesn't cancel queued deliveries. During the
grace period, each request is signed with the new and the previous secret, so receivers can switch
to the new secret without losing deliveries. The grace period is
configured in `cms.webhooks.rotation_grace` (default: one day, `0` disables it). Rotating again
during the grace period replaces the previous secret, so the older one isn't accepted any more.
Replacing the destination discards the previous secret immediately. After the grace period, the
previous secret isn't used any more and `cms:webhooks:reencrypt` deletes it.

Use "Send test event" in the admin panel or the `pingWebhook` GraphQL mutation to check a
subscription after setting up its receiver. It sends a signed `webhook.ping` event immediately,
also to inactive subscriptions as long as webhooks are enabled, and returns the HTTP status or the
failure reason. Test events are not retried and don't change the subscription health, but a
successful test event resumes the deliveries of a paused subscription immediately. Each
subscription can be tested once every 10 seconds, after replacing its destination it can be tested
again immediately.

Internal services such as search indexers or cache purgers are configured by the operator as
[endpoints](#operator-endpoints) instead.

## Delivery contract

The package emits exact event names only:

- `page.published`, `page.moved`, `page.deleted`, `page.restored`, `page.purged`
- `element.published`, `element.deleted`, `element.restored`, `element.purged`
- `file.published`, `file.deleted`, `file.restored`, `file.purged`

In multi-tenant installations, i.e. if a `Tenancy` callback is registered, events without a tenant
are dropped instead of being sent to the subscriptions of the default tenant. Don't use the default
tenant (the empty tenant ID) for a site there, its events would look like a lost tenant context.

Each request contains a bounded event reference with the event name (`event`), the tenant
(`tenant_id`) and the time of the event (`timestamp`) in the JSON body. The timestamp is in UTC with
millisecond precision, e.g. `2026-09-14T12:00:00.000+00:00`, and the same for all attempts. Requests
are signed like [Standard Webhooks](https://www.standardwebhooks.com) with these headers:

- `webhook-id`: ID of the delivery, the same for all attempts
- `webhook-timestamp`: Unix timestamp in seconds when the attempt was sent
- `webhook-signature: v1,<base64 hmac>` or, during a secret rotation, `v1,<base64 hmac> v1,<base64 hmac>`

Secrets have the Standard Webhooks format `whsec_<base64 key>`, so the
[Standard Webhooks libraries](https://github.com/standard-webhooks/standard-webhooks) for many
languages can verify the requests (steps 1 to 5). Without a library, verify every request before
parsing or processing its body:

1. Read `webhook-id`, `webhook-timestamp` and `webhook-signature`, rejecting missing or malformed
   values. The signature header contains one or more signatures separated by spaces, each `v1,`
   followed by a base64 encoded HMAC.
2. Reject `webhook-timestamp` when it is outside the receiver's allowed clock-skew window. Five
   minutes is a reasonable default. Use the signed header value, not the event `timestamp` in the
   JSON payload which doesn't change between retries.
3. Build the signed content `<webhook-id>.<webhook-timestamp>.<raw-request-body>`. The body must be
   the exact bytes received, before JSON decoding or re-encoding.
4. Compute `v1,` followed by the base64 encoded HMAC-SHA256 of the signed content. The key is the
   base64 decoded part of the secret after `whsec_`, not the secret itself.
5. Compare the complete expected signature with each received signature using a constant-time
   comparison such as PHP's `hash_equals()` and accept the request if any of them matches.
   Receivers which store both secrets during a rotation compute the expected signature for each.
6. Atomically claim `webhook-id` before applying side effects. Treat an already claimed value as
   a successful duplicate and return a 2xx response. Retain claims for at least the sender's maximum
   delivery age plus the accepted clock skew: at least 24 hours and five minutes with the defaults.
7. Decode the verified body and use its `event` and `tenant_id` values.

Retries retain the same `webhook-id` but receive a fresh signed `webhook-timestamp` value.

A Laravel receiver which follows these steps could look like this. Register the route in
`routes/api.php` or exclude it from CSRF verification:

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

Deliveries may arrive out of order. Retries, paused destinations and parallel workers can deliver
an older event after a newer one for the same item. Treat each notification as a hint that the item
changed and fetch its current state through the CMS API, or ignore events whose payload `timestamp`
is older than the last one processed for the same item. Return a 2xx response for event names the
receiver doesn't handle, e.g. `webhook.ping`, so new events aren't recorded as failed.

Subscription health is updated after each delivery attempt. `last_error` stores the reason, HTTP
status and time (`at`, a UTC date like the others) of the last failure, including temporary
failures which are still retried, and the admin panel shows it with its time. `last_success_at`
records the last successful response, while `last_error` is cleared. While deliveries keep
succeeding, `last_success_at` is updated at most once a minute. Concurrent deliveries update this
state in database completion order. Expired, cancelled and disabled jobs never change health
state. Destination replacement clears the health state for the new subscription revision,
while secret rotation keeps it. Health updates don't change `updated_at`, which only records the
last change of the subscription's configuration.
While the server configuration blocks all deliveries (invalid `deny_cidrs` setting or unusable
queue connection), no deliveries are queued. Deliveries which were already queued when the
`deny_cidrs` setting became invalid are stored in `last_error` with the reason `invalid_policy`,
which the admin panel shows as "Blocked by server configuration" until the next successful
delivery. The same applies to deliveries which couldn't be pushed to the queue, stored with the
reason `queue_failed` and shown as "Queue unavailable". Other reasons are
`http_error` with the HTTP status (3xx responses are shown as "Redirects aren't followed"),
`timeout`, `connection_failed`, `resolution_failed`, `tls_error`, `transport_error`,
`response_headers_too_large`, `destination_not_allowed` and `delivery_failed` if the queue worker
failed. Response bodies larger than 16 KB aren't read, only the HTTP status counts. If webhooks are disabled or all deliveries are blocked, the admin panel also shows a warning
above the list (`cmsWebhookServer` in GraphQL).
Operator endpoints have no stored health state and report through the log instead.

Single-item notifications contain the content and version IDs. Page notifications also include the route context carried by the committed CMS event when available:

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

Bulk notifications contain an ordered array of `{id, version_id}` references. Fetch current content through the CMS API when more detail is required; webhook delivery does not reload mutable content models.

## Destination security

Subscriptions created in the admin panel or through GraphQL must use HTTPS on port 443 and a
public host. Redirects and environment proxies are disabled, DNS is resolved immediately before
every call, and the allowed public addresses are pinned into cURL, which tries the next one if a
server can't be reached (libcurl 7.59 or later, older versions use only the first address). IPv4
and IPv6 addresses are looked up separately, so a DNS server failing for one of them doesn't stop
deliveries to the other. If the IPv4 query takes longer than a second and fails or returns
addresses, IPv6 addresses aren't queried so a slow DNS server isn't waited for twice. Loopback, private, link-local, transition and reserved addresses are
skipped and the delivery is rejected if no allowed address remains. The IP addresses and CIDR ranges listed in
`deny_cidrs` (e.g. `['10.1.0.0/16', '10.2.0.5']`) are rejected for all destinations, including
operator endpoints. If `deny_cidrs` contains an invalid entry, all deliveries are blocked and logged
as `cms.webhook.delivery_blocked` or `cms.webhook.delivery_failed` with the reason `invalid_policy`
until the list is fixed. The admin panel shows a warning above the list and adding or changing
subscriptions fails with "Webhooks are blocked by the server configuration."

## Operator endpoints

Webhooks for internal services are defined in `config/cms/webhooks.php` instead of the admin panel.
Only people who can change the application configuration can add them:

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

Endpoints use the same payload, headers and signature as subscriptions, so receivers verify them
the same way. Use the `tenant_id` of the payload to tell tenants apart when an endpoint receives
events for all tenants. Create the secret from at least 24 random bytes in the Standard Webhooks
format, e.g. with `echo "whsec_$(openssl rand -base64 32)"`. Optional values which aren't set are
ignored, so `'ca' => env( 'CMS_WEBHOOK_INDEXER_CA' )` doesn't invalidate the endpoint if the
environment variable is missing. The exception is an empty `tenants` value, which invalidates the
endpoint instead of sending it the events of all tenants.

To rotate an endpoint secret, configure a list with the new and the previous secret. Each request
is then signed with both, as for subscriptions during a secret rotation, so the receiver can switch
to the new secret without rejecting deliveries. Empty entries are ignored, so the previous secret
can be removed from the environment after the receiver switched:

```php
'secret' => [env( 'CMS_WEBHOOK_INDEXER_SECRET' ), env( 'CMS_WEBHOOK_INDEXER_PREVIOUS_SECRET' )],
```

Endpoints may use HTTP or HTTPS on any port and may resolve to private or loopback addresses.
Link-local addresses (including cloud metadata services like `169.254.169.254`), multicast,
transition and reserved ranges and `deny_cidrs` stay blocked. Redirects and proxies are disabled
and the resolved addresses are pinned as for subscriptions. Host names unknown to DNS are also
looked up by the system resolver, e.g. from `/etc/hosts`. Prefer HTTPS with `ca` whenever the
traffic leaves the host, because HTTP sends the payload unencrypted.

Endpoint names may contain letters, digits, `_` and `-`. Endpoints are checked whenever an event
is delivered. An endpoint with an invalid name, URL, secret, event name, tenant list, CA file or
unknown key is skipped and logged as a `cms.webhook.endpoint_invalid` warning while all other
endpoints and subscriptions still receive the event. Queued deliveries are checked again before
sending, so an endpoint that became invalid in the meantime logs `cms.webhook.delivery_failed`.
Use `php artisan cms:webhooks:check` to find these problems before deploying.

Endpoints are not shown in the admin panel. Terminal delivery failures are logged as
`cms.webhook.delivery_failed` warnings with the endpoint name. Successful deliveries are logged as
`cms.webhook.delivered` when the CMS log channel (`CMS_LOG_CHANNEL`) is set. Changing an endpoint's
URL cancels deliveries queued for the previous destination, and removing an event or tenant cancels
its pending deliveries. Rewriting the URL to an equivalent one (upper case host, default port,
`.` and `..` segments) changes nothing because URLs are normalized first. Changed secrets don't cancel queued deliveries, they are signed with the
secrets configured when they are sent.

Destinations, secrets and queued jobs use application encryption. During `APP_KEY` rotation, retain old keys in Laravel's `APP_PREVIOUS_KEYS`, stop or drain the webhook worker, and run:

```bash
php artisan cms:webhooks:reencrypt
```

Subscriptions encrypted with a key that is no longer available are still listed in the admin panel,
with the last error `invalid_encryption`. Their deliveries fail without retrying and they can't be
rotated or activated, but tenants can replace their URL or delete them. `cms:webhooks:reencrypt` skips them and
exits with an error, and `cms:webhooks:check` warns about them without failing the deploy. Both list
the affected tenants with the most subscriptions first, e.g. `Affected tenants: shop-a (2), shop-b (1)`,
so you know whom to notify. Add the old key to `APP_PREVIOUS_KEYS` again and rerun
`cms:webhooks:reencrypt` to recover them.

Subscriptions which are changed in the admin panel while the command runs are skipped and
reported with their tenants too, so rerun it afterwards.

Add `--tenant=<id>` to re-encrypt the subscriptions of a single tenant. An empty value is rejected
instead of re-encrypting all tenants, so an unset variable in a deploy script doesn't widen the run.

Tenant offboarding can remove all subscriptions with an exact tenant ID:

```bash
php artisan cms:webhooks:purge tenant-id
```

Like the changes in the admin panel, purging is logged as a `cms.webhook` warning, here with the
action `purged` and the number of deleted subscriptions. If the tenant's subscriptions are being
changed at the same time, the command fails without deleting anything and can be run again.

## Admin translations

The webhook panel owns its gettext sources in `admin/i18n`. Keep UI strings in the `webhooks`
context with `$pgettext('webhooks', ...)`, then update and build them from this package:

```bash
cd admin
npm run gettext:extract
npm run build
```

The PO sources live in `admin/i18n`. Compilation writes the runtime JSON catalogs to
the ignored staging directory `admin/public/i18n`, and Vite publishes them below
`admin/dist/i18n` together with the panel bundle. Commit the PO sources and distributable catalogs,
not the staging copies.
