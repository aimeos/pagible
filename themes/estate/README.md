# Estate Theme

Premium real-estate showcase with clean editorial spacing, large property photography, a unified homepage portfolio, and market news.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-estate
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Editorial property marketing with generous whitespace and image-first storytelling
- **Palette**: White pages (`#FFFFFF`), charcoal ink (`#242424`), and a focused signal-red accent (`#D00000`)
- **Typography**: Editorial serif display type with a crisp sans-serif body
- **Navigation**: Persistent top navigation, searchable content, and direct contact paths
- **Interaction**: Fine rules, square controls, clear property facts, and minimal shadow
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Property marketing pages and service pages |
| `docs` | Company and advisory pages |
| `blog` | Market news and property insight posts |
| `property` | Individual sale and rental property details |

## Structure

```
├── composer.json
├── schema.json          Theme configuration schema
├── src/
│   └── EstateServiceProvider.php
├── public/              CSS files published to public/vendor/cms/estate/
│   ├── cms.css
│   ├── cms-lazy.css
│   ├── hero.css
│   ├── cards.css
│   ├── article.css
│   ├── property.css
│   ├── properties.css
│   ├── slideshow.css
│   ├── questions.css
│   ├── contact.css
│   ├── image.css
│   ├── image-text.css
│   ├── pricing.css
│   ├── table.css
│   ├── toc.css
│   ├── video.css
│   ├── layout-page.css
│   ├── layout-blog.css
│   └── layout-docs.css
└── views/
    ├── layouts/
    │   └── main.blade.php
    ├── properties.blade.php
    ├── property-item.blade.php
    └── property.blade.php
```

## Properties

The `property` content element renders an image gallery, pricing, status, an optional availability date, listing freshness, location copy with an optional OpenStreetMap position, key facts, optional values, typed document downloads, structured data, fallback description and social metadata with image descriptions, a print-friendly view, and the existing CMS contact form. Contact submissions include the originating property URL. Sold and rented properties invite visitors to ask about similar properties instead of requesting a viewing. Structured offers identify the configured site as seller unless it still uses Laravel's default application name.

The `properties` content element defaults to a six-item card layout and also supports a list layout, optional filtering by city, property type, offer type, minimum room count, and availability date, database-backed position and date sorting, pagination, and property pages below its configured parent. The action normalizes request state once and returns canonical filters, schema options, and the paginator. Unfiltered lists are paginated in the database; filtered lists preserve database ordering and filter property content in memory. This intentionally targets normal agency-sized portfolios; larger inventories should use a dedicated indexed property search rather than expanding the theme action.

## Seeder

The package includes `Database\Seeders\EstateDemo` which builds a property-focused homepage with one filterable portfolio, individual property exposés, and market news.

## Browser tests

Run the Estate filter and contact-script browser tests from the monorepo root:

```bash
npm run test:estate
```

## License

MIT
