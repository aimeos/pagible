# Glow Theme

Soft, luminous design for beauty salons, skin studios, nail and lash bars with blush, berry and aubergine tones, Didone display headings, capsule image frames and glowing pill buttons for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-glow
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Calm beauty studio with a berry announcement bar framed by sparkles, a translucent sticky header with a glowing "Book now" pill, split heroes with a capsule-shaped portrait in a thin berry ring on a soft blush glow, sparkles before section headings, price lists with dashed leaders and duration pills, circular team portraits, star-rated testimonials, a pill-shaped call to action and an aubergine footer with the address, opening hours and the cancellation notice
- **Colors**: Blush white (#FCF6F3), aubergine (#2E2230), berry (#A2456B) and blush (#F3D3CF)
- **Typography**: Didone display headings (Bodoni Moda, Bodoni 72, Didot, Georgia) with italic berry accents, system sans-serif body and sentence-case labels
- **Borders**: Round `1.5rem` corners, capsule and circle images, pill buttons and badges, soft berry-tinted shadows
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Landing, treatments, facials, nails, lashes, membership, team, new clients, gift cards and visit pages |
| `docs` | Documentation with sidebar navigation |
| `blog` | Journal posts listed by the blog element |

## Salon Details

The **Salon** settings in the page config add a salon business JSON-LD to every page below the configured page:

| Field | Description |
|-------|-------------|
| Business type | schema.org type: `BeautySalon`, `DaySpa`, `NailSalon`, `HairSalon` or `LocalBusiness` |
| Name, address, telephone, email | Salon details shown in the footer, the telephone is also used by the action bar |
| Announcement | Short note shown in the top bar of every page |
| Booking link | Appointment booking page, shown as "Book now" in the header and rendered as `ReserveAction` |
| Price list link | Treatments and prices page, rendered as `hasOfferCatalog` |
| Price range | Price level, e.g. `€€` |
| Notice | Short note in the footer, e.g. the cancellation policy |
| Action bar | Call and booking buttons at the bottom of the screen on phones |
| Opening hours | Opening and closing time per day of the week |

## Treatment Menus

Pricing elements double as treatment menus: list items ending with a bold price (`- Signature glow `60 min` **119**`) are rendered with a dashed leader to the right-aligned price, and inline code (`` `60 min` ``) becomes a small pill. Cards in four columns become circular team portraits.

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Default | Description |
|----------|---------|-------------|
| `--pico-color` | `#2E2230` | Body text color |
| `--pico-background-color` | `#FCF6F3` | Page background |
| `--pico-contrast` | `#2E2230` | Headings and the footer |
| `--pico-primary` | `#A2456B` | Primary accent and filled buttons (berry) |
| `--pico-secondary` | `#F3D3CF` | Secondary accent (blush) |
| `--pico-border-radius` | `1.5rem` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=glow
```

## Structure

```
├── composer.json
├── schema.json          Theme and salon configuration schema
├── database/seeders/    GlowDemo seeder
├── lang/                Frontend translations
├── src/
│   └── GlowServiceProvider.php
├── public/              CSS and admin translations published to public/vendor/cms/glow/
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
