# Pagible Cashier Paddle

Paddle provider package for
[Pagible Cashier](https://github.com/aimeos/pagible-cashier).

```bash
composer require aimeos/pagible-cashier-paddle
php artisan cms:install:cashier
```

Add `Aimeos\Cms\Concerns\CashierAccess` and `Laravel\Paddle\Billable` to the
application user model and configure the provider credentials:

```dotenv
PADDLE_CLIENT_SIDE_TOKEN=...
PADDLE_API_KEY=...
# Enable for local and staging sandbox accounts:
PADDLE_SANDBOX=true
```

Create a Paddle notification destination for the application's public
`/paddle/webhook` URL. Configure it to send Cashier's normal event set and
ensure it includes `transaction.completed`, `subscription.created`,
`subscription.canceled`, `adjustment.created`, and `adjustment.updated`. The two
adjustment events are required for refund and chargeback revocation.

Copy that destination's endpoint secret into the application configuration:

```dotenv
PADDLE_WEBHOOK_SECRET=...
```

Reload cached configuration, then verify the local setup and review the printed
Paddle dashboard checklist:

```bash
php artisan config:clear
php artisan cms:cashier:check
```

Pricing **Payment reference** values are Paddle price IDs (`pri_...`) and
**Payment type** must match whether each Paddle price is recurring or one-time.

Webhook handling and its `users.access` mutation are synchronous. A processing
failure returns a non-success response so Paddle can retry the event. Monitor
the endpoint and exhausted Paddle webhook deliveries because there is no
separate reconciliation job.

The package owns the Paddle driver, checkout view, and lifecycle listeners,
creates checkout transactions and binds their CMS metadata server-side, requires
Laravel Cashier Paddle, and conflicts with all other Pagible and upstream
Cashier providers. Checkout and webhook processing remain unavailable until
`PADDLE_WEBHOOK_SECRET` is configured.

See the [Pagible Cashier guide](https://github.com/aimeos/pagible-cashier) for
the access catalog, pricing element, and restricted-page workflow.
