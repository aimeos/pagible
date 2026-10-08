---
name: solid
description: Dependable design for builders, renovation companies and general contractors with charcoal surfaces, safety yellow accents, condensed bold headings, square edges and a faint blueprint grid.
license: MIT
metadata:
  author: Aimeos
---

# Solid Theme Design System

## Direction

Use a sturdy, no-nonsense layout that builds trust with homeowners. Put services, finished projects, the build process and the way to a quote first, and show real numbers instead of slogans.

## Foundations

- Use only the markup and classes supplied by `./theme/views/`.
- Use system fonts with the condensed display stack for headings and the existing `--pico-*` variables.
- Keep page content within a `1280px` maximum width.
- Use charcoal (`#1F2328`) for text, header, footer and borders, and safety yellow (`#F2B705`) only for fills, bars, markers and buttons, never for text on light backgrounds.
- Keep all corners square and use 2px charcoal borders with offset shadows instead of soft shadows.
- The page background shows a faint blueprint grid; even sections are white.

## Components

- Hero: a site or finished house photo as background with a short uppercase headline, a yellow tag line and a "Get a free quote" action.
- Services: cards with a photo, a short text and a link to the service page.
- Figures: cards in the `figures` layout for years, finished projects, guarantee and reviews.
- Process: a horizontal timeline from site visit to handover.
- Projects: `blog` pages below the projects page, each with an article, key figures, a before/after comparison of same-sized photos, a vertical phase timeline and a slideshow.
- Contact: a contact form with a project type select and attachments for photos or drawings.
- Business details: the `contractor` config adds the local business JSON-LD and the call button for phones.

## Accessibility

- Preserve the skip link, semantic headings and visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast for text and controls.
- Keep controls at least `2.5rem` high and the sticky call button clear of the page content.

## Content

Write plainly and concretely. Name places, durations, prices and guarantees. Avoid generic claims like "quality craftsmanship" without evidence.
