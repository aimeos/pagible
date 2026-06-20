# Bold Theme

Brutalist design with heavyweight typography, sharp edges, and high-contrast layouts for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-bold
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Brutalist with strong visual presence and commanding layouts
- **Colors**: Near-black (#111111), off-white text (#F4F4F4) and orange-red (#FF4500) accents
- **Typography**: Roboto body text with "Archivo Black" display headings
- **Borders**: Sharp 0 radius edges for cards, containers and buttons
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
| `--pico-color` | `#F4F4F4` | Body text color |
| `--pico-background-color` | `#111111` | Page background |
| `--pico-primary` | `#FF4500` | Primary accent (orange-red) |
| `--pico-secondary` | `#F4F4F4` | Secondary accent (off-white) |
| `--pico-border-radius` | `0` | Base border radius |

## Structure

```
├── composer.json
├── schema.json          Theme configuration schema
├── src/
│   └── BoldServiceProvider.php
├── public/              CSS files published to public/vendor/cms/bold/
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
