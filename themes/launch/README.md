# Launch Theme

Modern SaaS and startup landing page design for [Pagible CMS](https://pagible.com) with automatic light and dark mode, indigo glow accents, gradient headlines, product screenshots in browser frames, bento feature grids, pricing with a monthly/yearly toggle and Cashier checkout, a changelog timeline, FAQ and testimonial walls.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-launch
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Clean product landing pages in the style of Linear, Vercel, Stripe and Resend. A gradient announcement bar, a blurred sticky header with "Sign in" and a "Get started" pill, centered heroes on a faded grid with a pill eyebrow, gradient headline and a product screenshot glowing below, grayscale logo clouds, bento feature cards with gradient icon tiles, split feature sections with framed screenshots, gradient figures, a masonry testimonial wall, pricing cards with a highlighted plan, a single-column FAQ, a changelog with sticky dates, a dark call-to-action panel and a minimal footer with a system status pill
- **Colors**: Zinc neutrals (#09090B, #3F3F46, #71717A, #FAFAFA), indigo (#4F46E5, #6366F1, #A5B4FC in dark mode), violet (#7C3AED, #A855F7) and cyan for gradients, green (#22C55E) for the status dot
- **Light and dark mode**: Follows the system setting and the `data-theme` attribute
- **Typography**: System sans-serif (Inter or Geist if installed) with tight letter-spacing on headings, monospace for dates and code
- **Borders**: `0.75rem` corners, pill-shaped buttons, toggles and badges, thin borders instead of heavy shadows
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Landing, features, pricing, customers, changelog, about and contact pages |
| `docs` | Documentation with sidebar navigation |
| `blog` | Blog posts listed by the blog element |

## Product Details

The **Product** settings in the page config apply to the configured page and all pages below it:

| Field | Description |
|-------|-------------|
| Name, category, description | Rendered as `SoftwareApplication` JSON-LD |
| Announcement, announcement link | Gradient bar above the header with a "New" tag |
| Sign in link | Login of your app, shown as "Sign in" in the header |
| Sign up link | Sign up or pricing page, shown as "Get started" button |
| Status page link | Shown as "System status" pill with a green dot in the footer |
| Starting price, currency | Rendered as `Offer` |
| Rating, reviews | Rendered as `AggregateRating` |
| Notice | Short note in the footer, e.g. certifications |

## Elements

- **Hero**: the first image is shown centered below the text in a browser frame; use a wide screenshot (16:10) with a dark UI
- **Cards**: `cards` in three columns form a bento grid on wide screens (the 1st and 4th card of every four span two columns); SVG images become gradient icon tiles, `badges` render a grayscale logo cloud and `figures` gradient numbers
- **Pricing**: different price `unit` values (e.g. "per month" and "per year") create the toggle, the highlighted plan gets the primary button and the badge
- **Timeline**: the `vertical` layout is a changelog with sticky dates as labels, `horizontal` shows numbered steps
- **Testimonials**: masonry wall with the first quote emphasized
- **Image text**: `grid-start`/`grid-end` show framed screenshots next to the text, list items get check icons

## Pricing and Checkout

Plans of the `pricing` element start a checkout when one of the Pagible Cashier packages is installed:

```bash
composer require aimeos/pagible-cashier-stripe   # or -paddle, -mollie
php artisan cms:install:cashier
php artisan cms:cashier:check
```

1. Add the `Aimeos\Cms\Concerns\CashierAccess` trait and the provider's `Billable` trait to your user model
2. Register the roles you sell in a service provider, e.g. `Access::using( fn() => ['relay-pro', 'relay-team'] )`
3. In each paid plan, set an ID, the **Frontend access role**, the **Target page** shown after the payment and for every price an ID, the payment type, the billing interval in days, the currency and the price ID of your payment provider as **Payment reference**
4. Protect the pages you sell with the same access role

Plans without an access role (e.g. free or enterprise plans) show a normal link button to their target page. The payment provider's webhooks grant and revoke the access role.

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Light | Dark | Description |
|----------|-------|------|-------------|
| `--pico-color` | `#3F3F46` | `#D4D4D8` | Body text color |
| `--pico-background-color` | `#FFFFFF` | `#09090B` | Page background |
| `--pico-contrast` | `#09090B` | `#FAFAFA` | Headings and "Get started" button |
| `--pico-primary` | `#4F46E5` | `#A5B4FC` | Links and accents (indigo) |
| `--pico-primary-background` | `#4F46E5` | `#4F46E5` | Primary buttons |
| `--pico-secondary` | `#7C3AED` | `#C4B5FD` | Secondary accent (violet) |
| `--pico-border-radius` | `0.75rem` | `0.75rem` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=launch
```

The demo builds a website for "Relay", a fictional uptime monitoring SaaS, with SVG product screenshots, features, pricing, customers, changelog, blog, about, contact, sign up and post-checkout welcome pages. If a Cashier package is installed, the Pro and Team plans use the `relay-pro` and `relay-team` access roles; replace the `price_*` payment references with your provider's price IDs.

## Structure

```
├── composer.json
├── schema.json          Theme and product configuration schema
├── database/seeders/    LaunchDemo seeder
├── lang/                Frontend translations
├── src/
│   └── LaunchServiceProvider.php
├── public/              CSS and admin translations published to public/vendor/cms/launch/
│   ├── cms.css          Base styles, light and dark tokens, header and footer
│   ├── i18n/            Admin translations of the config fields
│   └── *.css            Content element and layout styles
├── tests/
└── views/
    └── layouts/
        └── main.blade.php
```

## License

MIT
