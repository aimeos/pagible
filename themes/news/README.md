# News Theme

A newspaper theme for [Pagible CMS](https://pagible.com), designed for daily reporting, business desks, and editorial publications.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-news
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Dense financial-newspaper layout with a utility band, compact centered masthead, section rail, restrained lead-story split, top-story columns, and a numbered most-read strip
- **Colors**: One continuous light-grey newsprint surface (`#E9ECEE`), ink (`#262A2C`), burgundy accents (`#8B1538`), and deep-teal utility areas (`#0D4851`)
- **Typography**: System serif masthead and headlines with compact sans-serif navigation, dates, metadata, and controls
- **Geometry**: Sharp corners, thin editorial rules, and no decorative shadows
- **Layout**: A responsive 1180px news desk with tight vertical rhythm, compact supporting coverage, and no floating page sheet
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

The visual hierarchy takes cues from established financial newspapers while retaining original branding, content, a light-grey palette, and original component treatment.

## Demo

The package includes `Database\Seeders\NewsDemo`, an English-language business newspaper named **The Ledger**:

```bash
php artisan cms:demo --theme=news --tenant=news
```

It creates Economy, Money, Property, and Work desks with original articles, an editorial page, subscription options, shared footer navigation, accessible media descriptions, and search metadata.

## Page Types

| Type | Description |
|------|-------------|
| `page` | Front page, publication, and subscription pages |
| `docs` | Long-form dossiers with sidebar navigation |
| `blog` | News desks and individual articles |

## Customization

| Property | Default | Description |
|----------|---------|-------------|
| `--pico-color` | `#262A2C` | Body and headline ink |
| `--pico-background-color` | `#E9ECEE` | Continuous light-grey newsprint |
| `--pico-primary` | `#8B1538` | Section and action accent |
| `--pico-secondary` | `#0D4851` | Utility accent |
| `--pico-border-radius` | `0` | Sharp editorial geometry |

## License

MIT
