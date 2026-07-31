# Pagible Cashier

Payment-backed frontend access for Pagible CMS through Laravel Cashier. Choose
one provider package per application:

## Installation

```bash
composer require aimeos/pagible-cashier-stripe
# or: aimeos/pagible-cashier-paddle
# or: aimeos/pagible-cashier-mollie

php artisan cms:install:cashier
php artisan migrate
```

`cms:install:cashier` runs
`vendor:publish --tag=cashier-migrations` for the installed upstream Cashier and
adapter migrations. Add `Aimeos\Cms\Concerns\CashierAccess` beside that
provider's `Billable` trait on the application user model. The trait reserves,
guards, casts, and hides the `users.access` attribute. Do not add `access` to the
user model's `$fillable` list or accept it in profile/account input; only Pagible
Cashier may write it.

Each provider package installs the shared Pagible integration and exactly one
upstream Cashier package. Composer conflicts prevent another Pagible adapter or
upstream Cashier provider from being installed in the same application. The
installed package is the provider configuration; there is no
`CMS_CASHIER_PROVIDER` setting that can drift from the dependencies.

Each adapter owns its provider, webhook listeners, service provider, migrations,
and assets. Laravel discovers that service provider, which binds the
application's single `CashierProvider`. The shared installation command publishes
the upstream Cashier migrations. Provider-specific model, credential, webhook,
and scheduler steps remain explicit because a package must not rewrite the host
model or environment file.

## Pricing products

Products live in each tenant's published `pricing` content, not application
configuration. This keeps products tenant-editable in SaaS installations. A
package owns one frontend access role and contains up to five prices. The
installed adapter supplies the provider for every price:

```json
{
  "id": "professional",
  "name": "Professional",
  "access": "frontend.pro",
  "url": "/account",
  "prices": [
    {
      "id": "monthly",
      "reference": "price_123",
      "kind": "subscription",
      "currency": "EUR",
      "amount": 19.00,
      "unit": "month"
    },
    {
      "id": "lifetime",
      "reference": "price_456",
      "kind": "once",
      "currency": "EUR",
      "amount": 199.00,
      "unit": "once"
    }
  ]
}
```

Package and price identifiers are generated automatically and stored with the
pricing content, but aren't shown as editable fields. Copying a package creates
new package and price identifiers; cutting and pasting preserves them.

The frontend derives its toggle captions from the distinct non-empty `unit`
values and selects matching prices across packages. Packages without the
selected unit keep their first price.

For packages with `access`, `url` is the local page shown after checkout. Without
`access`, it is rendered directly as a page, fragment, or contact link and the
package may omit `reference`, `kind`, and `currency`; those fields remain
mandatory at runtime for packages that start checkout.

Pricing uses the ordinary CMS permissions. Editors with `page:save` can control
the access role and prices of inline pricing content; shared pricing elements
use `element:save`. Publication and scheduling continue to use the normal page
and element publication permissions. Checkout accepts an access role only when
it exists in the current tenant's `Access` catalog.

The rendered form posts only the published page, pricing element, package, and
price identifiers. The server resolves the payment reference, access role,
payment kind, and target URL from published content, obtains the provider
from the installed adapter, then signs the metadata sent to Cashier.

For Stripe and Paddle, `reference` is the provider-managed price ID. For Mollie,
it is the decimal amount (for example `"19.00"`), accompanied by `currency` and,
for subscriptions, `interval` as an integer number of days from 1 to 365.
One-time prices may leave the field empty, use `0`, or omit it. The package name
becomes the invoice description. Pagible converts the day count to Mollie's interval format and
signs these values into the plan name stored in Cashier's own subscription row.
It supplies a plan repository at runtime. This avoids
`cashier_plans.php` and a Pagible product table. Pagible also schedules Mollie's
`cashier:run` command every five minutes; the application scheduler still needs
to be running. Mollie reads authoritative remote payment state before its
Cashier webhook controller continues and acknowledges the request only after
the `users.access` mutation commits. Projection failures therefore remain
retryable. Webhooks embed refund and chargeback collections and order removals
use the newest completed refund or active chargeback timestamp.
If Mollie supplies an adverse aggregate without a usable event timestamp, the
webhook fails for retry instead of substituting local receipt time.
Source lookup indexes keep webhook resolution bounded.
The Mollie adapter adds its required customer and mandate columns to `users`;
the shared integration knows nothing about them.

