# Aimeos Theme

Enterprise ecommerce design for [Pagible CMS](https://pagible.com), inspired by the visual language of [aimeos.org](https://aimeos.org/).

The package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible). It contains presentation assets only and deliberately ships without demo content.

## Installation

```bash
composer require aimeos/pagible-themes-aimeos
php artisan vendor:publish --tag=cms-theme
```

## Design

- Deep navy hero and footer surfaces with warm gold accents
- White navigation and content sections with precise, square geometry
- System sans-serif typography and compact uppercase labels
- Technical commerce artwork isolated on a transparent background
- Responsive page, documentation and blog layouts built on shared Pagible views
- Pico CSS variables exposed through the theme schema

## Page types

| Type | Purpose |
|------|---------|
| `page` | Product, company and campaign pages |
| `docs` | Developer documentation with sidebar navigation |
| `blog` | News, releases and technical articles |

## Structure

```text
themes/aimeos/
├── composer.json
├── schema.json
├── src/AimeosServiceProvider.php
├── public/
│   ├── commerce-network.png
│   ├── cms.css
│   ├── hero.css
│   └── component and layout styles
└── views/layouts/main.blade.php
```

## License

MIT
