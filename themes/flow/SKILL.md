---
name: flow
description: Clean, trustworthy design for plumbers, heating engineers and gas fitters with deep petrol, water blue and copper accents, rounded headings, water drop markers and wave backgrounds.
license: MIT
metadata:
  author: Aimeos
---

# Flow Theme Design System

## Direction

Use a clean, calm layout that makes homeowners and landlords trust the company with their heating, water and gas. Put services, fixed prices, certifications, the emergency number and the way to a quote first, and show real numbers instead of slogans.

## Foundations

- Use only the markup and classes supplied by `./theme/views/`.
- Use system fonts (rounded system fonts for headings) and the existing `--pico-*` variables.
- Keep page content within a `1280px` maximum width.
- Use deep petrol (`#0A2B3B`) for header, footer and dark sections, water blue (`#0B7A9E`) for links, buttons and markers, and copper (`#E07B39`) only for fills, badges and the emergency bar, never for text on light backgrounds.
- Use rounded corners, thin borders and soft shadows; dark sections show the wavy water-line pattern and headings use water drop markers.

## Components

- Emergency bar: the emergency number from the `business` config at the top of every page.
- Hero: a plumber, heat pump or finished bathroom photo as background with a short headline, a copper tag line, a wave edge at the bottom and a "Get a fixed price" action.
- Services: cards with a photo, a short text and a link to the service page.
- Figures and badges: cards in the `figures` layout for years, jobs, response time and reviews, and in the `badges` layout for certifications.
- Prices: a `pricing` element with one-time fixed prices for typical jobs.
- Process: a horizontal timeline from the first contact to the handover.
- Projects: `blog` pages below the projects page, each with an article, key figures, a before/after comparison of same-sized photos, a vertical step timeline and a slideshow.
- Contact: a contact form with a job type select, postcode and attachments for photos.
- Business details: the `business` config adds the local business JSON-LD, the emergency contact point and the call button for phones.

## Accessibility

- Preserve the skip link, semantic headings and visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast for text and controls.
- Keep controls at least `2.5rem` high and the sticky call button clear of the page content.

## Content

Write plainly and concretely. Name places, response times, prices, subsidies and qualifications. For gas emergencies, always tell readers to leave the building and call the gas emergency number first. Avoid generic claims like "reliable service" without evidence.
