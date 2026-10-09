# Tonic Theme

Dark, moody design for cocktail bars, wine bars and pubs with brass and oxblood accents, Didone headings, Art Deco ornaments and sharp corners for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-tonic
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Speakeasy and Art Deco, with an oxblood announcement bar, a near-black background, full-bleed image heroes, brass diamond ornaments above section headings, cocktail menu boards with dotted price leaders, brass-framed images and a dark footer with the address, late opening hours and a responsible drinking notice
- **Colors**: Night (#14110F), cream (#EDE4D3), brass (#C9A45C) and oxblood (#8E2B3A)
- **Typography**: Didone display headings (Didot, Bodoni), system sans-serif body and uppercase, letter-spaced labels and navigation
- **Borders**: Sharp corners, thin brass outlines with inset frames and double brass rules
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Landing, menu, events, private events, masterclasses and visit pages |
| `docs` | Documentation with sidebar navigation |
| `blog` | Journal posts listed by the blog element |

## Bar Details

The **Bar** settings in the page config add a bar business JSON-LD to every page below the configured page:

| Field | Description |
|-------|-------------|
| Business type | schema.org type: `BarOrPub`, `NightClub`, `Brewery`, `Restaurant` or `LocalBusiness` |
| Name, address, telephone, email | Bar details shown in the footer, the telephone is also used by the action bar |
| Announcement | Short note shown in the top bar of every page |
| Booking link | Table reservation page, shown as "Book a table" in the header and rendered as `ReserveAction` |
| Menu link | Drinks menu page, rendered as `hasMenu` |
| Cuisine | Comma separated list, rendered as `servesCuisine` |
| Price range | Price level, e.g. `€€` |
| Notice | Short note in the footer, e.g. age limit or responsible drinking |
| Action bar | Call and booking buttons at the bottom of the screen on phones |
| Opening hours | Opening and closing time per day of the week, closing times after midnight are allowed |

## Menus

Pricing elements double as menu boards: list items ending with a bold price (`- Negroni **12.00**`) are rendered with a dotted brass leader to the right-aligned price, and inline code (`` `0.0` ``) becomes a small tag. In cards, inline code is shown as outlined flavour pills and a bold first line as a brass label.

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Default | Description |
|----------|---------|-------------|
| `--pico-color` | `#EDE4D3` | Body text color |
| `--pico-background-color` | `#14110F` | Page background |
| `--pico-contrast` | `#EDE4D3` | Headings and light accents |
| `--pico-primary` | `#C9A45C` | Primary accent (brass) |
| `--pico-secondary` | `#8E2B3A` | Secondary accent (oxblood) |
| `--pico-border-radius` | `0` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=tonic
```

## Structure

```
├── composer.json
├── schema.json          Theme and bar configuration schema
├── database/seeders/    TonicDemo seeder
├── lang/                Frontend translations
├── src/
│   └── TonicServiceProvider.php
├── public/              CSS and admin translations published to public/vendor/cms/tonic/
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
