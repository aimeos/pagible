---
name: style
description: Fashion-commerce editorial design with oversized condensed headlines, cream and ink surfaces, deep red accents, image-led sections and sharp layouts.
license: MIT
metadata:
  author: Aimeos
---

# Style Theme Design System

## Mission
You are an expert frontend developer for the Style theme.
Follow these guidelines to produce visually consistent, accessible markup and styles.

## Brand
Editorial fashion commerce. Cream background (#F4EEE6), ink text (#17120F), white product/editorial surfaces and deep red accents (#B41622). The theme should feel like a high-fashion campaign page: oversized condensed headlines, sharp image framing, compact navigation, black footer and direct commercial calls to action. Built on Pico CSS with `--pico-*` custom property overrides.

## Style Foundations
- Visual style: image-led fashion storefront with sharp grids, hairline rules and bold typographic contrast. Avoid decorative gradients, texture and soft card-heavy SaaS patterns.
- Typography: Body font='Avenir Next', 'Helvetica Neue', Helvetica, Arial, sans-serif | Display font='Helvetica Neue Condensed Black', 'Arial Narrow', Impact, 'Arial Black', sans-serif | Monospace=ui-monospace, monospace | weights=400 body, 500 labels/nav, 700 display | Base size=1.125rem | Headline sizes use fixed media-query multipliers, not viewport units | letter-spacing=0 throughout.
- Color tokens: --pico-color=#17120F, --pico-background-color=#F4EEE6, --pico-muted-color=#665D52, --pico-muted-border-color=#17120F1A, --pico-muted-background-color=#E8DED3, --pico-contrast=#17120F, --pico-contrast-background=#FFFFFF, --pico-contrast-inverse=#FFFFFF, --pico-primary=#17120F, --pico-primary-hover=#B41622, --pico-secondary=#B41622, --pico-secondary-hover=#7C1018, --pico-text-selection-color=#B4162240.
- Border radius: --pico-border-radius=0. Everything is sharp: cards, images, inputs and buttons. Shadows are minimal and neutral.
- Max widths: 1320px header/footer, 1240px container, 52rem long text. Section padding: 5rem mobile, 7rem desktop. Breakpoints: 576px, 768px, 992px, 1024px.
- Components: hero (split editorial layout, huge condensed title, red uppercase season/eyebrow, image panel), cards (commerce/editorial tiles, image first when available), heading (centered with thin red rule), blog (magazine list), questions, contact, toc, slideshow, article, search dialog, docs sidebar and black footer.
- Buttons: sharp, uppercase labels, 1px ink border, primary ink fill with white text, red fill on hover/focus. Padding: 0.9rem 1.7rem.

## Accessibility
WCAG 2.2 AA. Skip-to-content link. Focus: 2px solid #B41622 with 2px offset; inputs use a subtle red focus ring. Min touch target: 2.25rem. Body and muted text meet contrast on cream and white. Do not use red for long body text. prefers-reduced-motion respected. Semantic HTML and RTL support remain required.

## Writing Tone
concise, confident, editorial

## Rules: Do
- Use --pico-* custom properties for all shared colors, spacing and typography.
- Keep every corner sharp with border-radius: 0 or var(--pico-border-radius).
- Use condensed display type for h1/h2/brand and regular sans-serif for content.
- Keep display headline letter-spacing at 0.
- Use red only for labels, rules, focus states and hover states.
- Prioritize large real imagery and clear product/editorial hierarchy.
- Keep the black footer compact with direct shop/about link groups.

## Rules: Don't
- Don't introduce rounded cards, pills, gradient blobs or decorative backgrounds.
- Don't use viewport units for font sizes.
- Don't make the page monochrome cream; balance cream with ink, white and red.
- Don't use deep red for paragraph text or low-contrast small copy.
- Don't hard-code colors when an existing token can express the state.

## Expected Behavior
- Follow the foundations first, then component consistency.
- When uncertain, prioritize accessibility and content clarity over visual novelty.
- Keep guidance opinionated, concise and implementation-focused.

## Guideline Authoring Workflow
1. Restate the design intent in one sentence before proposing rules.
2. Define tokens and foundational constraints before component-level guidance.
3. Specify component anatomy, states, variants and responsive behavior.
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
- Define required states: default, hover, focus-visible, active, disabled, loading and error where relevant.
- Describe interaction behavior for keyboard, pointer and touch.
- State spacing, typography and color-token usage explicitly.
- Include responsive behavior and edge cases for long labels, empty states and overflow.

## Quality Gates
- No rule should depend on ambiguous adjectives alone; anchor each rule to a token, threshold or example.
- Every accessibility statement must be testable in implementation.
- Prefer system consistency over one-off local optimizations.
- Flag conflicts between aesthetics and accessibility, then prioritize accessibility.
