# Grove Theme

Natural design for gardeners, landscapers, garden designers and garden maintenance companies with moss green, cream and terracotta tones, elegant serif headings, arched photo frames, curved section edges and leaf details for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-grove
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Calm, organic look with a green top bar for the announcement and the service area, a blurred cream sticky header with the phone number and a pill-shaped "Free quote" button, dark green heroes with soft glows, an arched photo frame and a curved bottom edge, leaf marks before section headings, cream cards with rounded images, arched team portraits, leaf-shaped badge icons, large serif figures, care plans with a dark green highlighted plan, leaf markers in timelines, a dark green call to action and a curved forest green footer with address, office hours, service areas and guarantee notice
- **Colors**: Cream (#F6F2EA, #FFFDF8), forest green (#1F3A2C, #2D4E3C), moss green (#3F6B3A), terracotta (#A0532D) and chartreuse (#C5D86D) for accents on dark backgrounds
- **Typography**: Serif display headings (Hoefler Text, Iowan Old Style, Constantia, Palatino, Georgia) with italic green accents and system sans-serif body text
- **Borders**: Soft `1rem` corners, pill-shaped buttons, tags and badges, arched hero and team images and round avatars
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Landing, services, projects, care plans, about, contact and quote pages |
| `docs` | Documentation with sidebar navigation |
| `blog` | Garden journal posts listed by the blog element |

## Garden Details

The **Garden** settings in the page config add a local business JSON-LD to every page below the configured page:

| Field | Description |
|-------|-------------|
| Business type | schema.org type: `HomeAndConstructionBusiness`, `GeneralContractor`, `ProfessionalService`, `GardenStore` or `LocalBusiness` |
| Name, address, telephone, email | Business details shown in the footer, the telephone is also shown in the header and used by the action bar |
| Service area | Comma-separated towns, shown in the top bar and footer and rendered as `areaServed` |
| Announcement | Short note shown in the top bar of every page |
| Quote link | Quote request page, shown as "Free quote" in the header and rendered as `QuoteAction` |
| Care plans link | Maintenance plans page, rendered as `hasOfferCatalog` |
| Price range | Price level, e.g. `££` |
| Notice | Short note in the footer, e.g. insurance and plant guarantee |
| Action bar | Call and quote buttons at the bottom of the screen on phones |
| Office hours | Opening and closing time per day of the week |

## Cards, Care Plans and Projects

- **Services and projects**: cards with a rounded image, a bold first line as terracotta eyebrow and inline code as a sage tag, e.g. `` `Guildford · 2025` `` for the place and year of a project
- **Team**: cards in four columns show centered arched portraits
- **Badges and figures**: `badges` cards get leaf-shaped green icon boxes, `figures` cards large serif numbers
- **Care plans**: pricing items with features as list items and inline code tags, the highlighted plan is dark green with a chartreuse badge; list items ending with a bold price (``- Mowing `per visit` **£35**``) get a dotted leader to the price

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Default | Description |
|----------|---------|-------------|
| `--pico-color` | `#2B2A24` | Body text color |
| `--pico-background-color` | `#F6F2EA` | Page background |
| `--pico-contrast` | `#1F3A2C` | Headings, dark sections and the footer |
| `--pico-primary` | `#3F6B3A` | Primary accent and filled buttons (moss green) |
| `--pico-secondary` | `#A0532D` | Secondary accent (terracotta) |
| `--pico-border-radius` | `1rem` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=grove
```

## Structure

```
├── composer.json
├── schema.json          Theme and garden configuration schema
├── database/seeders/    GroveDemo seeder
├── lang/                Frontend translations
├── src/
│   └── GroveServiceProvider.php
├── public/              CSS and admin translations published to public/vendor/cms/grove/
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
