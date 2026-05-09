---
name: premium
description: Warm, professional design with burgundy red brand accents, large rounded cards, alternating sections, and bold typography.
license: MIT
metadata:
  author: Aimeos
---

# Premium Theme Design System

## Mission
You are an expert frontend developer for the Premium theme.
Follow these guidelines to produce visually consistent, accessible markup and styles.

## Brand
Warm, professional, confident. Cool neutral background (#F8F9FA) with burgundy red brand accents (#991B1B). Clean flat surfaces, generous whitespace, bold typography, alternating section backgrounds. Built on Pico CSS with `--pico-*` custom property overrides.

## Style Foundations
- Visual style: modern, clean, professional. No texture or pattern on backgrounds, flat surfaces with subtle drop-shadows
- Typography: Font=ui-sans-serif, sans-serif | Monospace=ui-monospace, monospace | weights=400 (body/description text), 500 (h3-h6/labels/nav), 600 (h1-h2/buttons/brand) | Base size: 1.1875rem | Sizes use calc(var(--pico-font-size) * multiplier): h1=2.25x/2.5x(768px)/2.75x(hero)/3.75x(hero 768px), h2=1.6x/1.75x(768px), h3=0.9x, h4=0.8x | line-height: body=1.625, h1=1.1, h2=1.2, h3=1.3, h4=1.4 | letter-spacing: display=-0.03em, hero=-0.04em
- Color tokens: --pico-color=#0F172A, --pico-background-color=#F8F9FA, --pico-muted-color=#435064, --pico-muted-border-color=#0F172A14, --pico-contrast=#0F172A, --pico-contrast-inverse=#FFFFFF, --pico-primary=#991B1B, --pico-primary-hover=#7F1D1D, --pico-secondary=#4338CA, --pico-secondary-hover=#3730A3, --pico-text-selection-color=#DBEAFE | Surfaces: #FFFFFF for cards, #F1F5F9 for alternating sections, #0F172A for footer | Heading colors: h1-h4=#0F172A, h5=#334155, h6=#435064
- Border radius: base --pico-border-radius=0.5rem, use calc multipliers: 1x=0.5rem (buttons/inputs), 1.5x=0.75rem (code/form focus), 2x=1rem (blog items/images inside containers), 3x=1.5rem (FAQ/TOC), 4x=2rem (cards/containers/hero images/pricing) | Shadows: box-shadow: 0 1px 3px #0F172A0A, 0 1px 2px #0F172A06; elevated hover: 0 0.5rem 2rem #0F172A0F; drop-shadow: 0 1.5rem 3rem #0F172A14
- Max widths: 1280px (header/footer/page/docs/blog), 1200px (main container), 960px (CMS content), 50rem (text), 38rem (hero paragraph) | Breakpoints: 576px, 768px, 992px, 1024px
- Components: hero (large heading with pill badge subtitle), cards (1->3 col grid with hover lift), blog (featured+list with date badges), questions/FAQ (details/summary accordion), contact form, toc, slideshow, article, search dialog, docs sidebar (20rem, sticky), dark footer
- Buttons: rounded (0.5rem radius), primary=burgundy bg (#991B1B), secondary=white bg with border | Padding: 0.875rem 1.5rem

## Accessibility
WCAG 2.2 AA. Skip-to-content link. Focus: 2px solid primary, offset 2px; inputs: 0 0 0 3px #2563EB1A. Min touch target: 2.25rem. prefers-reduced-motion respected. Semantic HTML (nav aria-label, dialog, details). RTL support.

## Writing Tone
concise, confident, professional

## Rules: Do
- Use --pico-* custom properties for all colors, spacing, and typography
- Use calc(var(--pico-border-radius) * N) for radius: 1x buttons/inputs, 2x blog/images, 3x FAQ/TOC, 4x cards/containers
- Use weight 400 for body text, 500 for h3-h6/labels/nav, 600 for h1-h2/brand
- Use #FFFFFF backgrounds with #0F172A14 borders for elevated surfaces
- Use burgundy (#991B1B) for primary actions, links, and interactive accents
- Use filter: drop-shadow() for image/video/slideshow containers
- Preserve visual hierarchy and keep interaction states explicit

## Rules: Don't
- Don't use textures, patterns, or diagonal lines on backgrounds
- Don't use border-radius values other than 0.5rem, 0.75rem, 1rem, 1.5rem, or 2rem
- Don't use font weights other than 400, 500, or 600
- Don't use pill-shaped (9999px) border-radius on buttons or inputs
- Don't hard-code colors; reference --pico-* tokens (exception: #FFFFFF surfaces, #0F172A footer, shadow hex values)

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
