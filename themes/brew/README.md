# Brew Theme

Warm, minimal design for cafés, coffee roasters and bakeries with paper, espresso and crema colors, serif headings, monospace labels and rounded corners for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-brew
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Calm and handmade, with a dark announcement bar, paper background, arched hero images, menu boards with right-aligned prices, tasting notes as small pills and a dark espresso footer with the address and opening hours
- **Colors**: Paper (#F7F3EC), espresso (#2A211C), roast (#8A4B2A) and crema (#C8A165)
- **Typography**: Serif headings with italic accents in text headings, system sans-serif body and monospace labels, prices and tags
- **Borders**: Rounded cards, pill buttons and dashed crema separators
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Landing, menu, coffee, classes and visit pages |
| `docs` | Documentation with sidebar navigation |
| `blog` | Journal posts listed by the blog element |

## Café Details

The **Café** settings in the page config add a café business JSON-LD to every page below the configured page:

| Field | Description |
|-------|-------------|
| Business type | schema.org type: `CafeOrCoffeeShop`, `Bakery`, `Restaurant` or `LocalBusiness` |
| Name, address, telephone, email | Café details shown in the footer, the telephone is also used by the action bar |
| Announcement | Short note shown in the top bar of every page |
| Order link | Online shop or pre-order page, shown as a button in the header and rendered as `OrderAction` |
| Menu link | Menu page, rendered as `hasMenu` |
| Cuisine | Comma separated list, rendered as `servesCuisine` |
| Price range | Price level, e.g. `€€` |
| Action bar | Call and order buttons at the bottom of the screen on phones |
| Opening hours | Opening and closing time per day of the week, shown in the footer |

## Menus

Pricing elements double as menu boards: list items ending with a bold price (`- Flat white **3.90**`) are rendered with the price aligned right and dashed separators, and inline code (`` `VG` ``) becomes a small dietary tag. In cards, inline code is shown as tasting note pills and a bold first line as a monospace label.

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Default | Description |
|----------|---------|-------------|
| `--pico-color` | `#2A211C` | Body text color |
| `--pico-background-color` | `#F7F3EC` | Page background |
| `--pico-contrast` | `#2A211C` | Espresso for headings and dark sections |
| `--pico-primary` | `#8A4B2A` | Primary accent (roast) |
| `--pico-secondary` | `#C8A165` | Secondary accent (crema) |
| `--pico-border-radius` | `0.75rem` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=brew
```

## Structure

```
├── composer.json
├── schema.json          Theme and café configuration schema
├── database/seeders/    BrewDemo seeder
├── lang/                Frontend translations
├── src/
│   └── BrewServiceProvider.php
├── public/              CSS and admin translations published to public/vendor/cms/brew/
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
