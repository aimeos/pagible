# Element views reference

## View resolution

`@element($page, $item)` renders the first existing view of:

| Element type | Views tried |
|--------------|-------------|
| Bundled, e.g. `hero` | `<page theme>::hero` (theme override), `cms::hero`, `cms::invalid` |
| Prefixed, e.g. `app::promo` | `app::promo` (`resources/cms/views/promo.blade.php`), `cms::promo`, `cms::invalid` |

The view of a prefixed element always comes from its own schema namespace, independent of the page theme.

References to shared elements (`{"type": "reference", "refid": "..."}`) are resolved before, so views always get the element data.

## View variables

| Variable | Content |
|----------|---------|
| `$data` | Element fields as object, e.g. `$data->title`, `$data->buttons` |
| `$id`, `$type` | Element ID (usable as HTML anchor) and type |
| `$files` | Files of the page and the shared element, keyed by ID |
| `$action` | Return value of the schema action class, `null` without action |
| `$page` | Current `PageView`; `cms($page, 'lang')`, `cms($page, 'title')` |
| `$theme`, `$nav` | Theme name and the navigation of the layout |

`$data` isn't validated against the schema when rendering. Treat every property as optional:

```blade
@if($data->subtitle ?? null)
    <p class="subtitle">{{ $data->subtitle }}</p>
@endif
```

## Complete example

`resources/cms/views/promo.blade.php` for the `promo` element of [schema.md](schema.md):

```blade
@pushOnce('foot')
<link href="{{ cmstheme($page, 'promo.css') }}" rel="preload" as="style">
@endPushOnce

<section class="promo">
    @if($file = cms($files, $data->file?->id ?? null))
        <x-cms::image :page="$page" :file="$file" class="promo-image" sizes="(min-width: 768px) 50vw, 100vw" />
    @endif

    <div class="promo-body">
        <h2>@text($data->title ?? '')</h2>

        @if($data->text ?? null)
            <div class="cms-text">@markdown($data->text)</div>
        @endif

        <x-cms::buttons :buttons="$data->buttons ?? []" />
    </div>
</section>
```

## Loading CSS and JavaScript

- Element CSS goes to the `foot` stack as `rel="preload" as="style"` and is applied without blocking rendering. Only elements usually shown above the fold (like `hero`) push a normal stylesheet to the `head` stack to avoid layout shifts.
- Scripts go to the `foot` stack with `defer`.
- Use `@pushOnce` so an element used several times on a page loads its assets once.
- `cmstheme($page, 'promo.css')` returns the versioned URL from `public/vendor/cms/<theme>/` and falls back to the base theme file. Assets of app elements outside a theme must be published below `public/vendor/cms/<theme>/` or loaded with `asset()`.

## Directives

| Directive | Use for |
|-----------|---------|
| `@element($page, $item)` | Rendering a nested content element |
| `@text($value)` | `string` and `text` fields: escaped, with inline Markdown (bold, italic, code, links) |
| `@markdown($value)` | `markdown` fields: GitHub-flavored Markdown, raw HTML stripped |
| `@localDate($date, $format)` | `date` fields: no format = day and month, `short`/`medium`/`long`/`full` or a Carbon `isoFormat` pattern |

## Helper functions

| Helper | Returns |
|--------|---------|
| `cms($item, 'path.to.prop', $default)` | Nested property of a page, element, file or collection; editors with view permission get the latest draft |
| `cms($files, $id)` | File object from `$files`, `null` for unknown IDs |
| `cmsasset($page, $file, $width)` | URL of a file or its preview; private files get an access-controlled URL |
| `cmsconfig($page, 'restaurant.data.name', $default)` | Config value of the page or its nearest ancestor |
| `cmslink($url)` | The URL if it's relative or uses http, https, mailto or tel, otherwise `''` |
| `cmsroute($page)` | URL of a page |
| `cmstheme($page, 'file.css')` | Versioned theme asset URL |
| `cmsjson($value)` | JSON for JSON-LD script blocks, output with `{!! !!}` |
| `cmsattr($value)` | Value safe for HTML `id` and `class` attributes |
| `cmsref($page, $item)` | Element data with shared element references resolved |

## Components

| Component | Use for |
|-----------|---------|
| `<x-cms::image :page="$page" :file="$file" sizes="..." />` | Responsive `<picture>` with `srcset`, lazy loading and translated alt text; add `main` for the largest image above the fold |
| `<x-cms::video :page="$page" :file="$file" />` | Video with poster and translated title; `autoplay` plays muted in a loop |
| `<x-cms::buttons :buttons="$data->buttons ?? []" />` | Button list from `items` with `label`, `url` and `url-rel` |
| `<x-cms::head>`, `brand`, `menu`, `languages`, `breadcrumb`, `search` | Layout parts, see [theme-package.md](theme-package.md) |

## Links

```blade
@if($url = cmslink($item->url ?? null))
    <a href="{{ $url }}" rel="{{ $item->{'url-rel'} ?? '' }}">{{ $item->title ?? '' }}</a>
@endif
```

Field names with dashes are read with `$data->{'url-rel'}`.

## Action classes

A hidden `action` field calls an invokable class before rendering. Its return value is available as `$action`:

```json
"action": {"type": "hidden", "value": "\\App\\Cms\\Promotions"}
```

```php
namespace App\Cms;

use Aimeos\Cms\Models\PageView;
use Illuminate\Http\Request;

class Promotions
{
    public function __invoke( Request $request, PageView $page, object $item ) : array
    {
        $limit = min( (int) ( $item->data->limit ?? 3 ), 12 );
        return \App\Models\Promotion::active()->limit( $limit )->get()->all();
    }
}
```

- The class is resolved with `app()->call()`, so any type-hinted dependency is injected. `$item->data` holds the element fields.
- Limit the loaded rows and only select what the view needs; actions run on every uncached page render.
- Validate and cap request parameters (pagination, filters) because they come from visitors.
- Anonymous pages are cached completely, including the action result. Set the page `cache` to `0` if the result must always be current, and never return visitor-specific data.

## Overriding bundled elements

Add a view with the bundled element name to the theme (`views/hero.blade.php`) and it replaces `cms::hero` for pages using the theme. Copy the original from `vendor/aimeos/pagible-theme/views/` and keep reading the same `$data` fields, because the schema and existing content stay the same. To only change the look, restyle the element CSS file instead (`public/hero.css`).

## Frontend translations

Wrap fixed strings in `__()` and add the translations to `lang/<locale>.json` of the theme package (loaded by `ThemeProvider`) or of the application:

```blade
<a href="#main" class="skip-link">{{ __('Skip to main content') }}</a>
```

Content in `$data` is already in the page language; don't translate it with `__()`.
