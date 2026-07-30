# Pagible Cashier Paddle

Paddle provider package for
[Pagible Cashier](https://github.com/aimeos/pagible-cashier).

```bash
composer require aimeos/pagible-cashier-paddle
php artisan cms:install:cashier
php artisan migrate
```

Add `Aimeos\Cms\Concerns\CashierAccess` and `Laravel\Paddle\Billable` to the
application user model and configure `PADDLE_CLIENT_SIDE_TOKEN`,
`PADDLE_API_KEY`, and `PADDLE_WEBHOOK_SECRET`. Configure Paddle to send verified
webhooks to `/paddle/webhook` for `transaction.completed`,
`subscription.created`, `subscription.canceled`, `adjustment.created`, and
`adjustment.updated`. The two adjustment events are required for refund and
chargeback revocation.

Webhook handling and its `users.access` mutation are synchronous. A processing
failure returns a non-success response so Paddle can retry the event. Monitor
the endpoint and exhausted Paddle webhook deliveries because there is no
separate reconciliation job.

The package owns the Paddle driver, checkout view, and lifecycle listeners,
creates checkout transactions and binds their CMS metadata server-side, requires
Laravel Cashier Paddle, and conflicts with all other Pagible and upstream
Cashier providers. Checkout and webhook processing remain unavailable until
`PADDLE_WEBHOOK_SECRET` is configured.
