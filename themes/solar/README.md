# Solar Theme

Bright, optimistic design for solar, battery storage and heat pump consultants and installers with forest green, leaf green and sun yellow accents, geometric headings, sun markers and solar cell backgrounds for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-solar
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Bright and optimistic with forest green header and footer, solar cell grid backgrounds, a sloped roof edge below the hero and sun markers
- **Colors**: Warm off-white (#F7F8F1), forest green (#12302A), leaf green (#1F7A4D) and sun yellow (#F5B82E)
- **Typography**: System sans-serif body, geometric sans-serif fonts for headings
- **Borders**: Moderately rounded corners, thin borders and soft shadows
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
| Business type | schema.org type: `HomeAndConstructionBusiness`, `Electrician` or `HVACBusiness` |
| Name, address, telephone, email | Company details, the telephone is also used by the call button |
| Emergency number | Shown in a bar at the top of every page and added as emergency contact point |
| 24/7 service | Marks the emergency number as available around the clock |
| Places served | Comma separated towns and regions, rendered as `areaServed` |
| Price range | Price level, e.g. `€€` |
| Opening hours | Opening and closing time per day of the week |
| Call button | Sticky call button at the bottom of the screen on phones |

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Default | Description |
|----------|---------|-------------|
| `--pico-color` | `#1C3530` | Body text color |
| `--pico-background-color` | `#F7F8F1` | Page background |
| `--pico-primary` | `#1F7A4D` | Primary accent (leaf green) |
| `--pico-secondary` | `#F5B82E` | Secondary accent (sun yellow) |
| `--pico-border-radius` | `0.5rem` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=solar
```

## Structure

```
├── composer.json
├── schema.json          Theme and business configuration schema
├── database/seeders/    SolarDemo seeder
├── lang/                Frontend translations
├── src/
│   └── SolarServiceProvider.php
├── public/              CSS and admin translations published to public/vendor/cms/solar/
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
