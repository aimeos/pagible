# Luxury Theme

Minimalist high-fashion design with editorial serif headlines, an ivory and near-black palette, antique gold accents, sharp edges and generous whitespace for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-luxury
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Restrained luxury / fashion editorial — flat surfaces, hairline rules, abundant negative space, alternating section backgrounds
- **Colors**: Warm ivory (#FBF9F4), near-black text (#1C1A16) and sparing antique gold accents (#7A5F32)
- **Typography**: High-contrast serif display headlines (Didot/Bodoni/Playfair fallbacks), geometric sans-serif body, uppercase letter-spaced labels
- **Borders**: Sharp, zero radius throughout
- **Surfaces**: White (#FFFFFF) cards with hairline borders and minimal shadow on ivory; near-black footer
- **Interaction**: Monochrome base with a single gold reveal on hover
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
| `--pico-color` | `#1C1A16` | Body text color |
| `--pico-background-color` | `#FBF9F4` | Page background (ivory) |
| `--pico-primary` | `#1C1A16` | Primary accent — links and CTAs (near-black) |
| `--pico-primary-hover` | `#8A6E3F` | Hover reveal (gold) |
| `--pico-secondary` | `#7A5F32` | Decorative accent (antique gold) |
| `--pico-border-radius` | `0` | Base border radius (sharp) |

## Structure

```
├── composer.json
├── schema.json          Theme configuration schema
├── src/
│   └── LuxuryServiceProvider.php
├── public/              CSS files published to public/vendor/cms/luxury/
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

MIT
