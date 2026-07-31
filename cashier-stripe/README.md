# Pagible Cashier Stripe

Stripe provider package for
[Pagible Cashier](https://github.com/aimeos/pagible-cashier).

```bash
composer require aimeos/pagible-cashier-stripe
```

Add `Aimeos\Cms\Concerns\CashierAccess` and `Laravel\Cashier\Billable` to the
application user model. Configure the public application URL and Stripe API
credentials first:

```dotenv
APP_URL=https://example.com
STRIPE_KEY=pk_...
STRIPE_SECRET=sk_...
```

Then run the installer. It publishes and runs the migrations, validates the
Stripe prerequisites, displays the registered webhook URL, and interactively
offers to create the endpoint with **yes** as the default:

```bash
php artisan cms:install:cashier
```

For an automated deployment, webhook creation is skipped unless it is requested
explicitly. `--webhook` creates it without a second prompt:

```bash
php artisan cms:install:cashier --webhook --no-interaction
```

The adapter adds all Pagible payment, subscription, refund, and dispute events
to Cashier's webhook event list before this command creates the endpoint.
Retrieve its signing secret from the Stripe dashboard, configure it, and reload
the application's configuration:

```dotenv
STRIPE_WEBHOOK_SECRET=whsec_...
```

Reload cached configuration, then verify the local setup:

```bash
php artisan config:clear
php artisan cms:cashier:check
```

The installer and readiness check finish with a **Developer next steps** section
containing failed local checks and the remaining Stripe dashboard and
purchase-flow tasks.

Checkout and webhook processing remain unavailable until
`STRIPE_WEBHOOK_SECRET` is configured. Pricing **Payment reference** values are
Stripe price IDs (`price_...`) and **Payment type** must match whether each Stripe
price is recurring or one-time.
Webhook handling and its `users.access` mutation are synchronous. A processing
failure returns a non-success response so Stripe can retry the event. Monitor
the endpoint and exhausted Stripe webhook deliveries because there is no
separate reconciliation job.

The package owns the Stripe driver and lifecycle listeners, requires Laravel
Cashier Stripe, and conflicts with all other Pagible and upstream Cashier
providers.

See the [Pagible Cashier guide](https://github.com/aimeos/pagible-cashier) for
the access catalog, pricing element, and restricted-page workflow.
