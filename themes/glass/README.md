# Glass Theme

Dark glassmorphism design with frosted panels, backdrop blur, and vibrant mint-green accents for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-glass
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Dark glassmorphism with frosted panels and layered depth
- **Colors**: Deep navy (#060A12), mint green (#8AFFC4) and indigo (#6366F1) accents
- **Typography**: System font, weights 300/500
- **Borders**: 1.5rem radius for cards/containers, pill-shaped (9999px) buttons
- **Surfaces**: Translucent glass panels with `backdrop-filter: blur(20px)`
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
| `--pico-color` | `#E2E8F0` | Body text color |
| `--pico-background-color` | `#060A12` | Page background |
| `--pico-primary` | `#8AFFC4` | Primary accent (mint green) |
| `--pico-secondary` | `#6366F1` | Secondary accent (indigo) |
| `--pico-border-radius` | `1.25rem` | Base border radius |

## Structure

```
├── composer.json
├── schema.json          Theme configuration schema
├── src/
│   └── GlassServiceProvider.php
├── public/              CSS files published to public/vendor/cms/glass/
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
