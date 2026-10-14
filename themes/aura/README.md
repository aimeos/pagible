# Aura Theme

Expressive portfolio and blog design for [Pagible CMS](https://pagible.com) with the polish of rauno.me, paco.me, joshwcomeau.com and linear.app. A slowly drifting violet and pink aurora with fine grain sits behind glass surfaces, and sections rise into view while you scroll. Projects fill a bento grid with spotlight cards, skills run as an endless marquee, figures count up and the floating pill header shows your availability with a glowing dot. Posts get a reading progress bar, a drop cap, a sticky outline and an author card. The theme follows the light or dark mode of the system, visitors can switch it with an animated toggle, and every effect respects reduced motion.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-aura
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Background**: Animated aurora gradients and a subtle grain overlay behind all pages, only moved with `transform` so scrolling stays smooth
- **Header**: A floating glass pill with your name, an availability status, the top-level pages, a search link with a `Ctrl K` / `/` shortcut and the light and dark switch. It condenses after scrolling
- **Home**: Avatar with a rotating gradient ring, a large title with a gradient serif accent, a skill marquee, a bento project grid, counting figures, post cards, testimonials and a dark contact panel with a rotating glow
- **Motion**: Cross-document view transitions between pages, staggered scroll reveals, pointer spotlights on cards and hover lifts. All of it is disabled with `prefers-reduced-motion`
- **Posts**: Reading progress bar, centered post header with date and reading time, glowing cover, drop cap, `#` anchors on headings, an outline that highlights the current section (from `1280px`), a glass author card and previous and next posts
- **Footer**: A large "Get in touch" e-mail link with a copy button, profile pills, a colophon with a "Back to top" link and an outlined wordmark of your first name
- **Colors**: Cool neutrals (`#2A2838`, `#6A687C`, `#F7F6FB`) with violet (`#5B3DF5`, `#A193FF` in dark mode) and pink accents. The dark background is `#08080D`. Code blocks are always dark
- **Typography**: Inter if installed, otherwise the system fonts, because the CSP doesn't allow remote fonts. Emphasis in titles (`*feel alive*`) becomes an italic serif with a gradient
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Home, overview and personal pages like Work or About |
| `blog` | Posts with progress bar, reading time, outline, author card, pager and `BlogPosting` JSON-LD |

Put all posts below one "Writing" page and add a `blog` element with the `list` layout to it. Previous and next links follow the order in the page tree, so keep the newest post at the top.

## Profile

The **Profile** settings in the page config apply to the configured page and all pages below it:

| Field | Description |
|-------|-------------|
| Name | Shown in the header, as author of the posts and as footer wordmark |
| Job title, company, location | Used for the `Person` JSON-LD |
| Photo, short bio | Author card below every post |
| E-mail | Large footer link with a copy button |
| Availability | Short status with a glowing dot in the header, e.g. "Booking projects for 2027" |
| Profiles | Footer pills, also used as `sameAs` for search engines |

## Elements

- **Blog**: The `list` layout shows large rows with date, title and an arrow; the `cards` layout shows the cover, date, reading time, title and introduction. Without a parent page (e.g. "Latest writing" on the home page), the element shows the latest posts without pagination
- **Hero**: The first image is shown as round avatar with a rotating gradient ring above the title
- **Cards**: Glass tiles in a bento grid where the first and last card of every four span two columns; inline `code` becomes tech pills and linked cards get an arrow. The `badges` layout becomes a marquee and `figures` count up to their value
- **Timeline**: Gradient line with glowing markers, vertical for experience and horizontal for processes
- **Testimonial**: Glass cards with a large gradient quote mark
- **CTA**: Dark panel with a rotating conic glow
- **Text**: GitHub alerts become callouts: `> [!NOTE]`, `> [!TIP]`, `> [!IMPORTANT]`, `> [!WARNING]` and `> [!CAUTION]`
- **Code**: Dark window with the language name and a "Copy code" button
- **Heading**: H2 and H3 headings in posts fill the outline

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Light | Dark | Description |
|----------|-------|------|-------------|
| `--pico-color` | `#2A2838` | `#C9C8D8` | Body text color |
| `--pico-background-color` | `#F7F6FB` | `#08080D` | Page background |
| `--pico-contrast` | `#12111A` | `#F4F3FB` | Headings and filled buttons |
| `--pico-primary` | `#5B3DF5` | `#A193FF` | Links and accents |
| `--pico-secondary` | `#6A687C` | `#8D8BA3` | Muted text |
| `--pico-border-radius` | `1rem` | `1rem` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=aura
```

The demo builds the portfolio of Noa Lindqvist, a fictional design engineer in Copenhagen. It contains a home page, the Work, Writing and About pages and five posts about CSS, motion and design systems.

## Structure

```
├── composer.json
├── schema.json          Theme and profile configuration schema
├── database/seeders/    AuraDemo seeder
├── lang/                Frontend translations
├── src/
│   └── AuraServiceProvider.php
├── public/              Assets published to public/vendor/cms/aura/
│   ├── cms.css          Tokens, aurora, header, footer, buttons, reveals, callouts and code boxes
│   ├── layout-blog.css  Progress bar, post column, outline, author card and pager
│   ├── list.css         Post rows and cards
│   ├── aura.js          Mode switch, header, search shortcut, reveals, marquee, spotlights, counters, copy buttons and outline
│   ├── i18n/            Admin translations of the config fields
│   └── *.css            Content element styles
├── tests/
└── views/
    ├── article.blade.php  Post header with date and reading time
    ├── blog.blade.php     Post rows or cards
    ├── code.blade.php     Code block with copy button
    ├── text.blade.php     Markdown with callouts
    └── layouts/
        ├── blog.blade.php
        └── main.blade.php
```

## License

MIT
