# Premium Theme

Warm, professional design with burgundy red brand accents, large rounded cards, and bold typography for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-premium
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Modern, clean, professional with alternating section backgrounds
- **Colors**: Cool neutral (#F8F9FA), burgundy red (#991B1B) and indigo (#4338CA) accents
- **Typography**: System sans-serif, weights 400/500/600
- **Borders**: 0.5rem base radius, up to 2rem for cards/containers
- **Surfaces**: White (#FFFFFF) cards with subtle shadows on light background
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Standard landing pages |
| `docs` | Documentation with sidebar navigation |
| `blog` | Blog with featured post and article list |

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Default | Description |
|----------|---------|-------------|
| `--pico-color` | `#0F172A` | Body text color |
| `--pico-background-color` | `#F8F9FA` | Page background |
| `--pico-primary` | `#991B1B` | Primary accent (burgundy) |
| `--pico-secondary` | `#4338CA` | Secondary accent (indigo) |
| `--pico-border-radius` | `0.5rem` | Base border radius |

## Structure

```
├── composer.json
├── schema.json          Theme configuration schema
├── src/
│   └── PremiumServiceProvider.php
├── public/              CSS files published to public/vendor/cms/premium/
│   ├── cms.css          Base styles and layout
│   ├── cms-lazy.css     Lazy-loaded component styles
│   ├── hero.css         Hero section
│   ├── cards.css        Card grid
│   ├── blog.css         Blog components
│   ├── article.css      Article content
│   ├── slideshow.css    Image slideshow
│   ├── questions.css    FAQ accordion
│   ├── contact.css      Contact form
│   ├── image.css        Image component
│   ├── image-text.css   Image with text
│   ├── pricing.css      Pricing tables
│   ├── table.css        Data tables
│   ├── toc.css          Table of contents
│   ├── video.css        Video component
│   ├── layout-page.css  Page layout
│   ├── layout-blog.css  Blog layout
│   └── layout-docs.css  Documentation layout
└── views/
    └── layouts/
        └── main.blade.php
```

## License

LGPL-3.0-only
