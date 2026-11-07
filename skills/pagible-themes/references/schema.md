# schema.json reference

A `schema.json` describes the element types an application or theme adds. The bundled `vendor/aimeos/pagible-theme/schema.json` contains examples for every field type.

## Sections of schema.json

```json
{
    "label": "Mytheme",
    "description": "One sentence shown in the theme selection of the admin panel",
    "types": {
        "page": {"sections": ["main", "footer"]},
        "docs": {"sections": ["main", "footer"]},
        "blog": {"sections": ["main", "footer"]}
    },
    "content": { "promo": { ... } },
    "meta": { ... },
    "config": { "theme": { ... }, "restaurant": { ... } }
}
```

| Key | Purpose |
|-----|---------|
| `label`, `description` | Name and description in the admin panel. Themes also set `maintainer`, `email` and `website` |
| `types` | Page types and the content sections editors can fill. **Only schemas with `types` are offered as theme**; without it the schema just adds elements |
| `content` | Content elements editors add to page sections |
| `meta` | Elements in the page's Meta tab (SEO, social media, robots) |
| `config` | Elements in the page's Config tab. They are inherited by subpages, so read them with `cmsconfig($page, '<type>.data.<field>')` |

Section names are rendered by the page type layout (`$content['main']`, `$content['footer']`). Adding a section requires a layout override that renders it, see [theme-package.md](theme-package.md#page-type-layouts).

## Element definition

```json
"promo": {
    "label": "Promotion",
    "group": "content",
    "description": "Highlighted offer with image and button",
    "icon": "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" fill=\"currentColor\"><path d=\"...\"/></svg>",
    "fields": { ... }
}
```

| Property | Description |
|----------|-------------|
| `label` | Name in the element selector; without it the prefixed type (`app::promo`) is shown |
| `group` | Group in the element selector: `basic`, `content`, `media`, `forms`, `expert` or your own name |
| `description` | One sentence telling editors and AI agents what the element is for |
| `icon` | Inline 24x24 SVG using `fill="currentColor"` (e.g. Material Design Icons) |
| `fields` | Field definitions keyed by the property name in `$data` |

Element types are stored with the schema name as prefix (`app::promo`). The bundled elements have no prefix (`hero`, `cards`).

## Field types

| Type | Value in `$data` | Notes |
|------|------------------|-------|
| `string` | string | Single line; `min`, `max`, `pattern`, `uppercase` |
| `plaintext` | string | Multi-line text without formatting |
| `text` | string | Rich text with bold, italic, code and links stored as inline Markdown; render with `@text()` |
| `markdown` | string | Rich text with headings and lists stored as Markdown; render with `@markdown()` |
| `html` | string | HTML purified on save, output with `{!! !!}`; prefer `markdown` |
| `number`, `range`, `slider` | number | `min`, `max`, `step` |
| `checkbox`, `switch` | bool | |
| `color` | `#rrggbb` / `#rrggbbaa` | |
| `date` | ISO date string | Render with `@localDate()` |
| `url` | string | `"rel": true` adds a `<field>-rel` property for `rel` attributes |
| `select`, `radio` | string | Require `options`: `[{"value": "a", "label": "A"}]`; `select` with `multiple` stores a list |
| `combobox` | string | Options plus free input |
| `autocomplete` | string | Data source via `api-type` (`GQL`), `query` with `_term_`, `list-key`, `item-title`, `item-value` |
| `image`, `audio`, `video`, `file`, `media` | `{id, type: "file"}` | Resolve with `cms($files, $data->field?->id ?? null)` |
| `images` | list of `{id, type: "file"}` | |
| `items` | list of objects | Nested fields in `item`; `min`/`max` limit the number of entries |
| `table` | list of rows, each a list of strings | `min`/`max` limit the rows |
| `map` | `{latitude, longitude, zoom}` | Location picked on a map |
| `hidden` | fixed value | Used for `"action"` classes |

Every field accepts `label` (defaults to the field name), `hint`, `required`, `default` and `translate` (`false` excludes it from AI translation, e.g. for codes, URLs and numbers).

### Repeated entries

```json
"features": {
    "type": "items",
    "hint": "Features shown as a list",
    "max": 6,
    "item": {
        "title": {"type": "string", "hint": "Name of the feature", "required": true},
        "file": {"type": "image", "label": "image", "hint": "Icon of the feature"},
        "url": {"type": "url", "hint": "Page with details", "rel": true}
    }
}
```

## Writing good fields

- Use the same field names as the bundled elements for the same meaning (`title`, `subtitle`, `text`, `buttons`, `file`, `files`, `background`, `layout`, `columns`). Editors and AI tools then treat them consistently.
- Every field gets a `hint` explaining what it is shown as, written for editors. AI agents use hints and descriptions to fill the fields correctly.
- Mark only what the view can't render without as `required`.
- Use `max` on strings shown in tight spaces (headlines, buttons) and on `items`.
- AI content refinement can only keep file references the schema defines, so add a file field for every image the view shows.

## Translation of labels and hints

The admin panel translates labels, hints, descriptions, groups and option labels from `public/i18n/<locale>.json` of the theme, registered automatically by `ThemeProvider`:

```json
{"de": {
    "Promotion": {"st": "Aktion"},
    "Highlighted offer with image and button": {"sd": "Hervorgehobenes Angebot mit Bild und Button"},
    "Name of the feature": {"fh": "Name des Features"}
}}
```

Contexts: `st` element name, `sd` element description, `sg` group, `fn` field name, `fh` field hint, `op` option label. Strings the admin or the base theme already translate don't need an entry.
