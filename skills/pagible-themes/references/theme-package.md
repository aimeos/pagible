# Theme package reference

A theme is a Composer package. It overrides the main layout and the CSS of the base theme (`aimeos/pagible-theme`) and inherits all element views, JavaScript and Pico CSS files from it.

## Package layout

```
mytheme/
├── composer.json
├── schema.json                     label, page types, theme tokens, optional elements
├── SKILL.md                        design system guide for coding agents (optional)
├── src/MythemeServiceProvider.php
├── views/
│   ├── layouts/main.blade.php      required: HTML document, header and footer
│   ├── layouts/blog.blade.php      optional: page type layout override
│   └── hero.blade.php              optional: bundled element override
├── public/                         published to public/vendor/cms/mytheme/
│   ├── cms.css                     global styles, header, footer, light/dark tokens
│   ├── hero.css, cards.css, ...    one file per element
│   └── i18n/<locale>.json          admin translations of schema labels and hints
├── lang/<locale>.json              frontend translations used by __()
└── database/seeders/MythemeDemo.php   optional demo pages for cms:demo
```

## composer.json

```json
{
    "name": "acme/pagible-theme-mytheme",
    "type": "library",
    "license": "MIT",
    "require": {
        "aimeos/pagible-theme": "*"
    },
    "autoload": {
        "psr-4": {
            "Acme\\Mytheme\\": "src/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "extra": {
        "laravel": {
            "providers": ["Acme\\Mytheme\\MythemeServiceProvider"]
        }
    }
}
```

## Service provider

```php
namespace Acme\Mytheme;

use Aimeos\Cms\ThemeProvider;

class MythemeServiceProvider extends ThemeProvider
{
    protected string $name = 'mytheme';
}
```

`$name` must match `[a-z0-9_-]+`. It is used as schema name, view namespace (`mytheme::`) and asset directory (`public/vendor/cms/mytheme/`). `ThemeProvider` registers the schema, views, `lang/` translations and the admin catalog in `public/i18n/` and publishes `public/` with the `cms-theme` tag. To register more services, override `boot()` and call `parent::boot()`.

## schema.json of a theme

```json
{
    "label": "Mytheme",
    "description": "Calm, airy design with rounded cards and a dark footer",
    "maintainer": "Acme",
    "website": "https://example.com",
    "types": {
        "page": {"sections": ["main", "footer"]},
        "docs": {"sections": ["main", "footer"]},
        "blog": {"sections": ["main", "footer"]}
    },
    "config": {
        "theme": {
            "group": "theme",
            "icon": "<svg ...>",
            "fields": {
                "--pico-font-family-sans-serif": {"type": "string", "hint": "Comma separated list of fonts used for the text", "default": "ui-sans-serif, sans-serif"},
                "--pico-color": {"type": "color", "hint": "Default text color", "default": "#111827"},
                "--pico-background-color": {"type": "color", "hint": "Background color of the pages", "default": "#FFFFFF"},
                "--pico-primary": {"type": "color", "hint": "Main brand color used for links and buttons", "default": "#3B82F6"}
            }
        }
    }
}
```

The `types` key makes the schema selectable as theme. The `config.theme` fields are the design tokens editors can change in the admin panel; `<x-cms::head>` outputs the configured values as `--pico-*` CSS variables. Copy the complete field list from the `config.theme` of a bundled theme and only change the defaults.

Themes with light and dark mode add a `theme-dark` config with the same color fields. The `theme` values then apply to light mode only and `theme-dark` to dark mode.

Industry-specific site settings (address, opening hours, LocalBusiness data) go into an extra `config` element of the theme, e.g. `"restaurant": {...}`, read in the layout with `cmsconfig($page, 'mytheme::restaurant.data')`.

## Main layout

`views/layouts/main.blade.php` is extended by all page type layouts. They fill the `main` and `footer` sections:

