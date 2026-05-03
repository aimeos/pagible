# Pagible Cashier

Payment integration for [Pagible CMS](https://pagible.com) via Laravel Cashier. Supports Stripe, Paddle, and Mollie payment providers.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-cashier
```

Run the interactive installer to configure your payment provider:

```bash
php artisan cms:install:cashier
```

The installer will:

- Publish the configuration file
- Prompt for your payment provider (Stripe, Paddle, or Mollie)
- Collect API credentials and write them to `.env`
- Install the required Cashier package
- Add the `Billable` trait to your User model
- Print webhook setup instructions

## Configuration

After installation, the configuration is available in `config/cms/cashier.php`:

```php
return [
    'provider' => env('CMS_CASHIER_PROVIDER'),       // 'stripe', 'paddle', or 'mollie'
    'products' => [
        'price_xxx' => ['once' => true, 'action' => 'course_access', 'course_id' => '123'],
        'price_yyy' => ['action' => 'premium'],
    ],
];
```

The `products` array maps payment provider price IDs to server-side metadata. Set `once` to `true` for one-time payments; omit it for subscriptions. Only price IDs listed here are accepted by the checkout endpoint. The full product array is forwarded to the payment provider as metadata and available in webhook events.

Each provider requires its own Cashier package:

| Provider | Package |
|----------|---------|
| Stripe | `laravel/cashier` |
| Paddle | `laravel/cashier-paddle` |
| Mollie | `mollie/laravel-cashier-mollie` |

### Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `CMS_CASHIER_PROVIDER` | Payment provider: `stripe`, `paddle`, or `mollie` | Yes |
| `STRIPE_KEY` | Stripe publishable key | Stripe |
| `STRIPE_SECRET` | Stripe secret key | Stripe |
| `STRIPE_WEBHOOK_SECRET` | Stripe webhook signing secret | Stripe |
| `PADDLE_SELLER_ID` | Paddle seller ID | Paddle |
| `PADDLE_AUTH_CODE` | Paddle API auth code | Paddle |
| `PADDLE_RETAIN_KEY` | Paddle Retain key | No |
| `PADDLE_SANDBOX` | Use Paddle sandbox: `true` or `false` | No |
| `PADDLE_WEBHOOK_SECRET` | Paddle webhook secret | Paddle |
| `MOLLIE_KEY` | Mollie API key | Mollie |

## Checkout Form

The checkout form only needs the price ID. Payment type and metadata are resolved server-side from the `products` config:

```html
<form method="POST" action="/cmsapi/cashier">
    @csrf
    <input type="hidden" name="priceid" value="price_xxx">
    <button type="submit">Buy Course</button>
</form>
```

Guest users are automatically redirected to the login page. After login or registration, they are redirected back to complete the checkout.

## Webhook Events

Each payment provider confirms payments via webhooks. Listen to these events in a service provider to perform actions on successful payments. Use the metadata from the `products` config to dispatch different actions per product.

### Stripe

```php
use Laravel\Cashier\Events\WebhookHandled;

Event::listen(WebhookHandled::class, function ($event) {
    $payload = $event->payload;

    // One-time payment completed
    if (($payload['type'] ?? '') === 'checkout.session.completed') {
        $session = $payload['data']['object'] ?? [];
        $userId = $session['customer'] ?? null;
        $items = \Laravel\Cashier\Cashier::stripe()->checkout->sessions->allLineItems($session['id'] ?? '');
        $priceId = $items->data[0]->price->id ?? null;
        $product = config('cms.cashier.products')[$priceId] ?? [];

        if ($userId && $user = User::where('stripe_id', $userId)->first()) {
            // grant access based on $product config
        }
    }

    // Recurring payment succeeded
    if (($payload['type'] ?? '') === 'invoice.payment_succeeded') {
        $userId = $payload['data']['object']['customer'] ?? null;
        $priceId = $payload['data']['object']['lines']['data'][0]['price']['id'] ?? null;
        $product = config('cms.cashier.products')[$priceId] ?? [];

        if ($userId && $user = User::where('stripe_id', $userId)->first()) {
            // grant access based on $product config
        }
    }
});
```

### Paddle

```php
use Laravel\Paddle\Events\WebhookHandled;

Event::listen(WebhookHandled::class, function ($event) {
    $payload = $event->payload;

    // One-time payment completed
    if (($payload['event_type'] ?? '') === 'transaction.completed') {
        $data = $payload['data'] ?? [];
        $userId = $data['customer_id'] ?? null;
        $priceId = $data['items'][0]['price']['id'] ?? null;
        $product = config('cms.cashier.products')[$priceId] ?? [];

        if ($userId && $user = User::where('paddle_id', $userId)->first()) {
            // grant access based on $product config
        }
    }

    // Recurring subscription activated
    if (($payload['event_type'] ?? '') === 'subscription.activated') {
        $data = $payload['data'] ?? [];
        $userId = $data['customer_id'] ?? null;
        $priceId = $data['items'][0]['price']['id'] ?? null;
        $product = config('cms.cashier.products')[$priceId] ?? [];

        if ($userId && $user = User::where('paddle_id', $userId)->first()) {
            // grant access based on $product config
        }
    }
});
```

### Mollie

```php
use Laravel\CashierMollie\Events\OrderPaymentPaid;
use Laravel\CashierMollie\Events\FirstPaymentPaid;

// One-time payment completed
Event::listen(OrderPaymentPaid::class, function ($event) {
    if ($user = $event->order?->owner) {
        $priceId = $event->order->orderItems->first()?->orderable_id;
        $product = config('cms.cashier.products')[$priceId] ?? [];

        // grant access based on $product config
    }
});

// First recurring payment completed
Event::listen(FirstPaymentPaid::class, function ($event) {
    if ($user = $event->payment?->owner) {
        $priceId = $event->payment->orderItems->first()?->orderable_id;
        $product = config('cms.cashier.products')[$priceId] ?? [];

        // grant access based on $product config
    }
});
```

## License

LGPL-3.0-only
