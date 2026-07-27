---
name: estate
description: Editorial property theme with image-led layouts, crisp typography, a restrained red accent, and clear property discovery and contact paths.
license: MIT
metadata:
  author: Aimeos
---

# Estate Theme

## Direction

Estate is a premium property-marketing theme built around large photography, editorial spacing, clear listing facts, and restrained conversion paths.

## Sources of truth

- Use `schema.json` for theme tokens, page types, and content fields.
- Use `public/cms.css` for the general visual language.
- Use `views/property.blade.php` with `public/property.css` for property details.
- Use `views/properties.blade.php`, `views/property-item.blade.php`, and `public/properties.css` for property lists.
- Reuse generic Pagible content views and assets instead of copying them into property-specific files.

## Visual language

- Background: white `#FFFFFF` with quiet neutral sections.
- Primary text and structural color: charcoal `#242424`.
- Accent and interactive color: signal red `#D00000`.
- Display typography: editorial serif using the configured `--pico-font-family-display`.
- Body typography: clean sans-serif using the configured `--pico-font-family-sans-serif`.
- Use square edges, fine neutral rules, and minimal shadow.
- Use gradients only for legibility over photography or intentional image placeholders.
- Keep interfaces image-led, spacious, and readable, with property facts presented as a clear hierarchy.

## Property components

- Property lists support only card and list layouts.
- Keep list filters appropriate for normal agency-sized portfolios.
- Use database pagination for unfiltered lists and preserve database ordering when filtering property content in memory.
- Property cards show one image; property details use the slideshow gallery.
- Keep price, status, facts, documents, contact, and structured listing data in the property templates.
- Use the existing CMS contact form and pass the source property URL.

## Implementation rules

- Keep all property-specific PHP, Blade, CSS, translations, tests, and schema entries in `themes/estate`.
- Keep property detail and property list CSS separate.
- Translate all frontend and admin strings.
- Do not use `@php` or `@endphp` in Blade templates.
- Prefer explicit Blade and PHP over helper functions, DTOs, registries, or compatibility layers.
- Use `property-<prop>` classes when individual property values need styling.
- Preserve responsive layouts, keyboard focus, semantic HTML, reduced-motion behavior, and print support.

## Avoid

- Do not introduce an unrelated black-and-gold fashion aesthetic.
- Do not remove rounding or gradients globally when the implemented component uses them intentionally.
- Do not move property filtering into generic blog actions.
- Do not add database-specific JSON filtering or search-package coupling at theme level.
