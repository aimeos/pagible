---
name: manual
description: Software documentation design with automatic light and dark mode, a sticky three-column layout with grouped sidebar navigation, an "On this page" outline, previous and next links, dark code blocks with copy buttons, callouts, numbered steps, reference tables, version badge and repository link.
license: MIT
metadata:
  author: Aimeos
---

# Manual Theme Design System

## Direction

Write documentation like the best developer docs (Stripe, Tailwind CSS, Laravel, Starlight): each page answers one question, starts with what the reader will achieve and gets them to working code as fast as possible. Organize the content into four kinds of pages and don't mix them. Getting started pages take a new user from install to first success. Guides solve one practical task. Reference pages list every class, option and command. The changelog records what changed. Keep the design quiet so the text and code carry the page; don't use decorative images, stock photos or marketing claims inside the docs.

## Foundations

- Use only the markup and classes supplied by `./theme/views/` and the theme's `code`, `text`, `docs` and `main` views.
- Use system sans-serif and monospace fonts and the existing `--pico-*` variables.
- Use slate neutrals (`#0F172A`, `#334155`, `#64748B`, `#E2E8F0`) for text and borders and blue (`#2563EB`, `#60A5FA` in dark mode) for links and the active page; code blocks always stay dark.
- Support light and dark mode; never place fixed light colors on themeable backgrounds.
- Use `0.5rem` corners and thin borders instead of shadows.

## Structure

- Home (`page`): a hero with the one-line promise and "Get started" button, a short code sample, feature cards with white line icons, cards linking to the sections and a `cta`.
- Sections (`docs`) directly below home, e.g. "Docs", "API reference" and "Changelog"; each becomes a top-level header link and the root of its sidebar.
- Groups below a section, e.g. "Getting started" and "Guides", each with a short overview listing its pages; topics go below the groups. Don't nest deeper than this, the sidebar shows two levels.
- The order in the tree is the reading order; the previous and next links follow it.

## Components

- Headings: use `heading` elements with level 2 for sections and 3 for methods or sub-steps; they build the "On this page" outline and get anchor links. Never skip a level and keep them short ("Install the package", `add(name, data, options?)`).
- Text: short paragraphs, inline `code` for every identifier, file name and value, and lists for options and steps.
- Callouts: use GitHub alerts in text elements. Use `[!NOTE]` for context, `[!TIP]` for shortcuts, `[!IMPORTANT]` for required steps, `[!WARNING]` for risky actions and `[!CAUTION]` for data loss. Use at most two per page.
- Code: one `code` element per runnable snippet with the language set, at most about 15 lines, complete enough to copy and run. Shell commands use `bash` and contain no `$` prompt.
- Steps: a `vertical` timeline with "Step 1", "Step 2" … as labels for installation and setup sequences.
- Reference tables: `table` with a header row; columns like Option, Default, Description or Parameter, Type, Description, with values in backticks.
- Changelog: a `vertical` timeline with "version · date" labels, newest first, and Markdown bullets starting with **Added**, **Changed**, **Fixed** or **Breaking**.
- Project details: the `manual::project` config adds the version pill, the repository link, the license in the footer and the `SoftwareSourceCode` JSON-LD.

## Accessibility

- Preserve the skip link, semantic headings, `aria-current` on the active page and the visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast in both color schemes, including code tokens.
- Name links after their target ("Retries and backoff"), never "click here".

## Content

Address the reader as "you" and use the imperative for steps ("Install the package", "Add the connection URL"). State requirements and versions up front, show the expected result after each step and link to the next page at the end. Prefer one precise sentence over three vague ones, and avoid "simply", "just" and "easy". Keep the names and defaults the same as in the code.
