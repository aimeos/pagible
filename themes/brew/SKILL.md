---
name: brew
description: Warm, minimal design for cafés, coffee roasters and bakeries with paper, espresso and crema colors, serif headings, monospace labels and rounded corners.
license: MIT
metadata:
  author: Aimeos
---

# Brew Theme Design System

## Direction

Use a warm, unhurried layout that makes guests want to come in. Put the menu, the opening hours, the address and the way to order ahead first, followed by what makes the place special: the coffees on offer, the bakery, the people and the farms. Show real drinks, pastries, the room and the team; avoid generic coffee bean wallpapers, stock photos of people holding takeaway cups as the main visual and brand logos on bags or cups.

## Foundations

- Use only the markup and classes supplied by `./theme/views/`.
- Use system fonts (serif headings, monospace labels) and the existing `--pico-*` variables.
- Keep page content within a `1280px` maximum width.
- Use a paper (`#F7F3EC`) background, espresso (`#2A211C`) for text, the top bar, the footer and dark sections, roast (`#8A4B2A`) for links, buttons and italic accents in markdown headings, and crema (`#C8A165`) only for dots, separators, badges and text on dark backgrounds, never for text on light backgrounds.
- Use rounded cards, pill buttons and dashed separators instead of heavy shadows; labels, prices and tags are monospace and uppercase.

## Components

- Hero: a paper hero with a monospace eyebrow naming the place and city, a short headline, two actions ("See the menu" and "Order ahead") and one portrait image in `files`, shown with an arched top.
- Figures and badges: cards in the `figures` layout for opening year, farms, fermentation hours or opening time, and in the `badges` layout with roast line icons for roasting, direct trade, the bakery, reusable cups and low waste.
- Coffees: cards with a photo, the origin as title and a text with a bold first line for process, altitude and price, followed by the tasting notes as inline code pills.
- Menu: `pricing` elements as menu boards, one item per section, features as list items ending with a bold price and dietary tags as inline code, followed by a text element with the tag legend and allergen note.
- Subscriptions, classes, catering and pre-order boxes: `pricing` elements with three items, the middle one highlighted with a badge.
- Process: horizontal timelines for farm to cup, class afternoons or ordering.
- Journal: `blog` pages below the journal page, each with an article, key figures, a vertical step timeline, questions and a call to action.
- Visit: a hero with the terrace or room, a table with opening hours, cards for the seating areas, a map with directions and questions about accessibility, dogs and parking.
- Footer: café, coffee and company links and a newsletter card; the layout adds the address and opening hours from the café config below it.
- Café details: the `cafe` config adds the café JSON-LD, the announcement top bar, the "Order ahead" button in the header, the footer address and hours and the action bar for phones.

## Accessibility

- Preserve the skip link, semantic headings and visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast for text and controls.
- Keep controls at least `2.5rem` high and the action bar clear of the page content.
- Mention step-free access, toilets, dogs, public transport and allergens.

## Content

Write warmly, concretely and briefly, to the guest as "you". Name origins, farms, ingredients and prices, and say when things are made. Don't call the coffee "the best in town" or use empty words like "artisanal" without a fact behind them. Mark vegetarian, vegan, gluten-free and nut dishes consistently and keep the allergen note.
