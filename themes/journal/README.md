# Journal Theme

Structured editorial theme for business publications, magazines, and newsrooms using [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-journal
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Editorial, information-dense, and restrained
- **Colors**: Warm gray page, white story modules, charcoal text, ochre accents
- **Typography**: Serif masthead and headlines with a compact system sans-serif UI
- **Borders**: Sharp corners, thin rules, and no decorative shadows
- **Layout**: Centered masthead and page content aligned to a shared 1156px frame, with a separate category rail and narrow article measure
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Demo

The package includes `Database\Seeders\JournalDemo`, a complete English-language business publication named **Kontur**:

```bash
php artisan cms:demo --theme=journal --tenant=journal
```

It creates Economy, Money, Property, and Work sections with original articles, an editorial about page, subscription options, shared footer navigation, localized media descriptions, and search metadata.

## Page Types

| Type | Description |
|------|-------------|
| `page` | Home, publication, and subscription pages |
| `docs` | Long-form dossiers with sidebar navigation |
| `blog` | Section fronts and editorial articles |

## Customization

| Property | Default | Description |
|----------|---------|-------------|
| `--pico-color` | `#1F1E1C` | Body and headline color |
| `--pico-background-color` | `#F1EFEC` | Page surround |
| `--pico-primary` | `#9A7112` | Editorial accent |
| `--pico-secondary` | `#4C5558` | Secondary accent |
| `--pico-border-radius` | `0` | Sharp editorial geometry |

## Structure

```text
├── composer.json
├── schema.json
├── database/seeders/JournalDemo.php
├── src/JournalServiceProvider.php
├── public/
│   ├── cms.css
│   ├── cms-lazy.css
│   ├── hero.css
│   ├── cards.css
│   ├── list.css
│   └── ...
└── views/layouts/main.blade.php
```

## License

MIT
