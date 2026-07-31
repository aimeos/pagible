# Pagible Cashier Mollie

Mollie provider package for
[Pagible Cashier](https://github.com/aimeos/pagible-cashier).

```bash
composer require aimeos/pagible-cashier-mollie
php artisan cms:install:cashier
```

Add `Aimeos\Cms\Concerns\CashierAccess` and `Laravel\Cashier\Billable` to the
application user model and configure the API key:

```dotenv
MOLLIE_KEY=test_...
```

Reload cached configuration, then verify the local setup and review the printed
scheduler and webhook checklist:

```bash
php artisan config:clear
php artisan cms:cashier:check
```

Use a live key in production. The package derives an opaque
segment for all three Mollie webhook paths from `APP_KEY` before Cashier
registers them. Rotating `APP_KEY` therefore changes those paths.
Keep the resulting Cashier Mollie webhook routes public.

Run Laravel's scheduler continuously. The adapter runs Mollie's `cashier:run`
command every five minutes to create subscription renewal payments. Every
webhook retrieves authoritative remote payment state before Cashier processes
it and returns success only after the corresponding `users.access` mutation
commits. An access or provider failure therefore returns a non-success response
so Mollie can retry. Subscription creation shares Cashier's local database
transaction; paid renewals are projected before their webhook is acknowledged.

For both subscriptions and one-time payments, the pricing **Payment reference**
is the decimal amount with exactly two digits, such as `19.00`, and **Currency**
is its three-letter uppercase code. Subscriptions additionally require a
**Billing interval** from 1 to 365 days; one-time payments may omit it or use `0`.

Provider webhook retries are the only recovery mechanism for failed delivery.
Monitor the endpoint and exhausted Mollie webhook deliveries because there is
no separate reconciliation job. The package adds source lookup and order-item
indexes to Cashier's existing payment and order tables; it adds no billing
table of its own.

Mollie subscription plan names contain a signed pricing snapshot. When rotating
`APP_KEY`, keep the previous value in Laravel's `APP_PREVIOUS_KEYS` setting
until all existing subscriptions have ended and Mollie no longer retries
webhooks issued with the previous opaque URL. Previous keys keep both signed
plans and their webhook route aliases valid during that window.

The package owns the Mollie driver, signed plan repository, migrations,
synchronous lifecycle listeners, and billing schedule. It requires Cashier
Mollie and conflicts with all other Pagible and upstream Cashier providers.

See the [Pagible Cashier guide](https://github.com/aimeos/pagible-cashier) for
the access catalog, pricing element, and restricted-page workflow.
