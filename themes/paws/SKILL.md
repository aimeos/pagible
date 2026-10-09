---
name: paws
description: Warm, friendly design for veterinary clinics and animal hospitals with navy, coral and cream colors, serif headings, rounded cards and soft wave edges.
license: MIT
metadata:
  author: Aimeos
---

# Paws Theme Design System

## Direction

Use a warm, calm layout that makes pet owners trust the clinic before their first visit. Put services, the team, opening hours, the emergency number, health plans, what happens at the first visit and the way to book first. Show happy, healthy animals with their people and gentle handling in the clinic, and keep it grown-up: no cartoon animals, paw-print wallpaper or childish colors.

## Foundations

- Use only the markup and classes supplied by `./theme/views/`.
- Use system fonts (classic serif fonts for headings) and the existing `--pico-*` variables.
- Keep page content within a `1280px` maximum width.
- Use a cream header and background, navy (`#16324F`) for the footer and dark sections, coral (`#C2412B`) for links, buttons and heading markers, and peach (`#F4C6A8`) only for fills, badges and text on dark backgrounds, never for text on light backgrounds.
- Use rounded corners, pill-shaped buttons, thin borders and soft shadows; dark sections show a soft dot pattern, the hero ends in a wave, hero images get an organic shape and headings use small paw markers.

## Components

- Hero: a peach hero with a short, warm headline, a tag line, the review rating, a "Book a visit" action and a photo of a happy pet, or a bright clinic photo as background on inner pages.
- Services: cards with a photo, a short text and a link to the service page; service pages name the responsible vet, link a matching guide and answer cost questions. Include cats, small pets, senior pets and emergencies.
- Figures and badges: cards in the `figures` layout for years, team size, same-day slots and reviews, and in the `badges` layout with line icons for a separate cat room, low-stress handling, emergencies and home visits.
- Team: cards with portrait photos of the same ratio, the name as title and focus plus the team member's own pets as text; the team size matches the figures.
- Health plans: a `pricing` element with monthly plans for young, adult and senior pets.
- First visit: a horizontal timeline from booking to the treatment plan.
- Pet care guides: `blog` pages below the guides page, each with an article, key figures, a vertical step timeline, questions and a call to action.
- Appointment: a contact form with selects for the animal, reason, new or existing client and preferred time, plus a map with opening hours, the emergency number and directions.
- New clients: what to bring, payment and pet insurance, emergencies and directions, followed by questions.
- Footer: opening hours with the emergency number, services, clinic links including careers, imprint and privacy, and contact.
- Clinic details: the `clinic` config adds the veterinary business JSON-LD, the action bar for phones, the top bar with the emergency number and the phone and booking buttons in the header.

## Accessibility

- Preserve the skip link, semantic headings and visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast for text and controls.
- Keep controls at least `2.5rem` high and the action bar clear of the page content.
- Mention step-free access, parking, languages and how to reach the clinic by public transport.

## Content

Write warmly, plainly and factually, to the owner and about the pet by name where it fits. Explain what happens, how long it takes and what it costs. Don't promise healing, don't compare with other clinics and avoid fear-based advertising. List the signs of a real emergency, point owners of sick pets to the phone instead of the form, and keep the imprint with the veterinary chamber and regulations complete.
