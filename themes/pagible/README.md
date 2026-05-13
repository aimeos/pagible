# Pagible Theme

Dark, immersive design with blue-purple gradient accents, sharp edges, and a deep navy background for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-pagible
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Dark-only theme with radial gradient backgrounds and translucent surfaces
- **Colors**: Deep navy (#080040), blue (#0868D0) and purple (#B008C8) gradient accents
- **Typography**: Helvetica Neue / system font, normal weight
- **Borders**: Sharp edges (0 border-radius), pill buttons (2rem)
- **Buttons**: Pill-shaped with linear gradient (blue to purple)
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
| `--pico-color` | `#FFFFFFD0` | Body text color |
| `--pico-background-color` | `#080040` | Page background |
| `--pico-primary` | `#0868D0` | Primary accent (blue) |
| `--pico-secondary` | `#B008C8` | Secondary accent (purple) |
| `--pico-border-radius` | `0` | Base border radius |

## Structure

```
├── composer.json
├── schema.json          Theme configuration schema
├── src/
│   └── PagibleServiceProvider.php
├── public/              CSS files published to public/vendor/cms/pagible/
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
