# Taste Theme

A modern restaurant, food and drinks theme for [Pagible CMS](https://pagible.com). Its composition is inspired by the welcoming, image-led rhythm of the Ichiraku Ramen reference while using a vivid dark palette and no animation or transition effects.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-taste
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Image-led restaurant pages with a split hero, menu cards, editorial story sections and practical visit information
- **Colors**: Midnight ink (`#090D14`), cloud white (`#E7EEF5`), electric emerald (`#39D6B4`), coral pink (`#FF6B7A`) and violet (`#8B5CF6`)
- **Typography**: Rounded system sans-serif with compact labels and large, friendly display headings
- **Surfaces**: Layered midnight panels, subtle gradient borders, crisp image crops and emerald-violet depth
- **Motion**: No CSS animations, transitions or smooth scrolling
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Restaurant landing, menu and visit pages |
| `docs` | Documentation or recipe collections |
| `blog` | News, recipes and journal articles |

## License

MIT
