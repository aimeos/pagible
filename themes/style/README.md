# Style Theme

Fashion-commerce editorial design inspired by [Luxury Pro](https://luxury-pro.vercel.app/) for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-style
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Editorial fashion storefront with oversized condensed display type, sharp grids, image-led hero sections and compact commercial navigation
- **Colors**: Cream base (`#F4EEE6`), ink text (`#17120F`), white product surfaces, deep red accents (`#B41622`) and restrained cool-gray supporting tones
- **Typography**: Condensed heavy display stack for headlines and brand text; clean geometric sans-serif for body copy, labels and controls
- **Borders**: Sharp, zero-radius layout with hairline rules and crisp image frames
- **Surfaces**: Alternating cream, white and ink sections, with black footer treatment for shop/about link groups
- **Interaction**: Ink buttons and links reveal the deep red accent on hover and focus
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
| `--pico-color` | `#17120F` | Body text color |
| `--pico-background-color` | `#F4EEE6` | Page background |
| `--pico-primary` | `#17120F` | Primary links and CTA fill |
| `--pico-primary-hover` | `#B41622` | Red hover accent |
| `--pico-secondary` | `#B41622` | Decorative accent |
| `--pico-border-radius` | `0` | Base border radius |

## Structure

```
├── composer.json
├── schema.json          Theme configuration schema
├── src/
│   └── StyleServiceProvider.php
├── public/              CSS files published to public/vendor/cms/style/
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
