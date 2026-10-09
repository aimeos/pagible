# Counsel Theme

Classic, trustworthy design for law firms, attorneys and notaries with navy, ivory and brass colors, serif headings, sharp corners and fine gold rules for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-counsel
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Calm and authoritative, with an ivory header, navy footer, navy heroes and dark sections with a faint diagonal pinstripe, thin gold rules, uppercase tracked eyebrows and grayscale portraits that turn to color on hover
- **Colors**: Ivory (#F8F6F1), navy (#0E2A47), brass (#7D5F2A) and gold (#C6A15B)
- **Typography**: Classic serif fonts for headings, system sans-serif body
- **Borders**: Sharp corners, hairline borders and gold top rules instead of shadows
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Landing, practice area and attorney pages |
| `docs` | Documentation with sidebar navigation |
| `blog` | Legal insights and news listed by the blog element |

## Firm Details

The **Firm** settings in the page config add a legal business JSON-LD to every page below the configured page:

| Field | Description |
|-------|-------------|
| Firm type | schema.org type: `LegalService`, `Attorney`, `Notary` or `LocalBusiness` |
| Name, address, telephone, email | Firm details, the telephone is also used by the action bar |
| Hotline | Urgent number shown in the top bar of every page and rendered as emergency `contactPoint` |
| Consultation link | Consultation page or online booking, shown as a button in the header and rendered as `ReserveAction` |
| Languages | Comma separated languages, rendered as `knowsLanguage` |
| Practice areas | Comma separated practice areas, rendered as `knowsAbout` |
| Price range | Price level, e.g. `€€€` |
| Action bar | Call and consultation buttons at the bottom of the screen on phones |
| Disclaimer | Legal disclaimer shown in the footer of every page |
| Office hours | Opening and closing time per day of the week |

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Default | Description |
|----------|---------|-------------|
| `--pico-color` | `#1F2733` | Body text color |
| `--pico-background-color` | `#F8F6F1` | Page background |
| `--pico-contrast` | `#0E2A47` | Navy for headings and dark sections |
| `--pico-primary` | `#7D5F2A` | Primary accent (brass) |
| `--pico-secondary` | `#C6A15B` | Secondary accent (gold) |
| `--pico-border-radius` | `0.125rem` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=counsel
```

## Structure

```
├── composer.json
├── schema.json          Theme and firm configuration schema
├── database/seeders/    CounselDemo seeder
├── lang/                Frontend translations
├── src/
│   └── CounselServiceProvider.php
├── public/              CSS and admin translations published to public/vendor/cms/counsel/
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