Stripe redirects to its hosted checkout. Paddle creates the transaction and
binds the signed CMS metadata on the server, then exposes only the transaction
ID to the inline Paddle.js checkout. Recurring checkouts use a tenant-keyed
`subscription_type`, so local subscriptions remain distinct without trusting
browser-submitted product data.

## Access state

Cashier's verified webhook events maintain the hidden JSON `users.access`
projection:

```json
{
  "acme|stripe|sub_123": {
    "role": "frontend.pro",
    "end": 1787745600000,
    "at": 1787745600000
  },
  "acme|stripe|pi_456": {
    "role": "frontend.course",
    "end": null,
    "at": 1785157200000
  },
  "acme|paddle|txn_789": {
    "role": null,
    "end": null,
    "at": 1785243600000
  }
}
```

`users.access` is a package-reserved authorization projection, not application
profile data. Application code must not mass assign, `forceFill()`, or update the
column directly.

Each top-level key combines the tenant ID, provider, and payment source
identifier using `|` separators. Provider and source identifiers cannot contain
`|`; any preceding separators remain part of the tenant ID.
Subscription sources always have their verified paid-through `end` as a Unix
timestamp in milliseconds. Successful one-time payments use `null`. `at` is the
provider event time in the same format. Revoked sources remain as role-less
tombstones so a delayed older webhook cannot restore access. A role remains
active in its tenant while any source entry is permanent or has a future end.

Checkout rejects new starts when the current projection already contains 128
payment sources or reaches 128 KiB. This is an admission bound rather than a
distributed reservation: concurrent starts can observe the same projection,
and already-created provider checkouts and verified webhook mutations are still
honored so a successful payment is never left without its access update.

Provider-confirmed paid invoices, transactions, and orders renew the source.
Status-only subscription updates never restore access. Failed renewals and
scheduled cancellations retain access through the already paid period.
Provider-confirmed subscription deletion, immediate cancellation, full refund,
or chargeback tombstones the affected source. Repeated and out-of-order webhook
delivery is idempotent. Local Cashier ownership may revoke an existing source,
but only source-bound signed CMS metadata may create a tombstone for an unknown
source.

Applications may schedule end-of-period cancellation through
`DELETE /cmsapi/cashier/{subscription}`. The active driver and authenticated
user's Cashier relationship scope ownership; the verified provider lifecycle
event remains authoritative for access removal.

## Frontend authorization

Payment roles are added to the existing Pagible `Access` grants. They do not
replace a custom Bouncer, Laratrust, Spatie, or `Access::using()` resolver.
Frontend page rendering reads no subscription table: the authenticated user
query supplies `users.access`. `cms.access.limit` is the strict maximum
number of distinct access values in each tenant's complete catalog. Pagible
rejects oversized catalogs and additions beyond that limit. Page rendering,
navigation, pricing checkout, and JSON:API all use the same database-side
access predicate. An effective `grants` callback avoids Gate calls for each
catalog value; its results are filtered through the bounded catalog and
memoized per user and tenant for the request.

Webhook handling and the corresponding `users.access` mutation are synchronous.
An exception prevents a successful response so the payment provider can retry
the event. Provider retries are the only recovery mechanism for failed webhook
delivery; monitor webhook endpoints and exhausted deliveries. If every retry
for a one-time refund or chargeback is exhausted, permanent access may require
manual revocation. Mollie installations must still run Laravel's scheduler for
Cashier Mollie's normal `cashier:run` subscription billing command.

## License

MIT
