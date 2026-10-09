# Volt Theme

Bright, trustworthy design for electricians and electrical contractors with deep navy, electric blue and amber accents, bold headings, rounded corners and photo-led sections for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-volt
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Clean and technical with navy header and footer, photo-led hero and amber quote and call buttons
- **Colors**: Light grey (#F4F6FA), deep navy (#0B1530), electric blue (#1F6FEB) and amber (#FFB020)
- **Typography**: System sans-serif, bold headings with tight letter spacing
- **Borders**: Rounded corners, thin borders and soft shadows
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Landing and service pages |
| `docs` | Documentation with sidebar navigation |
| `blog` | Job and news pages listed by the blog element |

## Business Details

The **Business** settings in the page config add a local business JSON-LD to every page below the configured page:

| Field | Description |
|-------|-------------|
| Business type | schema.org type: `Electrician`, `HVACBusiness` or `HomeAndConstructionBusiness` |
| Name, address, telephone, email | Company details, the telephone is also used by the call button |
| Emergency number | Shown in a bar at the top of every page and added as emergency contact point |
| 24/7 service | Marks the emergency number as available around the clock |
| Places served | Comma separated towns and regions, rendered as `areaServed` |
| Price range | Price level, e.g. `££` |
| Opening hours | Opening and closing time per day of the week |
| Call button | Sticky call button at the bottom of the screen on phones |

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Default | Description |
|----------|---------|-------------|
| `--pico-color` | `#18233A` | Body text color |
| `--pico-background-color` | `#F4F6FA` | Page background |
| `--pico-primary` | `#1F6FEB` | Primary accent (electric blue) |
| `--pico-secondary` | `#FFB020` | Secondary accent (amber) |
| `--pico-border-radius` | `0.375rem` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=volt
```

## Structure

```
├── composer.json
├── schema.json          Theme and business configuration schema
├── database/seeders/    VoltDemo seeder
├── lang/                Frontend translations
├── src/
│   └── VoltServiceProvider.php
├── public/              CSS and admin translations published to public/vendor/cms/volt/
│   ├── cms.css          Base styles, header, emergency bar, footer and call button
│   ├── i18n/            Admin translations of the config fields
│   └── *.css            Content element and layout styles
├── tests/
└── views/
    └── layouts/
        └── main.blade.php
```

## License

MIT
