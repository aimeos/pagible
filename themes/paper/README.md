# Paper Theme

Warm, minimal, print-inspired design with off-white textured background and monochrome palette for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-paper
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Minimal, warm, print-inspired with diagonal line pattern texture
- **Colors**: Monochrome palette, off-white background (#F7F7F4), dark text (#1A1A1A)
- **Typography**: System font, weights 300/400/500
- **Borders**: 2rem radius for cards/containers, pill-shaped (9999px) buttons
- **Surfaces**: White (#FFFFFF) cards with subtle shadows on paper-like background
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
| `--pico-color` | `#1A1A1A` | Body text color |
| `--pico-background-color` | `#F7F7F4` | Page background |
| `--pico-primary` | `#1A1A1A` | Primary accent |
| `--pico-secondary` | `#6090D8` | Secondary accent |
| `--pico-border-radius` | `0.5rem` | Base border radius |

## Structure

```
├── composer.json
├── schema.json          Theme configuration schema
├── src/
│   └── PaperServiceProvider.php
├── public/              CSS files published to public/vendor/cms/paper/
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
