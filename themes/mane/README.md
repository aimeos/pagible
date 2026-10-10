# Mane Theme

Light, editorial design for hair salons, colour studios and barbers with ivory, espresso and clay rose tones, serif display headings, arched image frames and pill buttons for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-mane
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Minimal salon luxury with a blush announcement bar, a translucent sticky header with a pill "Book now" button, bright full width image heroes with dark text and no dimming, fine clay lines above section headings, price lists with dotted leaders, a before and after slider, borderless team portraits and an espresso footer with the address, opening hours and the cancellation notice
- **Colors**: Ivory (#F7F2EC), espresso (#1F1A17), clay rose (#9A5A43) and blush (#E9D5CB)
- **Typography**: Serif display headings (Cormorant Garamond, Baskerville, Georgia) with italic accents, system sans-serif body and uppercase, letter-spaced labels
- **Borders**: Soft `1rem` corners, arched images, pill buttons and badges, hairline borders instead of heavy shadows
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Landing, services, colour, bridal, team, new clients, gift cards and visit pages |
| `docs` | Documentation with sidebar navigation |
| `blog` | Hair tips posts listed by the blog element |

## Salon Details

The **Salon** settings in the page config add a salon business JSON-LD to every page below the configured page:

| Field | Description |
|-------|-------------|
| Business type | schema.org type: `HairSalon`, `BarberShop`, `BeautySalon`, `DaySpa` or `LocalBusiness` |
| Name, address, telephone, email | Salon details shown in the footer, the telephone is also used by the action bar |
| Announcement | Short note shown in the top bar of every page |
| Booking link | Appointment booking page, shown as "Book now" in the header and rendered as `ReserveAction` |
| Price list link | Services and prices page, rendered as `hasOfferCatalog` |
| Price range | Price level, e.g. `€€€` |
| Notice | Short note in the footer, e.g. the cancellation policy |
| Action bar | Call and booking buttons at the bottom of the screen on phones |
| Opening hours | Opening and closing time per day of the week |

## Price Lists

Pricing elements double as salon price lists: list items ending with a bold price (`- Cut & finish **78**`) are rendered with a dotted leader to the right-aligned price, and inline code (`` `bestseller` ``) becomes a small pill. Cards in four columns become borderless team portraits with arched images.

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Default | Description |
|----------|---------|-------------|
| `--pico-color` | `#1F1A17` | Body text color |
| `--pico-background-color` | `#F7F2EC` | Page background |
| `--pico-contrast` | `#1F1A17` | Headings, filled buttons and the footer |
| `--pico-primary` | `#9A5A43` | Primary accent (clay rose) |
| `--pico-secondary` | `#E9D5CB` | Secondary accent (blush) |
| `--pico-border-radius` | `1rem` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=mane
```

## Structure

```
├── composer.json
├── schema.json          Theme and salon configuration schema
├── database/seeders/    ManeDemo seeder
├── lang/                Frontend translations
├── src/
│   └── ManeServiceProvider.php
├── public/              CSS and admin translations published to public/vendor/cms/mane/
│   ├── cms.css          Base styles, header, footer and action bar
│   ├── i18n/            Admin translations of the config fields
│   └── *.css            Content element and layout styles
├── tests/
└── views/
    └── layouts/
        └── main.blade.php
```

## License

MIT
