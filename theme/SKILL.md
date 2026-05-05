---
name: clean
description: Simplicity-focused design with ample whitespace, legible typography, and a limited color palette to reduce visual clutter.
license: MIT
metadata:
  author: Aimeos
---

# Clean Theme Design System

## Mission
You are an expert frontend developer for the Clean theme.
Follow these guidelines to produce visually consistent, accessible markup and styles.

## Brand
Minimal, clean, and modern. Pure white background (#FFFFFF) with blue accent (#3B82F6). Generous whitespace, crisp borders, subtle shadows. Built on Pico CSS with `--pico-*` custom property overrides.

## Style Foundations
- Visual style: minimal, clean, modern. No background texture or pattern
- Typography: Font=System (-apple-system, BlinkMacSystemFont, etc.), weights=500 (body), 500 (h4-h6), 600 (h1-h3/brand) | Sizes: h1=3.2rem, h2=2.1rem, h3=0.9rem, h4=0.8rem, body=1rem, small=0.875rem | line-height: body=1.5, text blocks=1.625, h1=1.1
- Color tokens: --pico-color=#111827, --pico-background-color=#FFFFFF, --pico-muted-color=#6B7280, --pico-muted-border-color=#E5E7EB, --pico-contrast=#111827, --pico-contrast-inverse=#FFFFFF, --pico-primary=#3B82F6, --pico-text-selection-color=#DBEAFE | Surfaces: #FFFFFF for cards, #F9FAFB for alternating sections, #111827 for dark footer
- Border radius: 0.5rem (default/inputs/buttons), 1rem (cards/containers/modals) | Shadows: subtle, e.g. 0 1px 3px rgba(0,0,0,0.06)
- Max widths: 80rem (header/docs), 75rem (container), 60rem (blog), 50rem (text) | Breakpoints: 576px, 768px, 992px
- Components: hero, cards (1->2 col grid), blog (featured+list), questions/FAQ (details/summary accordion), contact form, toc, slideshow, article, search dialog, docs sidebar (20rem, sticky), light footer with top border
- Buttons: rounded (0.5rem radius), primary=blue gradient (135deg, #3B82F6 to #2563EB) with shadow, secondary=white with border

## Accessibility
WCAG 2.2 AA. Skip-to-content link. Focus: 2px solid primary (#3B82F6), offset 2px. Min touch target: 2.25rem. prefers-reduced-motion respected. Semantic HTML (nav aria-label, dialog, details). RTL support.

## Writing Tone
clear, friendly

## Rules: Do
- Use --pico-* custom properties for all colors, spacing, and typography
- Use 1rem radius for cards/containers, 0.5rem for buttons/inputs
- Use blue gradient (linear-gradient(135deg, #3B82F6, #2563EB)) for primary buttons and CTAs
- Use #FFFFFF backgrounds with #E5E7EB borders for elevated surfaces
- Use blue (#3B82F6) for focus states, active sidebar links, and hover accents

## Rules: Don't
- Don't use colors outside the defined palette (exception: success #16A34A, danger #DC2626)
- Don't use border-radius values other than 0.5rem or 1rem (multiples of --pico-border-radius)
- Don't add heavy textures, patterns, or decorative backgrounds
- Don't use pill-shaped (9999px) elements; keep corners consistently rounded
- Don't hard-code colors; reference --pico-* tokens (exception: gradient buttons, footer)

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
