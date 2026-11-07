---
name: pagible-themes
description: Builds Pagible CMS content elements and frontend themes for Laravel applications - schema.json element and field definitions, Blade element views, action classes that load application data, theme packages with layouts, CSS and --pico-* design tokens, demo seeders and admin translations. Use when adding a custom content element or block type to Pagible, overriding a bundled element view, creating or restyling a Pagible theme, or debugging why an element or theme doesn't show up in the admin or on the website.
license: MIT
metadata:
  author: Aimeos
---

# Building Pagible elements and themes

Pagible (`aimeos/pagible`) renders pages from content elements. Each element type is defined in a `schema.json` (fields shown in the admin panel) and rendered by a Blade view of the same name. A theme is a schema that also defines page `types`, plus a main layout and CSS.

## Choose the right approach

| Goal | Do this | Reference |
|------|---------|-----------|
| New element for one application | `resources/cms/schema.json` + views, registered in the app's service provider | [schema.md](references/schema.md), [views.md](references/views.md) |
| Element that shows application data (products, jobs, events) | Element with a hidden `action` field pointing to an invokable class | [views.md](references/views.md#action-classes) |
| Change the markup of a bundled element (e.g. `hero`) | Add `views/hero.blade.php` to your theme | [views.md](references/views.md#overriding-bundled-elements) |
| New look for the whole site | Theme package with `ThemeProvider`, `layouts/main.blade.php`, CSS and `--pico-*` tokens | [theme-package.md](references/theme-package.md) |
| Site-wide settings (business address, opening hours) | `config` element in the theme schema, read with `cmsconfig()` | [schema.md](references/schema.md#sections-of-schemajson) |

Prefer the smallest option. Restyle with CSS and tokens before overriding views, and override views before adding new element types.

## Workflow: add a content element

1. Look at the bundled elements first: `vendor/aimeos/pagible-theme/schema.json` and `vendor/aimeos/pagible-theme/views/`. Copy the closest one instead of starting from scratch.
2. Add the element to the `content` section of your `schema.json`. Use known field types only and give every field a short `hint`.
3. Register the schema and the view namespace (skip if a `ThemeProvider` does it):
   ```php
   Schema::register( resource_path( 'cms' ), 'app' );
   View::addNamespace( 'app', resource_path( 'cms/views' ) );
   ```
4. Create `views/<type>.blade.php`. Read fields from `$data`, files from `$files`, escape everything and load the element CSS with `@pushOnce('foot')`.
5. Run `php artisan cms:schema:check app` and fix all errors.
6. Add the element to a page in the admin panel (`/cmsadmin`), save and open the preview.

## Workflow: create a theme

1. Copy the bundled theme closest to the target design (light: `premium`, `paper`; dark: `glass`; see `vendor/aimeos/pagible-themes-*`) or start from the skeleton in [theme-package.md](references/theme-package.md).
2. Rename the package, provider class, `$name`, `schema.json` label and the `theme-<name>` body class.
3. Set the design tokens as defaults of the `config.theme` fields in `schema.json`, then restyle `public/cms.css` and the per-element CSS files.
4. Install it with `composer require`, then `php artisan vendor:publish --tag=cms-theme --force`.
5. Seed demo pages with `php artisan cms:demo --theme=<name>` and check every element type in light and dark mode, on mobile and desktop.
6. Run `php artisan cms:schema:check <name>`.

## Rules

- **Escape output**: Use `{{ }}`, `@text()` for single-line inline Markdown and `@markdown()` for Markdown fields. Use `{!! !!}` only for `cmsjson()` in JSON-LD and for `html` fields, which are purified on save. Prefer `markdown` fields over `html`.
- **Links**: Pass every content URL through `cmslink()`, which drops `javascript:` and other unsafe schemes, and output the `url-rel` value of `url` fields with `"rel": true`.
- **Images and video**: Use `<x-cms::image>` and `<x-cms::video>`, never hand-written `<img>` tags, so `srcset`, lazy loading, alt texts and private files work.
- **Missing data**: Element data isn't validated at render time. Every property can be missing, so always use `?? null` / `?? ''` and render nothing for empty optional parts.
- **Translations**: Wrap every fixed frontend string in `__()` and ship `lang/<locale>.json`. Admin labels and hints come from `public/i18n/<locale>.json`.
- **Blade**: No `@php` blocks. Render nested elements with `@element($page, $item)` and use the layout components (`<x-cms::head>`, `brand`, `menu`, `languages`, `breadcrumb`, `search`).
- **Assets**: Load CSS and JS with `cmstheme($page, 'file.css')`. The CSP only allows assets from your own origin, so don't link external fonts, scripts or stylesheets.
- **JSON-LD**: Escape `@` as `@@` inside Blade (`"@@context"`, `"@@type"`).
- **Caching**: Anonymous pages are cached as complete HTML. Don't render per-visitor data into element views. Pages whose action results must always be current need a page `cache` of `0`.

## Verify

- `php artisan cms:schema:check [schema]` fails on unknown field types, `select`/`radio` without options, `autocomplete` without data source, missing action classes and element types without a view.
- Open the page as a visitor in a private window, because editors see the latest draft and visitors see the published version.
- Clear compiled views (`php artisan view:clear`) and republish assets (`php artisan vendor:publish --tag=cms-theme --force`) after changing a theme package.