```blade
<!DOCTYPE html>
<html class="no-js" data-theme="light" lang="{{ cms($page, 'lang') }}" dir="{{ \Aimeos\Cms\Utils::textDir(cms($page, 'lang')) }}">
    <head>
        <x-cms::head :page="$page" :nav="$nav" />

        <link href="{{ cmstheme($page, 'pico.min.css') }}" rel="stylesheet">
        <link href="{{ cmstheme($page, 'pico.nav.min.css') }}" rel="stylesheet">
        <link href="{{ cmstheme($page, 'pico.dropdown.min.css') }}" rel="stylesheet">
        <link href="{{ cmstheme($page, 'cms.css') }}" rel="stylesheet">
        @stack('head')
    </head>
    <body class="theme-mytheme type-{{ cms($page, 'type') ?: 'page' }}">
        <a href="#main" class="skip-link">{{ __('Skip to main content') }}</a>
        <x-cms::search :page="$page" />

        <header>
            <nav aria-label="{{ __('Main navigation') }}">
                <ul>
                    <li class="brand"><x-cms::brand :page="$page" :nav="$nav" /></li>
                </ul>
                <ul class="menu">
                    <x-cms::menu :page="$page" :nav="$nav" />
                    <x-cms::languages :page="$page" :nav="$nav" />
                </ul>
            </nav>
        </header>

        <x-cms::breadcrumb :page="$page" :nav="$nav" />

        <main id="main">
            @yield('main')
        </main>

        @yield('footer')

        @include('cms::layouts.foot')
    </body>
</html>
```

- `@stack('head')` must come **after** the stylesheets, because the configured `--pico-*` variables are pushed there and have to win.
- `cms::layouts.foot` must stay at the end of `<body>`; it loads the base scripts, the lazy element CSS, page-specific styles and scripts and the editor integration.
- Start from the `main.blade.php` of the bundled theme closest to your design. It also contains the mobile menu buttons and the WebSite/WebPage/BreadcrumbList JSON-LD (write `"@@context"`, `"@@type"` in Blade).
- Component options: `<x-cms::brand alternative>` uses the logo for dark headers, `<x-cms::menu :dropdown="false">` renders top-level links only, `highlight="contact"` marks a menu item as call to action.

## Page type layouts

Pages of type `blog` use the first existing view of `mytheme::layouts.blog`, `cms::layouts.blog` and `cms::layouts.page`. Override one only for a different structure, e.g. extra sections:

```blade
@extends($theme . '::layouts.main')

@pushOnce('head')
<link href="{{ cmstheme($page, 'layout-page.css') }}" rel="stylesheet">
@endPushOnce

@section('main')
    <div class="cms-content" data-section="main">
        @include('cms::elements', ['items' => $content['main'] ?? []])
    </div>
@endsection

@section('footer')
    <footer class="cms-content" data-section="footer">
        @include('cms::elements', ['items' => $content['footer'] ?? []])
    </footer>
@endsection
```

Keep `class="cms-content"` and `data-section`; the admin preview editor uses them to find the sections and elements.

## CSS

- `cmstheme()` falls back to the base theme file, so a theme only needs the CSS files it changes. Most themes provide `cms.css`, `cms-lazy.css`, the layouts (`layout-page.css`, `layout-blog.css`, `layout-docs.css`) and one file per element (`hero.css`, `cards.css`, `image-text.css`, `slideshow.css`, `questions.css`, `pricing.css`, `contact.css`, `toc.css`, `table.css`, `video.css`, `image.css`, `article.css`, ...).
- Build on Pico CSS and its `--pico-*` variables. Write colors as `var(--pico-...)` or literal hex values; don't invent your own `--mytheme-*` variables, editors can only change the `--pico-*` tokens.
- Define light and dark values in `cms.css` with `:root:not([data-theme=dark])`, `@media (prefers-color-scheme: dark) { :root:not([data-theme=light]) {...} }` and `:root[data-theme=dark]`.
- Fonts: the CSP only allows fonts from your own origin. Use system font stacks or ship the font files in `public/` with `@font-face`.
- Respect `prefers-reduced-motion`, keep focus outlines visible, use logical properties (`margin-inline-start`) for RTL languages and test at 576px, 768px and 992px.

## Demo content

`Database\Seeders\MythemeDemo` extends `Database\Seeders\AbstractDemo` and implements `pages()` using its helpers (`saveRoot()`, `savePage()`, `saveElement()`, `image()`, `article()`). `php artisan cms:demo --theme=mytheme` seeds it into the tenant `mytheme`; without the class the default demo is used. Look at the seeders of the bundled themes for complete examples.

## Install and preview

```bash
composer require acme/pagible-theme-mytheme
php artisan vendor:publish --tag=cms-theme --force
php artisan cms:schema:check mytheme
php artisan cms:demo --theme=mytheme --tenant=
```

Select the theme in the page settings of the admin panel. New subpages get the theme of their parent page, existing pages keep theirs. Republish the assets after every CSS change in the package.

## Design system guide

Bundled themes ship a `SKILL.md` that describes their design system (tokens, radii, typography, do's and don'ts). Write one for your theme as well, so agents changing it later keep the styles consistent.
