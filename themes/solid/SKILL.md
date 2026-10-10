---
name: solid
description: Dependable design for builders, renovation companies and general contractors with charcoal surfaces, safety yellow accents, condensed bold headings, square edges and photo-led sections.
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
- Keep all corners square and use thin borders with soft shadows; even sections are white.
- Use sentence case for headings, menu and buttons; uppercase only for small labels like tag lines, breadcrumbs and timeline labels.
- Avoid hazard stripes, blueprint grids, hard hats, cones and offset shadows; they look like a building site sign rather than a trusted builder.

## Components

- Header: the office phone from the `business` config next to the last menu item, which is shown as a yellow quote button (link it to the contact page).
- Hero: a site or finished house photo as background, kept visible on the right, with a "what and where" headline, a yellow tag line, a "Get a free quote" and a projects action.
- Services: cards with a photo, a short text and a linked title; the whole card is clickable.
- Figures and badges: cards in the `figures` layout for years, finished projects, guarantee and reviews, placed directly after the hero as a floating strip, and in the `badges` layout for memberships and guarantees (FMB, TrustMark, building control, insurance-backed guarantee), shown as tiles with a shield icon when they have no logo.
- Process: a horizontal timeline from site visit to handover with the typical duration of each step as label.
- Prices: a `pricing` element with typical price ranges for the main project types, matching the project pages and service texts.
- Projects: `blog` pages below the projects page, each with an article, key figures, a before/after comparison of same-sized photos, a vertical phase timeline and a slideshow.
- Contact: a contact form with a project type select and attachments for photos or drawings.
- Business details: the `business` config adds the local business JSON-LD and the call button for phones.

## Accessibility

- Preserve the skip link, semantic headings and visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast for text and controls.
- Keep controls at least `2.5rem` high and the sticky call button clear of the page content.

## Content

Write plainly and concretely. Name places, durations, prices and guarantees. Avoid generic claims like "quality craftsmanship" without evidence.
