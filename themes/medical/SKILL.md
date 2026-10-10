---
name: medical
description: Calm, clean design for medical and dental practices with navy, teal and mint accents, a white header, rounded cards, plus markers and soft dot backgrounds.
license: MIT
metadata:
  author: Aimeos
---

# Medical Theme Design System

## Direction

Use a calm, clean layout that makes patients feel welcome and well informed before their first visit. Put treatments, the team, opening hours, insurance and self-pay prices, what to expect at an appointment and the way to book first, and explain instead of promising.

## Foundations

- Use only the markup and classes supplied by `./theme/views/`.
- Use system fonts (humanist sans-serif fonts for headings) and the existing `--pico-*` variables.
- Keep page content within a `1280px` maximum width.
- Use a white header, navy (`#0F2D3A`) for the footer and dark sections, teal (`#0A7782`) for links, buttons and heading markers, and mint (`#8DD8C8`) only for fills, badges and text on dark backgrounds, never for text on light backgrounds.
- Use rounded corners, pill-shaped buttons, thin borders and soft shadows; dark sections show a soft dot pattern, the hero ends in a gentle curve and headings use plus markers.

## Components

- Hero: a bright photo of a consultation or the practice as background with a short, reassuring headline, a mint tag line, a curved edge at the bottom and a "Book an appointment" action.
- Treatments: cards with a photo, a short text and a link to the treatment page; treatment pages name the responsible dentist, link a matching guide and answer cost and insurance questions. Include anxious patients and dental emergencies.
- Figures and badges: cards in the `figures` layout for years, team size, waiting time and reviews, and in the `badges` layout with line icons for new patients, insurances, accessibility, languages and memberships.
- Team: cards with portrait photos of the same ratio, the name as title and qualifications and focus as text; the team size matches the figures.
- Prices: a `pricing` element with typical prices for self-pay services, always with a note on cost plans.
- First visit: a horizontal timeline from booking to the treatment plan.
- Patient guides: `blog` pages below the guides page, each with an article, key figures, a vertical step timeline, questions and a call to action.
- Appointment: a contact form with selects for the reason, new or existing patient, insurance and preferred time, plus a map with opening hours and directions.
- Patient info: what to bring, insurance and costs, emergencies and directions, followed by questions.
- Footer: opening hours with the emergency number, treatments, practice links including careers, imprint and privacy, and contact.
- Practice details: the `practice` config adds the medical business JSON-LD, the call button for phones, the top bar with telephone and emergency number and the booking button in the header.

## Accessibility

- Preserve the skip link, semantic headings and visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast for text and controls.
- Keep controls at least `2.5rem` high and the sticky call button clear of the page content.
- Mention step-free access, languages and how to reach the practice by public transport.

## Content

Write warmly, plainly and factually. Explain what happens, how long it takes, who pays and what it costs. Don't promise healing or results, don't compare with other practices and avoid fear-based or before/after advertising, as health advertising rules in many countries forbid it. Point patients in pain to the phone instead of the form, and keep the imprint with the professional chamber and regulations complete.
