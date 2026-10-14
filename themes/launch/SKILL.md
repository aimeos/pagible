---
name: launch
description: Modern SaaS and startup landing page design with automatic light and dark mode, indigo glow accents, gradient headlines, product screenshots in browser frames, bento feature grids, pricing with Cashier checkout, changelog timeline, FAQ and testimonial walls.
license: MIT
metadata:
  author: Aimeos
---

# Launch Theme Design System

## Direction

Use a confident, precise and calm layout like the best product sites (Linear, Vercel, Stripe, Resend): lots of whitespace, one clear message per section and the product itself as the main visual. Lead with the outcome for the user, show the product right below the hero, then prove it with customer logos, features, numbers and testimonials, and close with pricing, questions and a call to action. Show real product screenshots or clean UI mockups; avoid generic stock photos of people pointing at screens, abstract 3D blobs and decorative illustrations without meaning.

## Foundations

- Use only the markup and classes supplied by `./theme/views/`.
- Use system fonts (Inter or Geist if installed) with tight letter-spacing on headings, monospace for dates and code, and the existing `--pico-*` variables.
- Keep page content within a `1200px` maximum width and text sections within `46rem`.
- Use zinc neutrals (`#09090B`, `#3F3F46`, `#71717A`, `#FAFAFA`) for text and surfaces, indigo (`#4F46E5`, `#A5B4FC` in dark mode) for links, primary buttons and glows, and the indigo to violet to cyan gradient only for accents in headlines (`*...*`), icon tiles and figures.
- Support light and dark mode; never place fixed light colors on themeable backgrounds.
- Use `0.75rem` corners, pill-shaped buttons and badges and thin borders instead of heavy shadows.

## Components

- Hero: centered text with a pill eyebrow for news ("New: …"), a headline of at most eight words with an accent in `*...*`, one sentence of text, a primary and a secondary action, and a wide 16:10 product screenshot in `files`.
- Logos: `cards` in the `badges` layout with company names and optional monochrome logos directly below the hero.
- Features: `cards` in three columns with six items form a bento grid; give the 1st and 4th card a longer text because they span two columns, and use white line icons as SVG images.
- Feature details: `image-text` with `grid-end`/`grid-start` alternating, a screenshot, an H2 with an accent, a sentence and three bullet points starting with a bold keyword.
- Figures: `cards` in the `figures` layout with four short numbers (uptime, customers, speed, rating).
- Testimonials: six quotes with name, role and company and round portraits; put the strongest quote first.
- Pricing: three or four plans with monthly and yearly prices (same `unit` values in every plan), a free plan, one highlighted plan with the badge "Most popular" and an enterprise plan with "Custom" and a "Talk to sales" link. For checkout, set `access`, `url` and price `id`, `kind`, `interval`, `currency` and `reference`. Follow with a comparison `table` and questions about billing.
- Changelog: a `vertical` timeline with dates as labels, the release as title and Markdown bullet lists with the changes, newest first.
- FAQ: six `questions` with short, direct answers about the free plan, billing, data storage and discounts.
- Call to action: one `cta` before the footer with a headline accent, a short sentence and two buttons.
- Footer: cards with a short product blurb first, then product, company and legal links; the layout adds the status link, notice and copyright.
- Product details: the `product` config adds the `SoftwareApplication` JSON-LD, the announcement bar, "Sign in" and "Get started" in the header and the status pill in the footer.

## Accessibility

- Preserve the skip link, semantic headings and visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast in both color schemes; use gradients only on large headline text and never for body text.
- Keep controls at least `2.5rem` high and respect `prefers-reduced-motion`.
- Describe screenshots by what they show in their alt texts, e.g. "Dashboard with uptime bars and a list of monitors".

## Content

Write short, concrete and to the reader as "you". Lead with the result ("Know about outages before your users do"), not the technology. Use numbers instead of adjectives, name the integrations and limits of each plan and state prices including the billing period. Avoid buzzwords like "revolutionary", "seamless" or "next-generation" and don't claim certifications, customers or ratings you don't have.
