---
name: solar
description: Bright, optimistic design for solar, battery storage and heat pump consultants and installers with forest green, leaf green and sun yellow accents, geometric headings, sun markers and solar cell backgrounds.
license: MIT
metadata:
  author: Aimeos
---

# Solar Theme Design System

## Direction

Use a bright, optimistic layout that makes homeowners and businesses trust the company with their energy transition. Put services, package prices, yields and savings, certifications, subsidies and the way to a free consultation first, and show real numbers instead of slogans.

## Foundations

- Use only the markup and classes supplied by `./theme/views/`.
- Use system fonts (geometric sans-serif fonts for headings) and the existing `--pico-*` variables.
- Keep page content within a `1280px` maximum width.
- Use forest green (`#12302A`) for header, footer and dark sections, leaf green (`#1F7A4D`) for links and buttons, and sun yellow (`#F5B82E`) only for fills, markers, badges and text on dark backgrounds, never for text on light backgrounds.
- Use moderately rounded corners, thin borders and soft shadows; dark sections show the solar cell grid pattern, the hero ends in a sloped roof edge and headings use sun markers.

## Components

- Service bar: the fault service number from the `business` config at the top of every page.
- Hero: a solar roof, battery or heat pump photo as background with a short headline, a sun yellow tag line, a sloped edge at the bottom and a "Get a free consultation" action.
- Services: cards with a photo, a short text and a link to the service page.
- Figures and badges: cards in the `figures` layout for installed kWp, systems, self-sufficiency and reviews, and in the `badges` layout for certifications.
- Prices: a `pricing` element with one-time package prices for typical systems.
- Process: a horizontal timeline from the consultation and site survey to the grid connection.
- Projects: `blog` pages below the projects page, each with an article, key figures, a before/after comparison of same-sized photos, a vertical step timeline and a slideshow.
- Contact: a contact form with a project type select, postcode, annual consumption and attachments for photos of the roof, meter cabinet and boiler room.
- Business details: the `business` config adds the local business JSON-LD, the fault service contact point and the call button for phones.

## Accessibility

- Preserve the skip link, semantic headings and visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast for text and controls.
- Keep controls at least `2.5rem` high and the sticky call button clear of the page content.

## Content

Write plainly and concretely. Name places, system sizes in kWp and kWh, yields, payback periods, prices, subsidies and qualifications. Base savings on stated assumptions such as consumption and electricity price. Avoid generic claims like "save money with the sun" without numbers.
