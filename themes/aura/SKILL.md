---
name: aura
description: Expressive portfolio and blog design with a drifting violet and pink aurora, glass surfaces, a floating pill header with availability status, bento project grid with spotlight cards, skill marquee, counting figures, scroll reveals, view transitions, light and dark mode, reading progress, post outline and author card.
license: MIT
metadata:
  author: Aimeos
---

# Aura Theme Design System

## Direction

Build a portfolio like the most polished designer and design engineer sites (rauno.me, paco.me, joshwcomeau.com, linear.app): one person whose craft shows in every detail. The site should feel alive and precise, with light that moves slowly behind glass and interactions that respond to the pointer, but the work and the writing always stay in front. Show finished projects with strong images and concrete results. Use no stock photos of people, no generic claims and no effects beyond those the theme provides.

## Foundations

- Use only the markup and classes supplied by `./theme/views/` and the theme's `article`, `blog`, `code`, `text`, `blog` layout and `main` views.
- Use the existing `--pico-*` variables; Inter is used when installed, otherwise the system fonts.
- Use cool neutrals (`#2A2838`, `#6A687C`, `#F7F6FB`) with violet (`#5B3DF5`, `#A193FF` in dark mode) and pink only as accents and gradients; code blocks always stay dark.
- Support light and dark mode; never place fixed light colors on themeable backgrounds.
- Use `1rem` corners, glass surfaces with thin borders and soft glows instead of hard shadows.
- Every animation must stop with `prefers-reduced-motion` and only animate `transform` and `opacity`.

## Structure

- Home (`page`): a `hero` with the avatar as image, role and city as subtitle, a title with one `*emphasized*` phrase, a one-sentence introduction and two buttons, `cards` with the `badges` layout for 8–12 skills, project `cards` with 4 image cards, `cards` with the `figures` layout for four numbers, a `blog` element with the `cards` layout and a limit of 3, a `testimonial` and a `cta`.
- Work (`page`): a level-1 `heading`, a short introduction, project `cards` in multiples of four for a complete bento grid, a `horizontal` timeline of the process and a `cta`.
- Writing (`page`): a level-1 `heading`, a note on what you write about and a `blog` with the `list` layout and this page as parent page.
- Posts (`blog`) below Writing, newest first in the tree. Each starts with an `article` element (title, a two-sentence lead and a colorful abstract cover), followed by `text`, level-2 `heading` and `code` elements.
- About (`page`): a level-1 `heading`, a personal text, a `vertical` timeline of jobs and a `testimonial`.
- Fill the `aura::profile` config on the home page: name, job title, company, location, a square photo, a one-sentence bio, the e-mail address, an availability status and profile links.

## Components

- Titles: emphasize at most one short phrase per hero, heading or CTA title with `*…*`; it becomes a gradient serif.
- Projects: one card per project with an image, the name as title, one sentence of what it achieved with a number and the discipline, stack and year as inline code pills on a separate line, e.g. `` `Design system` `React` `2025` ``.
- Skills: single-word or two-word badge titles without images or links.
- Figures: four short numbers ("12", "40+", "2.4M") with one-line labels; keep them plain so they can count up.
- Post lead: two sentences that state the problem and what the reader gets. Cards use it as introduction.
- Headings: short, specific level-2 headings; use at least two per post so the outline appears.
- Code: one `code` element per snippet with the language set, at most about 20 lines, real and runnable.
- Callouts: GitHub alerts (`[!NOTE]`, `[!TIP]`, `[!IMPORTANT]`) in text elements, at most one or two per post.
- Testimonials: two or three quotes with name and "Role, Company".

## Accessibility

- Preserve the skip link, semantic headings, the labelled mode switch and the visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast in both color schemes, including text on glass, gradients and code tokens.
- Write meaningful alt texts for project images and covers and name links after their target, never "click here".

## Content

Write in the first person and address the reader as "you". Be specific: name the tools, numbers and decisions. Open posts with the problem and end with what the reader can try. Keep titles plain and descriptive ("Animating gradients with @property"). Avoid buzzwords, "simply", "just", "seamless" and filler intros.
