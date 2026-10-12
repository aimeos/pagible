---
name: grove
description: Natural design for gardeners, landscapers, garden designers and garden maintenance companies with moss green, cream and terracotta tones, serif headings, leaf-shaped hero photos, curved section edges and leaf details.
license: MIT
metadata:
  author: Aimeos
---

# Grove Theme Design System

## Direction

Use a calm, warm and trustworthy layout that feels like a well-kept garden: cream paper, deep greens, terracotta pots and soft curves. Put the free quote, the phone number, the services, recent projects and the care plans first, followed by what builds trust: insurance and accreditations, the plant guarantee, the same gardener on every visit, reviews, the team and the areas covered. Show finished gardens, lush borders, lawns, patios, ponds, planting hands and gardeners at work; avoid bare soil without context, brown dead lawns, chemical sprays, heavy machinery and stock handshakes.

## Foundations

- Use only the markup and classes supplied by `./theme/views/`.
- Use system fonts (serif display headings with italic accents, sans-serif body) and the existing `--pico-*` variables.
- Keep page content within a `1280px` maximum width.
- Use the cream (`#F6F2EA`, `#FFFDF8`) background, forest green (`#1F3A2C`, `#2D4E3C`) for headings, heroes, the highlighted care plan and the footer, moss green (`#3F6B3A`) for links, filled buttons and accents in headings, terracotta (`#A0532D`) for eyebrows and leaf marks, and chartreuse (`#C5D86D`) only on dark green.
- Use soft `1rem` corners, pill-shaped buttons, tags and badges, leaf-shaped hero images, arched team images, round avatars, leaf marks before section headings and curved edges below heroes and above the footer.

## Components

- Hero: a dark green split hero with a single 4:5 garden photo in `files`, shown in a leaf-shaped frame, an eyebrow naming the services and region, a short headline with an accent in `*...*` and two actions ("Get a free quote" and "See our projects"). Nest service pages (garden design, landscaping, garden maintenance, lawns and hedges) below a top-level page so the header keeps about six links next to the phone number and "Free quote".
- Figures and badges: cards in the `figures` layout for rating, gardens in care, founding year and plant guarantee, and in the `badges` layout with white line icons for insurance, accreditation, plant guarantee, the same gardener and peat-free gardening.
- Services: cards in three columns with a photo, the service as title, a link to its page and a bold first line as eyebrow ("Design", "Build", "Care").
- Process: a horizontal `timeline` with four steps from the free garden visit to regular care.
- Projects: cards in three columns with a photo, the project as title and the place and year as inline code, plus a `before-after` element with two photos of the same size.
- Care plans: a `pricing` element with three plans priced per visit, how often in the text, features as list items with inline code for the season or frequency, the most popular plan highlighted, followed by a table of seasonal jobs and questions about contracts, access and green waste.
- Team: cards in four columns with 4:5 portraits, the name as title and a bold first line with the role.
- Journal: `blog` pages below the journal page (status 2, hidden in navigation), each with an article, key figures, a vertical step timeline, questions and a call to action.
- Contact: a hero, cards for the areas covered, a map with directions and a contact form; a hidden quote page with a form asking for postcode, service, garden size and budget.
- Footer: service, customer and company links and a seasonal newsletter card; the layout adds the address, office hours, service areas and notice from the garden config below it.
- Garden details: the `garden` config adds the local business JSON-LD with the service area, the top bar with the announcement and towns served, the phone number and "Free quote" button in the header, the footer address, hours, areas and notice and the action bar for phones.

## Accessibility

- Preserve the skip link, semantic headings and visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast for text and controls; use moss green and terracotta only on cream and use chartreuse and cream text only on dark green, never chartreuse on light backgrounds.
- Keep controls at least `2.5rem` high and the action bar clear of the page content.
- Write phone numbers in international format so they work as call links, and describe garden photos specifically in their alt texts.

## Content

Write warmly, plainly and to the customer as "you". Name the services, what is included, how often visits happen and prices per visit or square metre including VAT. Use plant and season names where they help, explain gardening terms in one short sentence and mention the towns you cover. Don't promise "maintenance-free" gardens or claim accreditations you don't have. State insurance, guarantees, the quote policy and what happens with green waste clearly.
