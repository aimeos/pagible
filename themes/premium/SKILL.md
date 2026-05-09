---
name: premium
description: Modern, product-focused design with blue brand accents, large rounded cards, alternating sections, and bold typography.
license: MIT
metadata:
  author: Aimeos
---

# Premium Theme Design System

## Mission
You are an expert frontend developer for the Premium theme.
Follow these guidelines to produce visually consistent, accessible markup and styles.

## Brand
Modern, product-focused, confident. Cool neutral background (#F8F9FA) with blue brand accents (#2563EB). Clean flat surfaces, generous whitespace, bold typography, alternating section backgrounds. Built on Pico CSS with `--pico-*` custom property overrides.

## Style Foundations
- Visual style: modern, clean, product-focused. No texture or pattern on background, flat surfaces with subtle shadows
- Typography: Font=Inter, weights=400 (body/description text), 500 (h3-h6/labels/nav), 600 (h1-h2/buttons/brand) | Sizes: h1=3.5rem/5rem, h2=1.6rem/2.25rem, h3=0.9rem, h4=0.8rem, body=1rem, small=0.875rem | line-height: body=1.625, h1=1.05, h2=1.2
- Color tokens: --pico-color=#0F172A, --pico-background-color=#F8F9FA, --pico-muted-color=#64748B, --pico-muted-border-color=#0F172A14, --pico-contrast=#0F172A, --pico-contrast-inverse=#FFFFFF, --pico-primary=#2563EB, --pico-primary-hover=#1D4ED8, --pico-secondary=#6366F1, --pico-text-selection-color=#DBEAFE | Surfaces: #FFFFFF for cards, #F1F5F9 for alternating sections, #0F172A for footer
- Border radius: 0.75rem (default/buttons/inputs), 1.5rem (cards/containers/images), 2.25rem (article covers) | Shadows: subtle, e.g. 0 1px 3px #0F172A0A; elevated: 0 1.5rem 4rem -1rem #0F172A14
- Max widths: 80rem (header/docs), 75rem (container), 60rem (blog), 50rem (text) | Breakpoints: 576px, 768px, 992px
- Components: hero (large 5rem heading), cards (1->2 col grid with hover lift), blog (featured+list), questions/FAQ (accordion), contact form, toc, slideshow, article, search dialog, docs sidebar (20rem, sticky), dark footer with rounded top
- Buttons: rounded (0.75rem radius), primary=blue bg (#2563EB) with blue shadow, secondary=white bg with border

## Accessibility
WCAG 2.2 AA. Skip-to-content link. Focus: 2px solid primary, offset 2px. Min touch target: 2.25rem. prefers-reduced-motion respected. Semantic HTML (nav aria-label, dialog, details). RTL support.

## Writing Tone
concise, confident, professional

## Rules: Do
- Use --pico-* custom properties for all colors, spacing, and typography
- Use 1.5rem radius for cards/containers, 0.75rem for buttons/inputs
- Use weight 400 for body text, 500 for h3-h6/labels/nav, 600 for h1-h2/brand
- Use #FFFFFF backgrounds with #0F172A14 borders for elevated surfaces
- Use blue (#2563EB) for primary actions, links, and interactive accents
- Preserve visual hierarchy and keep interaction states explicit

## Rules: Don't
- Don't use textures, patterns, or diagonal lines on backgrounds
- Don't use border-radius values other than 0.75rem, 1.125rem, 1.5rem, or 2.25rem
- Don't use font weights other than 400, 500, or 600
- Don't use pill-shaped (9999px) border-radius on buttons or inputs
- Don't hard-code colors; reference --pico-* tokens (exception: #FFFFFF surfaces, #0F172A footer, blue shadows)

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
