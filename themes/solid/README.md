# Solid Theme

Dependable design for builders, renovation companies and general contractors with charcoal surfaces, safety yellow accents, condensed bold headings and square edges for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-solid
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Sturdy and direct with photo-led sections, a yellow header line and a floating figures strip below the hero
- **Colors**: Warm concrete (#F4F3EF), charcoal (#1F2328) and safety yellow (#F2B705)
- **Typography**: System sans-serif, condensed sentence case headings with weight 800
- **Borders**: Square edges, thin borders and soft shadows
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Landing and service pages |
| `docs` | Documentation with sidebar navigation |
| `blog` | Project and news pages listed by the blog element |

## Business Details

The **Business** settings in the page config add a local business JSON-LD to every page below the configured page:

| Field | Description |
|-------|-------------|
| Business type | schema.org type, e.g. `GeneralContractor`, `RoofingContractor` or `Electrician` |
| Name, address, telephone, email | Company details, the telephone is also used by the call button |
| Places served | Comma separated towns and regions, rendered as `areaServed` |
| Price range | Price level, e.g. `££` |
| Opening hours | Opening and closing time per day of the week |
| Call button | Sticky call button at the bottom of the screen on phones |

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Default | Description |
|----------|---------|-------------|
| `--pico-color` | `#1F2328` | Body text color |
| `--pico-background-color` | `#F4F3EF` | Page background |
| `--pico-primary` | `#F2B705` | Primary accent (safety yellow) |
| `--pico-secondary` | `#3A3F46` | Secondary accent (graphite) |
| `--pico-border-radius` | `0` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=solid
```

## Structure

```
├── composer.json
├── schema.json          Theme and business configuration schema
├── database/seeders/    SolidDemo seeder
├── lang/                Frontend translations
├── src/
│   └── SolidServiceProvider.php
├── public/              CSS and admin translations published to public/vendor/cms/solid/
│   ├── cms.css          Base styles, header, footer and call button
│   ├── i18n/            Admin translations of the config fields
│   └── *.css            Content element and layout styles
├── tests/
└── views/
    └── layouts/
        └── main.blade.php
```

## License

MIT
