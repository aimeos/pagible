---
name: volt
description: Bright, trustworthy design for electricians and electrical contractors with deep navy, electric blue and amber accents, bold headings, rounded corners and circuit-line backgrounds.
license: MIT
metadata:
  author: Aimeos
---

# Volt Theme Design System

## Direction

Use a clean, technical layout that makes homeowners feel safe. Put services, fixed prices, certifications, the emergency number and the way to a quote first, and show real numbers instead of slogans.

## Foundations

- Use only the markup and classes supplied by `./theme/views/`.
- Use system fonts and the existing `--pico-*` variables.
- Keep page content within a `1280px` maximum width.
- Use deep navy (`#0B1530`) for header, footer and dark sections, electric blue (`#1F6FEB`) for links, buttons and markers, and amber (`#FFB020`) only for fills, badges and the emergency bar, never for text on light backgrounds.
- Use rounded corners, thin borders and soft shadows; dark sections show the circuit-line pattern.

## Components

- Emergency bar: the emergency number from the `business` config at the top of every page.
- Hero: an electrician or finished installation photo as background with a short headline, an amber tag line and a "Get a fixed price" action.
- Services: cards with a photo, a short text and a link to the service page.
- Figures and badges: cards in the `figures` layout for years, jobs, response time and reviews, and in the `badges` layout for certifications.
- Prices: a `pricing` element with one-time fixed prices for typical jobs.
- Process: a horizontal timeline from the first contact to the certificate.
- Jobs: `blog` pages below the jobs page, each with an article, key figures, a before/after comparison of same-sized photos, a vertical step timeline and a slideshow.
- Contact: a contact form with a job type select, postcode and attachments for photos.
- Business details: the `business` config adds the local business JSON-LD, the emergency contact point and the call button for phones.

## Accessibility

- Preserve the skip link, semantic headings and visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast for text and controls.
- Keep controls at least `2.5rem` high and the sticky call button clear of the page content.

## Content

Write plainly and concretely. Name places, response times, prices and certifications. Avoid generic claims like "reliable service" without evidence.
