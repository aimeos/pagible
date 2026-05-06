---
name: pagible
description: Dark, immersive design with blue-purple gradient accents, sharp edges, and a deep navy background for a modern tech aesthetic.
license: MIT
metadata:
  author: Aimeos
---

# Pagible Theme Design System

## Mission
You are an expert frontend developer for the Pagible theme.
Follow these guidelines to produce visually consistent, accessible markup and styles.

## Brand
Dark, immersive, and modern. Deep navy background (#080040) with blue (#0868D0) and purple (#B008C8) accent gradients. Sharp edges (0 border-radius), translucent surfaces, and radial gradient backgrounds. Built on Pico CSS with `--pico-*` custom property overrides.

## Style Foundations
- Visual style: dark, immersive, gradient-driven. Radial gradient backgrounds on body, images, videos, code blocks, and slideshows
- Typography: Font="Helvetica Neue", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, etc. | Weights: normal (h1-h4, body) | Sizes: h1=2.5x font-size (hero), body=1rem, small=0.875rem | line-height: body=1.6, hero paragraphs=1.75
- Color tokens: --pico-color=#FFFFFFD0, --pico-background-color=#080040, --pico-muted-color=#FFFFFFA0, --pico-muted-border-color=#FFFFFF20, --pico-muted-background-color=#00000040, --pico-contrast=#FFFFFFE0, --pico-contrast-hover=#FFFFFF, --pico-contrast-inverse=#000000, --pico-primary=#0868D0, --pico-primary-background=#0868D080, --pico-secondary=#B008C8, --pico-secondary-background=#B008C880 | Headings: #FFFFFF (contrast-hover)
- Border radius: 0 (default for all elements), 2rem (pill buttons and nav CTA), 1rem (badges), 9999px (pricing toggle)
- Max widths: 1200px (header/container/main), 960px (blog content), 800px (hero text-only) | Breakpoints: 576px, 768px, 992px
- Gradient accents: --cms-accent-gradient (radial blue top-right + purple bottom-left, full spread), --cms-accent-gradient-tight (same, 66% spread). Applied to images, videos, code blocks, slideshows, and search dialog
- Body background: multiple large radial gradients alternating blue/purple at different vertical positions
- Components: hero, cards (1->2->3 col grid, alternating background), blog (featured+list), questions/FAQ (details accordion, 2-col at 768px), contact form, toc, slideshow (swiffy-slider), pricing (toggle, highlight), article, search dialog, docs sidebar (20rem, sticky), image-text (float/grid layouts), bottom footer
- Buttons (.btn): pill-shaped (2rem radius), gradient background (90deg, primary to secondary), white text, arrow-circle icon after

## Accessibility
WCAG 2.2 AA. Skip-to-content link. Focus: 1px solid contrast (#FFFFFFE0). Min touch target: adequate button padding. prefers-reduced-motion respected (slideshow animations). Semantic HTML (nav aria-label, dialog, details). RTL support with [dir="rtl"] overrides.

## Writing Tone
clear, technical

## Rules: Do
- Use --pico-* custom properties for all colors, spacing, and typography
- Use 0 border-radius for cards, containers, inputs, and general elements
- Use 2rem border-radius only for pill buttons (.btn) and nav CTA links
- Use gradient backgrounds (--cms-accent-gradient or --cms-accent-gradient-tight) for media elements (images, videos, code blocks, slideshows)
- Use translucent backgrounds (--pico-muted-background-color: #00000040) for elevated surfaces
- Use --pico-contrast (#FFFFFFE0) for link colors and interactive text
- Use linear-gradient(90deg, primary, secondary) for primary action buttons

## Rules: Don't
- Don't use colors outside the defined palette; all colors are translucent white/alpha variants on dark backgrounds
- Don't use border-radius values other than 0 (sharp), 2rem (pill), or 9999px (toggle) — no 0.5rem or 1rem rounded corners
- Don't use solid white (#FFFFFF) backgrounds; all surfaces must be dark or translucent
- Don't use box-shadow for elevation; use border (1px solid --pico-muted-border-color) and background-color instead
- Don't hard-code colors; reference --pico-* tokens (exception: gradient buttons, body background radials)
- Don't add light-mode styles; this is a dark-only theme (:root:not([data-theme=dark]))

## Expected Behavior
- Follow the foundations first, then component consistency.
- When uncertain, prioritize accessibility and clarity over novelty.
- Provide concrete defaults and explain trade-offs when alternatives are possible.
- Keep guidance opinionated, concise, and implementation-focused.

## Guideline Authoring Workflow
1. Restate the design intent in one sentence before proposing rules.
2. Define tokens and foundational constraints before component-level guidance.
3. Specify component anatomy, states, variants, and interaction behavior.
4. Include accessibility acceptance criteria and content-writing expectations.
5. Add anti-patterns and migration notes for existing inconsistent UI.
6. End with a QA checklist that can be executed in code review.

## Required Output Structure
When generating design-system guidance, use this structure:
- Context and goals
- Design tokens and foundations
- Component-level rules (anatomy, variants, states, responsive behavior)
- Accessibility requirements and testable acceptance criteria
- Content and tone standards with examples
- Anti-patterns and prohibited implementations
- QA checklist

## Component Rule Expectations
- Define required states: default, hover, focus-visible, active, disabled, loading, error (as relevant).
- Describe interaction behavior for keyboard, pointer, and touch.
- State spacing, typography, and color-token usage explicitly.
- Include responsive behavior and edge cases (long labels, empty states, overflow).

## Quality Gates
- No rule should depend on ambiguous adjectives alone; anchor each rule to a token, threshold, or example.
- Every accessibility statement must be testable in implementation.
- Prefer system consistency over one-off local optimizations.
- Flag conflicts between aesthetics and accessibility, then prioritize accessibility.

## Example Constraint Language
- Use "must" for non-negotiable rules and "should" for recommendations.
- Pair every do-rule with at least one concrete don't-example.
- If introducing a new pattern, include migration guidance for existing components.
