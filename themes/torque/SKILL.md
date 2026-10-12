---
name: torque
description: Workshop-grade design for car repair shops, garages, tyre centres and used car dealers with graphite, concrete and signal red tones, condensed uppercase headings, hazard stripes, vehicle spec strips and service coupons.
license: MIT
metadata:
  author: Aimeos
---

# Torque Theme Design System

## Direction

Use a clean, robust and trustworthy layout that feels like a well-run workshop: concrete floor, graphite tools, signal red markings and clear fixed prices. Put booking, the phone number, the services with starting prices, the price list, current offers and the used cars first, followed by what builds trust: binding quotes, warranty-safe servicing, old parts returned, the rating, the team and the address. Show mechanics at work, cars on lifts, brakes, tyres, diagnostic testers, electric cars charging and clean vehicles for sale; avoid dirty or chaotic workshops, crashed cars, racing imagery, stock handshakes and manufacturer logos used as decoration.

## Foundations

- Use only the markup and classes supplied by `./theme/views/`.
- Use system fonts (condensed uppercase display headings, sans-serif body, monospace for prices, durations, labels and specs) and the existing `--pico-*` variables.
- Keep page content within a `1280px` maximum width.
- Use the concrete (`#F3F4F2`) background, graphite (`#16191D`, `#2B3036`) for text, heroes, team cards and the footer, signal red (`#C8102E`) for links, filled buttons, badges, prices and accents in headings, and amber (`#F5A400`) only for hazard stripes, the announcement dot and star ratings.
- Use tight `0.25rem` corners, thick top borders on cards, slanted buttons, badges and hero image frames, square avatars and hazard stripe bars before section headings; headings and buttons are uppercase.

## Components

- Hero: a dark split hero with a single 4:3 workshop or vehicle photo in `files`, shown in a slanted frame with a red block behind it, an eyebrow naming the garage type, city and founding year, a short headline with an accent in `*...*` and two actions ("Book service" and "Price list"). Nest service pages (servicing, brakes and tyres, diagnostics, electric and hybrid) and careers below a top-level page so the header keeps about six links next to the phone number and "Book service".
- Figures and badges: cards in the `figures` layout for rating, founding year, number of lifts and warranty, shown as odometer read-outs, and in the `badges` layout with white line icons for fixed-price quotes, warranty-safe servicing, old parts returned, courtesy cars and the breakdown service.
- Services: cards in three columns with a photo, the service as title, a link to its page and a bold first line with the starting price followed by the duration as inline code.
- Used cars: a `pricing` element with one item per vehicle: a photo, the make and model as name, the price, the monthly rate in the text, features as inline code only (year, mileage, fuel, gearbox, power, one highlight) for the spec strip, "Book a test drive" as button and badges like "Certified" or "Price drop".
- Price list: `pricing` elements with one item per service group, features as list items with the duration as inline code and a bold price at the end, the most booked group highlighted, followed by a table and questions about quotes, parts and payment.
- Offers: a `pricing` element with service coupons without images, each with the offer price, the regular price and code in the text and what is included as features.
- Team: cards in four columns with 4:5 portraits, the name as title and a bold first line with the role and qualification.
- Tips: `blog` pages below the tips page (status 2, hidden in navigation), each with an article, key figures, a vertical step timeline, questions and a call to action.
- Contact: a hero with the workshop, a table with workshop, sales and breakdown hours, cards for the lounge, courtesy cars and key drop box, a map with directions and a contact form.
- Footer: service, customer and company links and a service reminder card; the layout adds the address, opening hours and notice from the garage config below it.
- Garage details: the `garage` config adds the automotive business JSON-LD, the top bar with the announcement and breakdown number, the phone number and "Book service" button in the header, the footer address, hours and notice and the action bar for phones.

## Accessibility

- Preserve the skip link, semantic headings and visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast for text and controls; use signal red only on white or concrete and white text only on red or graphite, never amber text on light backgrounds.
- Keep controls at least `2.5rem` high and the action bar clear of the page content.
- Write phone numbers in international format so they work as call links, and mention step-free access, courtesy cars and drop-off options.

## Content

Write plainly, confidently and to the customer as "you". Name services, durations and fixed prices including VAT, say what is included and when a part needs replacing. Explain technical terms in one short sentence. Don't use scare tactics, promise "the cheapest prices in town" or claim manufacturer approval you don't have. State the warranty, the quote policy and what happens before extra work clearly.
