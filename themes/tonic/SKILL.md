---
name: tonic
description: Dark, moody design for cocktail bars, wine bars and pubs with brass and oxblood accents, Didone headings, Art Deco ornaments and sharp corners.
license: MIT
metadata:
  author: Aimeos
---

# Tonic Theme Design System

## Direction

Use a dark, intimate layout that feels like stepping down into the bar at night. Put booking a table, the drinks menu, the opening hours and the address first, followed by what makes the place special: the signature cocktails, the room, the music, the team and the craft behind the drinks. Show real drinks, the bar counter, the room in low light and the people behind the bar; avoid bright daylight photos, party crowds as the main visual, drunk guests and spirit brand logos on bottles.

## Foundations

- Use only the markup and classes supplied by `./theme/views/`.
- Use system fonts (Didone display headings, sans-serif body) and the existing `--pico-*` variables.
- Keep page content within a `1280px` maximum width.
- Use the night (`#14110F`) background, cream (`#EDE4D3`) for text, brass (`#C9A45C`) for links, buttons, prices, ornaments and italic accents in headings, and oxblood (`#8E2B3A`) only for the top bar and small tags.
- Use sharp corners, thin brass outlines with inset frames and double brass rules instead of shadows; labels and navigation are uppercase and letter-spaced.

## Components

- Hero: on the home page a full-bleed `background` image of the room with the `zoom` animation, an eyebrow naming the bar and district, a short headline with an italic accent and two actions ("Book a table" and "See the menu"); on other pages one portrait image in `files`, shown with an arched brass frame.
- Figures and badges: cards in the `figures` layout for opening year, signatures, ice or last call, and in the `badges` layout with brass line icons for ice, seasonal menus, walk-ins, low and no drinks and live music.
- Signatures: a `pricing` element with three cocktails, each with a photo, the price, the main ingredients and flavour notes as inline code pills, the middle one highlighted with a badge.
- Menu: `pricing` elements as menu boards, one item per section, features as list items ending with a bold price and tags such as `LOW`, `0.0`, `V` or `VG` as inline code, followed by a text element with the tag legend, the age limit and allergen note.
- What's on: cards with a photo, the event as title and a bold first line with day and time; on the events page a vertical timeline for the weekly program.
- Private events, masterclasses and gift cards: `pricing` elements with three items, one highlighted with a badge, and a contact form for requests.
- Journal: `blog` pages below the journal page, each with an article, key figures, a vertical step timeline, questions and a call to action.
- Visit: a hero with the entrance, a table with opening hours, kitchen hours and last orders, cards for the bar areas, a map with directions and questions about walk-ins, dress code, accessibility and getting home.
- Footer: bar, events and company links and a newsletter card; the layout adds the address, opening hours and notice from the bar config below it.
- Bar details: the `bar` config adds the bar JSON-LD, the announcement top bar, the "Book a table" button in the header, the footer address, hours and notice and the action bar for phones.

## Accessibility

- Preserve the skip link, semantic headings and visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast for text and controls; never put brass text on images without the hero overlay.
- Keep controls at least `2.5rem` high and the action bar clear of the page content.
- Mention steps, step-free alternatives, public transport, the age limit and allergens.

## Content

Write with confidence and a little mystery, briefly and to the guest as "you". Name spirits, ingredients, flavours and prices, and say when the music plays. Don't call the bar "the best in town" or use empty words like "curated" without a fact behind them. Mark low and alcohol-free drinks consistently, keep the age limit and encourage responsible drinking without lecturing.
