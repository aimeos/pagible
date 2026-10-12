---
name: glow
description: Soft, luminous design for beauty salons, skin studios, nail and lash bars with blush, berry and aubergine tones, Didone display headings, capsule image frames and glowing pill buttons.
license: MIT
metadata:
  author: Aimeos
---

# Glow Theme Design System

## Direction

Use a soft, bright and calm layout that feels like a treatment room: blush light, rounded shapes, clear prices and few, warm words. Put booking, the treatment categories with prices, the membership, the team and the address first, followed by what makes the studio special: the skin analysis, the signature treatments, hygiene and the products. Show glowing skin, hands at work, close-ups of nails and lashes, calm treatment rooms and the therapists as portraits; avoid heavily retouched faces, before and after collages, medical imagery, cluttered product shots and brand logos.

## Foundations

- Use only the markup and classes supplied by `./theme/views/`.
- Use system fonts (Didone display headings, sans-serif body) and the existing `--pico-*` variables.
- Keep page content within a `1280px` maximum width.
- Use the blush white (`#FCF6F3`) background, aubergine (`#2E2230`) for text and the footer, berry (`#A2456B`) for links, filled buttons, the top bar, sparkles, icons and italic accents in headings, and blush (`#F3D3CF`) for glows, highlight boxes and badges.
- Use round `1.5rem` corners, capsule hero images, circular portraits, pill buttons and soft berry-tinted shadows; labels are in sentence case, never uppercase.

## Components

- Hero: a split hero with a single portrait image in `files` (2:3, the face or hands in the upper middle), shown as a capsule with a berry ring and a blush glow, an eyebrow naming the studio and district, a short headline with an italic accent and two actions ("Book now" and "Treatments & prices"). Nest treatment pages (facials, nails, lashes) and careers below a top-level page so the header keeps about six links next to "Book now".
- Figures and badges: cards in the `figures` layout for rating, opening year, team size and vegan products, and in the `badges` layout with berry line icons for skin analysis, vegan products, clear prices, hygiene and time for the client.
- Treatment categories: cards in three columns with a photo, the category as title and a bold first line with the starting price followed by the duration as inline code.
- Signature treatments: a `pricing` element with three treatments, each with a photo, the price and what is included, the middle one highlighted with a badge.
- Treatment menu: `pricing` elements as price lists, one item per category, features as list items with the duration as inline code and a bold price at the end, followed by a text element with the skin analysis and patch test notes.
- Membership: a `pricing` element with three monthly subscriptions, the middle one highlighted, and a table comparing regular and member prices.
- Team: cards in four columns with square portraits shown as circles, the name as title and a bold first line with the role.
- Gift cards and careers: `pricing` or `cards` elements with three items, one highlighted with a badge.
- Journal: `blog` pages below the journal page (status 2, hidden in navigation), each with an article, key figures, a vertical step timeline, questions and a call to action.
- Visit: a hero with the studio, a table with opening hours and last appointments, cards for the studio areas, a map with directions and questions about parking, public transport and accessibility.
- Footer: treatment, client and studio links and a newsletter card; the layout adds the address, opening hours and notice from the salon config below it.
- Salon details: the `salon` config adds the salon JSON-LD, the announcement top bar, the "Book now" button in the header, the footer address, hours and notice and the action bar for phones.

## Accessibility

- Preserve the skip link, semantic headings and visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast for text and controls; use berry only on blush white or white and white text only on berry or aubergine.
- Keep controls at least `2.5rem` high and the action bar clear of the page content.
- Mention step-free access, public transport, patch tests, allergies and pregnancy.

## Content

Write warmly and calmly, briefly and to the client as "you". Name treatments, durations and prices, and say what is included and how to prepare. Don't promise medical results, call the studio "the best in town" or use empty words like "pampering experience" without a fact behind them. State the cancellation policy and patch test requirements clearly and kindly.
