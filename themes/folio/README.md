# Folio Theme

Personal developer blog and portfolio design for [Pagible CMS](https://pagible.com), modeled on leerob.com, overreacted.io, brittanychiang.com and antfu.me. Posts are set in a narrow reading column with a serif body and warm neutrals with one green accent. The theme follows the light or dark mode of the system, and visitors can switch it with a toggle. Writing is grouped by year, and posts show the reading time, a sticky "On this page" outline, an author box and previous and next links. Project tiles carry tech pills, an experience timeline lists your jobs and `Person` markup describes you to search engines.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-folio
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Layout**: A sticky, blurred header shows your name, the top-level pages, a search link with a `Ctrl K` / `/` shortcut and the light and dark switch. All content stays in a `48rem` column
- **Home**: Round avatar, a one-line introduction, the latest posts as a dated list, selected projects and a contact panel
- **Posts**: Date and reading time above the title, a lead paragraph, a cover image, serif body text, `#` anchors on headings, an outline that highlights the current section (from `1280px`), the author box and previous and next posts
- **Footer**: Profile links, your e-mail address with a copy button and a monospace colophon
- **Colors**: Warm neutrals (`#2B2924`, `#6B665E`, `#E4E0D9`, `#FBFAF8`) with green (`#1F7A4D`, `#4FBF87` in dark mode) for links. The dark background is `#0F0E0C`. Code blocks are always dark
- **Typography**: The system sans-serif, serif and monospace fonts, because the CSP doesn't allow remote fonts. Dates, labels and section titles use the monospace font
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Home, overview and personal pages like About, Projects, Uses or Now |
| `blog` | Posts with reading time, outline, author box, pager and `BlogPosting` JSON-LD |

Put all posts below one "Writing" page and add a `blog` element with the `list` layout to it. Previous and next links follow the order in the page tree, so keep the newest post at the top.

## Profile

The **Profile** settings in the page config apply to the configured page and all pages below it:

| Field | Description |
|-------|-------------|
| Name | Shown in the header and as author of the posts |
| Job title, company, location | Used for the `Person` JSON-LD |
| Photo, short bio | Author box below every post |
| E-mail | Footer link with a copy button |
| Profiles | Footer links, also used as `sameAs` for search engines |

## Elements

- **Blog**: The `list` layout groups posts by year with the date on the right; the `cards` layout shows the date, title and introduction. Without a parent page (e.g. "Recent writing" on the home page), the element shows the latest posts without pagination
- **Hero**: The first image is shown as round avatar above the title
- **Cards**: Bordered project tiles; inline `code` becomes tech pills and linked titles get an arrow
- **Timeline**: Experience rows with the years in a left column
- **Text**: GitHub alerts become callouts: `> [!NOTE]`, `> [!TIP]`, `> [!IMPORTANT]`, `> [!WARNING]` and `> [!CAUTION]`
- **Code**: Dark box with the language name and a "Copy code" button
- **Heading**: H2 and H3 headings in posts fill the outline

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Light | Dark | Description |
|----------|-------|------|-------------|
| `--pico-color` | `#2B2924` | `#D9D4CB` | Body text color |
| `--pico-background-color` | `#FBFAF8` | `#0F0E0C` | Page background |
| `--pico-contrast` | `#1C1A17` | `#ECE8E1` | Headings and filled buttons |
| `--pico-primary` | `#1F7A4D` | `#4FBF87` | Links |
| `--pico-secondary` | `#6B665E` | `#9A948A` | Muted text |
| `--pico-border-radius` | `0.5rem` | `0.5rem` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=folio
```

The demo builds the website of Lena Hartmann, a fictional staff engineer in Berlin. It contains a home page, six posts about Laravel, databases and queues, and the Projects, About, Uses and Now pages.

## Structure

```
├── composer.json
├── schema.json          Theme and profile configuration schema
├── database/seeders/    FolioDemo seeder
├── lang/                Frontend translations
├── src/
│   └── FolioServiceProvider.php
├── public/              Assets published to public/vendor/cms/folio/
│   ├── cms.css          Base styles, light and dark tokens, header, footer, callouts and code boxes
│   ├── layout-blog.css  Post column, outline, author box and pager
│   ├── list.css         Posts by year and post cards
│   ├── folio.js         Mode switch, search shortcut, copy buttons, heading anchors and outline
│   ├── i18n/            Admin translations of the config fields
│   └── *.css            Content element styles
├── tests/
└── views/
    ├── article.blade.php  Post header with date and reading time
    ├── blog.blade.php     Post list grouped by year or as cards
    ├── code.blade.php     Code block with copy button
    ├── text.blade.php     Markdown with callouts
    └── layouts/
        ├── blog.blade.php
        └── main.blade.php
```

## License

MIT
