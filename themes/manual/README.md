# Manual Theme

Software documentation design for [Pagible CMS](https://pagible.com), modeled on the docs of Stripe, Tailwind CSS, Starlight, Mintlify, VitePress and Laravel. It switches between light and dark mode automatically and uses a sticky three-column layout: grouped sidebar navigation, the article, and an "On this page" outline that highlights the section you're reading. Pages get previous and next links and dark code blocks with copy buttons. You also get GitHub-style callouts, numbered steps, reference tables, a version badge, a repository link and a `Ctrl K` search shortcut.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-manual
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Layout**: A sticky, blurred header holds the logo, a version pill, a search field with a `Ctrl K` hint, the top-level sections and a repository icon. Docs pages have three columns:
  - a sidebar with the current section and its grouped pages (off-canvas on small screens)
  - the article, at most `48rem` wide
  - an "On this page" outline built from the H2 and H3 headings (from `1280px`)
- **Navigation**: Previous and next links follow the sidebar order. An eyebrow above the title names the parent page, and "Last updated" shows the date of the latest change. Hovering an H2 or H3 reveals a `#` anchor link
- **Colors**: Slate neutrals (`#0F172A`, `#334155`, `#64748B`, `#E2E8F0`) and blue (`#2563EB`, `#60A5FA` in dark mode) for links and the active page. The dark background is `#0B1120`. Code blocks are always dark
- **Typography**: The system sans-serif and monospace fonts, because the CSP doesn't allow remote fonts. Text is set at `1rem` with a comfortable line height and tight headings
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Landing page of the project without sidebar |
| `docs` | Documentation pages with sidebar, outline, pager and `TechArticle` JSON-LD |
| `blog` | Blog posts listed by the blog element |

The sidebar shows the second-level page of the current branch (e.g. "Docs" or "API reference") with two levels of pages below it. Create one `docs` page per section below the home page and nest the topics in groups.

## Project Details

The **Project** settings in the page config apply to the configured page and all pages below it:

| Field | Description |
|-------|-------------|
| Name, description, programming language | Rendered as `SoftwareSourceCode` JSON-LD |
| Version | Pill next to the logo, e.g. `v2.4` |
| Repository | Icon link in the header and `codeRepository` |
| License | Shown in the footer |

## Elements

- **Text**: GitHub alerts become colored callouts: `> [!NOTE]`, `> [!TIP]`, `> [!IMPORTANT]`, `> [!WARNING]` and `> [!CAUTION]`
- **Code**: Dark box showing the language name and a "Copy code" button
- **Heading**: H2 and H3 headings fill the outline and get anchor links
- **Timeline**: The `vertical` layout shows numbered steps for installation guides and dated releases for changelogs
- **Table**: Compact reference tables for options, parameters and commands
- **Cards**: Linked cards for section overviews; SVG icons are shown in blue tiles

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Light | Dark | Description |
|----------|-------|------|-------------|
| `--pico-color` | `#334155` | `#CBD5E1` | Body text color |
| `--pico-background-color` | `#FFFFFF` | `#0B1120` | Page background |
| `--pico-contrast` | `#0F172A` | `#F8FAFC` | Headings |
| `--pico-primary` | `#2563EB` | `#60A5FA` | Links and the active page |
| `--pico-secondary` | `#64748B` | `#94A3B8` | Muted text |
| `--pico-border-radius` | `0.5rem` | `0.5rem` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=manual
```

The demo builds the documentation of "Waypoint", a fictional audit log package for Laravel. It contains a landing page, getting started pages (installation, quickstart, configuration), guides (custom events, pruning, testing), an API reference (Auditable trait, Activity model, Waypoint facade, Artisan commands) and a changelog.

## Structure

```
├── composer.json
├── schema.json          Theme and project configuration schema
├── database/seeders/    ManualDemo seeder
├── lang/                Frontend translations
├── src/
│   └── ManualServiceProvider.php
├── public/              Assets published to public/vendor/cms/manual/
│   ├── cms.css          Base styles, light and dark tokens, header, callouts and code boxes
│   ├── layout-docs.css  Three-column docs layout, sidebar, outline and pager
│   ├── manual.js        Search shortcut, copy buttons, heading anchors and scroll spy
│   ├── i18n/            Admin translations of the config fields
│   └── *.css            Content element styles
├── tests/
└── views/
    ├── code.blade.php   Code block with copy button
    ├── text.blade.php   Markdown with callouts
    └── layouts/
        ├── docs.blade.php
        └── main.blade.php
```

## License

MIT
