# Pagible Cashier Stripe

Stripe provider package for
[Pagible Cashier](https://github.com/aimeos/pagible-cashier).

```bash
composer require aimeos/pagible-cashier-stripe
php artisan cms:install:cashier
php artisan migrate
```

Add `Aimeos\Cms\Concerns\CashierAccess` and `Laravel\Cashier\Billable` to the
application user model, configure `STRIPE_KEY`, `STRIPE_SECRET`, and
`STRIPE_WEBHOOK_SECRET`, then register the verified webhook using Laravel
Cashier's `cashier:webhook` command.

```bash
php artisan cashier:webhook
```

The adapter adds its checkout, refund, and dispute events to Cashier's webhook
configuration before the command registers the endpoint. Checkout and webhook
processing remain unavailable until `STRIPE_WEBHOOK_SECRET` is configured.
Webhook handling and its `users.access` mutation are synchronous. A processing
failure returns a non-success response so Stripe can retry the event. Monitor
the endpoint and exhausted Stripe webhook deliveries because there is no
separate reconciliation job.

The package owns the Stripe driver and lifecycle listeners, requires Laravel
Cashier Stripe, and conflicts with all other Pagible and upstream Cashier
providers.
