# Pagible CMS webhooks

This optional package sends signed, tenant-scoped notifications for committed Pagible CMS lifecycle events. It uses the Laravel queue and does not add an event bus, outbox or webhook dependency to the core package.

## Installation

```bash
composer require aimeos/pagible-webhooks
php artisan cms:install:webhooks
php artisan migrate
```

For production, configure an asynchronous queue connection and run a dedicated worker for the `cms-webhooks` queue. The `sync` driver is supported for immediate delivery without a worker; the package refuses to start delivery with the `null` queue driver.

```dotenv
CMS_WEBHOOKS_ENABLED=true
CMS_WEBHOOKS_QUEUE_CONNECTION=redis
CMS_WEBHOOKS_QUEUE=cms-webhooks
```

```bash
php artisan queue:work redis --queue=cms-webhooks
```

Run enough workers for the expected delivery rate: each destination is an independent job and a
slow endpoint can occupy a worker for up to the configured webhook timeout. Monitor queue depth,
oldest-job age and failed-job rate, and scale the dedicated workers when scheduled publications
create bursts. Configure the queue connection's `retry_after` or broker visibility timeout several
seconds above `cms.webhooks.queue.timeout` so a running delivery is not released twice.

Pagible does not impose a separate outbound webhook payload-size limit. Core lifecycle events carry
bounded references, but the selected queue backend and receiver must accept the resulting encrypted
queue message and HTTP request sizes.

Provision subscriptions through the CMS admin panel or GraphQL. New and replaced subscriptions are inactive. Store the secret returned by the create, replace or rotate operation; it is never returned by ordinary queries.

## Delivery contract

The package emits exact event names only:

- `page.published`, `page.moved`, `page.deleted`, `page.restored`, `page.purged`
- `element.published`, `element.deleted`, `element.restored`, `element.purged`
- `file.published`, `file.deleted`, `file.restored`, `file.purged`

Each request contains a bounded event reference and these headers:

- `X-Cms-Event`
- `X-Cms-Tenant`
- `X-Cms-Delivery`
- `X-Cms-Timestamp`
- `X-Cms-Signature: v2=<hex hmac>`

Verify every request before parsing or processing its body:

1. Read `X-Cms-Event`, `X-Cms-Tenant`, `X-Cms-Timestamp`, `X-Cms-Delivery` and
   `X-Cms-Signature`, rejecting missing or malformed values. The timestamp is a Unix timestamp in
   seconds and the signature has the form `v2=<64 lowercase hexadecimal characters>`.
2. Reject `X-Cms-Timestamp` when it is outside the receiver's allowed clock-skew window. A five
   minute window is a reasonable default. Use the signed header value, not the informational
   timestamp in the JSON payload.
3. Build the canonical signature payload below. Header names are lowercase, values are used exactly
   as received after normal HTTP whitespace handling, and the empty line separates the headers from
   the body. The body must be the exact bytes received, before JSON decoding or re-encoding.

   ```text
   v2
   x-cms-event:<X-Cms-Event>
   x-cms-tenant:<X-Cms-Tenant>
   x-cms-delivery:<X-Cms-Delivery>
   x-cms-timestamp:<X-Cms-Timestamp>

   <raw-request-body>
   ```

4. Compute `v2=` followed by the hexadecimal HMAC-SHA256 of that canonical payload using the
   subscription secret.
5. Compare the complete expected and received signatures using a constant-time comparison such as
   PHP's `hash_equals()`.
6. Atomically claim `X-Cms-Delivery` before applying side effects. Treat an already claimed value as
   a successful duplicate and return a 2xx response. Retain claims for at least the sender's maximum
   delivery age plus the accepted clock skew: at least 24 hours and five minutes with the defaults.

Retries retain the same delivery ID but receive a fresh signed timestamp.
Signature version `v2` replaces `v1`; update receivers before deploying a sender with this version.

Subscription health is updated after terminal delivery outcomes. `failures` counts consecutive
failed deliveries and is reset to zero by the next successful 2xx response. `last_success_at`
records that response, while `last_error` is cleared. Concurrent deliveries update this state in
database completion order. Expired, disabled and revision-stale jobs never change health state.
Destination replacement and secret rotation clear the health state for the new subscription revision.

Single-item notifications contain the content and version IDs. Page notifications also include the route context carried by the committed CMS event when available:

```json
{
  "event": "page.published",
  "tenant_id": "tenant",
  "timestamp": "2026-09-14T12:00:00Z",
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

HTTPS on port 443 is the default. Redirects and environment proxies are disabled, DNS is resolved immediately before every call, and the selected public address is pinned into cURL. Loopback, link-local, transition and reserved address ranges remain blocked.

Exact host exceptions belong in `config/cms/webhooks.php`. A host may opt into HTTP, alternate ports, private CIDRs or a custom CA bundle. Global `deny_cidrs` and hard denials always win. Keep these exceptions narrow and review them as infrastructure policy.

Destinations, secrets, queued jobs and stored last errors use application encryption. During `APP_KEY` rotation, retain old keys in Laravel's `APP_PREVIOUS_KEYS`, stop or drain the webhook worker, and run:

```bash
php artisan cms:webhooks:reencrypt
```

Tenant offboarding can remove all subscriptions with an exact tenant ID:

```bash
php artisan cms:webhooks:purge tenant-id
```

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
