---
name: mane
description: Light, editorial design for hair salons, colour studios and barbers with ivory, espresso and clay rose tones, serif display headings, arched image frames and pill buttons.
license: MIT
metadata:
  author: Aimeos
---

# Mane Theme Design System

## Direction

Use a calm, light and airy layout that feels like the salon itself: lots of white space, soft daylight photos and few, confident words. Put booking, the services with prices, the team and the address first, followed by what makes the salon special: results, specialities such as colour or curls, the care products and the ethos. Show real hair results, hands at work, the salon interior and the stylists as portraits; avoid stock photos with heavy make-up, retouched before and after collages, cluttered product shots and brand logos.

## Foundations

- Use only the markup and classes supplied by `./theme/views/`.
- Use system fonts (serif display headings, sans-serif body) and the existing `--pico-*` variables.
- Keep page content within a `1280px` maximum width.
- Use the ivory (`#F7F2EC`) background, espresso (`#1F1A17`) for text, filled buttons and the footer, clay rose (`#9A5A43`) for links, lines, icons and italic accents in headings, and blush (`#E9D5CB`) for the top bar, highlight boxes and badges.
- Use soft `1rem` corners, arched portrait images, pill buttons and hairline borders instead of shadows; labels are uppercase and letter-spaced.

## Components

- Hero: a bright full width `background` image in 16:9 with the subject in the right 55% and a light, calm left side (white walls, pale fabric) for the dark text without an overlay; it fills the hero from 1200px and stacks below the text on smaller screens, so keep the left 45% free of hair, faces and dark objects to meet WCAG AA (the home page adds the `zoom` animation), an eyebrow naming the salon and district, a short headline with an italic accent and two actions ("Book now" and "Services & prices"). Nest secondary pages (colour, bridal, careers) below a top-level page so the header keeps about six links next to "Book now".
- Figures and badges: cards in the `figures` layout for opening year, rating, team size and vegan colour, and in the `badges` layout with clay line icons for consultation, vegan colour, clear prices, head spa and the satisfaction promise.
- Signature services: a `pricing` element with three services, each with a photo, the starting price and what is included, the middle one highlighted with a badge.
- Before and after: a `before-after` element with the same client before and after a colour service; never use photos of different people.
- Price list: a table with prices by stylist level and `pricing` elements as price lists, one item per category, features as list items ending with a bold price and tags such as `bestseller` or `20 min` as inline code, followed by a text element with the consultation, patch test and long hair notes.
- Team: cards in four columns with arched 4:5 portraits, the name as title and a bold first line with the role and speciality.
- Bridal, gift cards and careers: `pricing` or `cards` elements with three items, one highlighted with a badge, and a horizontal timeline for the steps.
- Hair tips: `blog` pages below the hair tips page (status 2, hidden in navigation), each with an article, key figures, a vertical step timeline, questions and a call to action.
- Visit: a hero with the salon, a table with opening hours and last appointments, cards for the salon areas, a map with directions and questions about parking, public transport, accessibility and pets.
- Footer: salon, client and company links and a newsletter card; the layout adds the address, opening hours and notice from the salon config below it.
- Salon details: the `salon` config adds the salon JSON-LD, the announcement top bar, the "Book now" button in the header, the footer address, hours and notice and the action bar for phones.

## Accessibility

- Preserve the skip link, semantic headings and visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast for text and controls; use clay rose only on ivory or white, never on blush for small text.
- Keep controls at least `2.5rem` high and the action bar clear of the page content.
- Mention steps, step-free access, public transport, patch tests and allergies.

## Content

Write warmly and calmly, briefly and to the client as "you". Name services, techniques, durations and prices, and say what is included. Don't call the salon "the best in town" or use empty words like "luxurious experience" without a fact behind them. State the cancellation policy and patch test requirements clearly and kindly.
