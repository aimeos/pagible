---
name: folio
description: Personal developer blog and portfolio design with a narrow reading column, serif post text, warm neutrals and one green accent, light and dark mode with a toggle, writing grouped by year, reading time, post outline, author box, previous and next posts, project tiles with tech pills and an experience timeline.
license: MIT
metadata:
  author: Aimeos
---

# Folio Theme Design System

## Direction

Build a personal website like the best developer blogs and portfolios (leerob.com, overreacted.io, brittanychiang.com, antfu.me): one person, one voice, and the writing comes first. The site should feel like a well-kept notebook, quiet and fast, with nothing that competes with the text. Show what the person built and learned with concrete numbers and code. Use no stock photos of people, no marketing claims and no decorative effects.

## Foundations

- Use only the markup and classes supplied by `./theme/views/` and the theme's `article`, `blog`, `code`, `text`, `blog` layout and `main` views.
- Use the system sans-serif, serif and monospace fonts and the existing `--pico-*` variables.
- Use warm neutrals (`#2B2924`, `#6B665E`, `#E4E0D9`, `#FBFAF8`) and green (`#1F7A4D`, `#4FBF87` in dark mode) only for links and the first button; code blocks always stay dark.
- Support light and dark mode; never place fixed light colors on themeable backgrounds.
- Use `0.5rem` corners and thin borders instead of shadows.

## Structure

- Home (`page`): a `hero` with the avatar as image, the role as subtitle, a one-sentence introduction and two buttons, a `blog` element with the `list` layout and "Recent writing" as title, two-column project `cards` and a `cta` for contact.
- Writing (`page`): a short note on what you write about and a `blog` list of all posts with this page as parent page.
- Posts (`blog`) below Writing, newest first in the tree. Each starts with an `article` element (title, a two-sentence lead and a cover image), followed by `text`, level-2 `heading` and `code` elements.
- About, Projects, Uses and Now pages (`page`) start with a level-1 `heading`. About contains a `vertical` timeline of jobs, Uses lists tools by category and Now says what you're doing this month with the date of the last update.
- Fill the `folio::profile` config on the home page: name, job title, company, location, a square photo, a one-sentence bio, the e-mail address and profile links.

## Components

- Post lead: two sentences that state the problem and what the reader gets. The list cards use it as introduction.
- Headings: short, specific level-2 headings ("Backfill in batches"); level 3 only below them. Use at least two so the outline appears.
- Code: one `code` element per snippet with the language set, at most about 15 lines, real and runnable.
- Callouts: GitHub alerts (`[!NOTE]`, `[!TIP]`, `[!WARNING]`) in text elements, at most one or two per post.
- Projects: one card per project with the name as title, the repository or demo as link, one sentence of what it does with a number (stars, installs, users), and the stack as inline code pills on a separate line, e.g. `` `PHP` `Laravel` ``.
- Experience: timeline items with "2023 — now" labels, "Role, Company" titles, one sentence of impact with numbers and the stack as code pills.
- Figures: a `cards` element with the `figures` layout for three key numbers at most.

## Accessibility

- Preserve the skip link, semantic headings, the labelled mode switch and the visible `:focus-visible` outline.
- Maintain WCAG 2.2 AA contrast in both color schemes, including code tokens.
- Write meaningful alt texts for covers and name links after their target, never "click here".

## Content

Write in the first person and address the reader as "you". Be specific: name the versions, numbers and mistakes. Open posts with the problem, not with background, and end with what you would do next. Keep titles plain and descriptive ("Zero-downtime migrations on a 400M-row table"). Avoid buzzwords, "simply", "just" and filler intros.
