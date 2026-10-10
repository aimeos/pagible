---
name: counsel
description: Classic, trustworthy design for law firms, attorneys and notaries with navy, ivory and brass colors, serif headings, sharp corners and fine gold rules.
license: MIT
metadata:
  author: Aimeos
---

# Counsel Theme Design System

## Direction

Use a calm, authoritative layout that lets clients trust the firm before the first call. Put the practice areas, the attorneys, the way to book a consultation, the costs and the urgent hotline first, followed by proof: years, matters, certified specialists and selected results. Show real people, offices and the city; avoid clichés like gavels on every page, handshake stock photos as the main visual or dramatic courtroom scenes.

## Foundations

- Use only the markup and classes supplied by `./theme/views/`.
- Use system fonts (classic serif fonts for headings) and the existing `--pico-*` variables.
- Keep page content within a `1280px` maximum width.
- Use an ivory background and header, navy (`#0E2A47`) for heroes, the footer and dark sections, brass (`#7D5F2A`) for links and accents on light backgrounds and gold (`#C6A15B`) only for rules, the first button and text on dark backgrounds, never for text on light backgrounds.
- Use sharp corners, hairline borders and thin gold rules instead of shadows; dark sections show a faint diagonal pinstripe, eyebrows are uppercase and tracked, portraits are grayscale until hovered.

## Components

- Hero: a navy hero with an eyebrow naming the firm and city, a short headline, two actions ("Book a consultation" and the practice areas) and a city skyline or building photo as background.
- Figures and badges: cards in the `figures` layout for founding year, matters, transaction volume and specialists, and in the `badges` layout with brass line icons for partner-led work, specialists, languages and confidentiality.
- Practice areas: four-column cards with a photo, a short text and a link; each practice area page names the responsible attorney, lists typical matters, answers cost and deadline questions and links a matching insight.
- Attorneys: four-column cards with portraits of the same ratio, the name as title and role, focus, certifications and languages as text; the team size matches the figures.
- Results: figure-style cards with the amount or outcome as title, always followed by the note that prior results don't guarantee a similar outcome.
- Fees: a `pricing` element with the initial consultation, hourly rates and fixed fees, prices plus VAT.
- Process: a horizontal timeline from contact to resolution.
- Insights: `blog` pages below the insights page, each with an article, key figures, a vertical step timeline, questions and a call to action that reminds readers it is no legal advice.
- Consultation: a contact form with selects for the practice area and client type, a field for other parties for the conflict check and the matter in brief, plus the fees and a map with office hours, the hotline and directions.
- Footer: office hours with the hotline, practice areas, firm links including careers, imprint and privacy, contact and the disclaimer.
- Firm details: the `firm` config adds the legal business JSON-LD, the action bar for phones, the top bar with the hotline, the consultation button in the header and the footer disclaimer.

## Accessibility

- Preserve the skip link, semantic headings and visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast for text and controls.
- Keep controls at least `2.5rem` high and the action bar clear of the page content.
- Mention step-free access, parking, languages and how to reach the office by public transport.

## Content

Write precisely, plainly and without legalese, to the client as "you". Explain deadlines, the next steps and what they cost. Don't promise outcomes, don't call the firm "the best" or compare it with others, and don't use fear-based advertising. Keep the disclaimer that the website is no legal advice, that contacting the firm creates no client relationship and that confidential information shouldn't be sent before a mandate is confirmed. Keep the imprint with the bar association, professional title, regulations and liability insurance complete.
