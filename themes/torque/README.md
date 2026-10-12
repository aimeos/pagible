# Torque Theme

Workshop-grade design for car repair shops, garages, tyre centres and used car dealers with graphite, concrete and signal red tones, condensed uppercase headings, hazard stripes, vehicle spec strips and service coupons for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-torque
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Professional workshop look with a dark top bar for the announcement and the 24/7 breakdown number, a white sticky header with a hazard stripe, the service phone number and a slanted red "Book service" button, dark heroes with diagonal stripes and a slanted photo frame, hazard bars before section headings, odometer-style figures, inspection-check badges, vehicle cards with spec strips and monthly rates, dashed service coupons, price lists with dashed leaders, a red call to action band and a near-black footer with address, opening hours and warranty notice
- **Colors**: Concrete (#F3F4F2), graphite (#16191D, #2B3036), signal red (#C8102E) and amber (#F5A400) for the hazard stripes and ratings
- **Typography**: Condensed uppercase display headings (Bahnschrift, DIN Alternate, Roboto Condensed, Arial Narrow), system sans-serif body and monospace for prices, durations, labels and specs
- **Borders**: Tight `0.25rem` corners, square avatars, slanted buttons, badges and image frames, thick top borders on cards
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Landing, services, price list, used cars, offers, about, careers and contact pages |
| `docs` | Documentation with sidebar navigation |
| `blog` | Workshop tips listed by the blog element |

## Garage Details

The **Garage** settings in the page config add an automotive business JSON-LD to every page below the configured page:

| Field | Description |
|-------|-------------|
| Business type | schema.org type: `AutoRepair`, `AutoDealer`, `AutoBodyShop`, `TireShop`, `MotorcycleRepair` or `AutomotiveBusiness` |
| Name, address, telephone, email | Garage details shown in the footer, the telephone is also shown in the header and used by the action bar |
| Breakdown service | Phone number of the breakdown and towing service, shown in the top bar |
| Announcement | Short note shown in the top bar of every page |
| Booking link | Service booking page, shown as "Book service" in the header and rendered as `ReserveAction` |
| Price list link | Services and prices page, rendered as `hasOfferCatalog` |
| Price range | Price level, e.g. `€€` |
| Notice | Short note in the footer, e.g. the repair warranty |
| Action bar | Call and booking buttons at the bottom of the screen on phones |
| Opening hours | Opening and closing time per day of the week |

## Price Lists, Vehicles and Coupons

Pricing elements cover three uses:

- **Price lists**: list items ending with a bold price (`- Oil service `60 min` **€89**`) get a dashed leader to the right-aligned price, inline code becomes a small duration tag
- **Vehicles**: items with an image whose features consist of inline code only (`` - `2021` ``, `` - `48,000 km` ``) show the features as a two-column spec strip; put the monthly rate in the item text and "Certified" or "Price drop" in the badge
- **Coupons**: items without an image and without a price list are rendered as dashed coupons with side notches

Cards in four columns become dark team cards with grayscale portraits, `figures` cards look like odometer read-outs and `badges` cards like inspection checks.

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Default | Description |
|----------|---------|-------------|
| `--pico-color` | `#1F2328` | Body text color |
| `--pico-background-color` | `#F3F4F2` | Page background |
| `--pico-contrast` | `#16191D` | Headings, dark sections and the footer |
| `--pico-primary` | `#C8102E` | Primary accent and filled buttons (signal red) |
| `--pico-secondary` | `#2B3036` | Secondary accent (graphite) |
| `--pico-border-radius` | `0.25rem` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=torque
```

## Structure

```
├── composer.json
├── schema.json          Theme and garage configuration schema
├── database/seeders/    TorqueDemo seeder
├── lang/                Frontend translations
├── src/
│   └── TorqueServiceProvider.php
├── public/              CSS and admin translations published to public/vendor/cms/torque/
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
