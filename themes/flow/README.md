# Flow Theme

Clean, trustworthy design for plumbers, heating engineers and gas fitters with deep petrol, water blue and copper accents, rounded headings, water drop markers and wave backgrounds for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-flow
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Clean and calm with petrol header and footer, wavy water-line backgrounds, a wave edge below the hero and water drop markers
- **Colors**: Pale aqua (#F2F7F7), deep petrol (#0A2B3B), water blue (#0B7A9E) and copper (#E07B39)
- **Typography**: System sans-serif body, rounded system fonts for headings
- **Borders**: Generously rounded corners, thin borders and soft shadows
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
| Business type | schema.org type: `Plumber`, `HVACBusiness` or `HomeAndConstructionBusiness` |
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
| `--pico-color` | `#15303B` | Body text color |
| `--pico-background-color` | `#F2F7F7` | Page background |
| `--pico-primary` | `#0B7A9E` | Primary accent (water blue) |
| `--pico-secondary` | `#E07B39` | Secondary accent (copper) |
| `--pico-border-radius` | `0.75rem` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=flow
```

## Structure

```
├── composer.json
├── schema.json          Theme and business configuration schema
├── database/seeders/    FlowDemo seeder
├── lang/                Frontend translations
├── src/
│   └── FlowServiceProvider.php
├── public/              CSS and admin translations published to public/vendor/cms/flow/
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
