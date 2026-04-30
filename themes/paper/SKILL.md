---
name: paper
description: Paper-textured, print-inspired design with minimal colors, clean serif/sans typography, and tactile surface qualities.
license: MIT
metadata:
  author: Aimeos
---

<!-- TYPEUI_SH_MANAGED_START -->
# Paper Theme Design System

## Mission
You are an expert frontend developer for the Paper theme.
Follow these guidelines to produce visually consistent, accessible markup and styles.

## Brand
Warm, minimal, print-inspired. Off-white textured background (#F7F7F4) with diagonal line pattern. Monochrome palette, generous whitespace, paper-like surfaces. Built on Pico CSS with `--pico-*` custom property overrides.

## Style Foundations
- Visual style: minimal, warm, print-inspired. Diagonal line pattern texture on off-white background
- Typography: Font=Inter (self-hosted woff2), weights=300 (body/description text), 400 (default/h1-h3), 500 (h4-h6/labels) | Sizes: h1=3rem/3.5rem, h2=2.5rem, h3=1.25rem, h4=1.125rem, body=1rem, small=0.875rem | line-height: body=1.7, text blocks=1.8, h1=1.1
- Color tokens: --pico-color=#1A1A1A, --pico-background-color=#F7F7F4, --pico-muted-color=#525252, --pico-muted-border-color=rgba(0,0,0,0.08), --pico-contrast=#1A1A1A, --pico-contrast-inverse=#FFFFFF, --pico-text-selection-color=#E5E5DF | Surfaces: #FFFFFF for cards/dialogs/inputs, #1A1A1A for dark footer
- Border radius: 0.5rem (default), 2rem (cards/containers), 9999px (buttons/inputs/badges) | Shadows: subtle, e.g. 0 1.25rem 3.75rem -0.9375rem rgba(0,0,0,0.05)
- Max widths: 80rem (header/docs), 75rem (container), 60rem (blog), 50rem (text) | Breakpoints: 576px, 768px, 992px
- Components: hero, cards (1->2->3 col grid), blog (featured+list), questions/FAQ (details/summary accordion), contact form (pill inputs), toc, slideshow, article, search dialog, docs sidebar (20rem, sticky), dark footer with 3rem top radius
- Buttons: pill-shaped (9999px radius), primary=dark bg (#1A1A1A), secondary=white bg with border


## Accessibility
WCAG 2.2 AA. Skip-to-content link. Focus: 3px solid contrast, offset 3px. Min touch target: 2.75rem. prefers-reduced-motion respected. Semantic HTML (nav aria-label, dialog, details). RTL support in docs layout.

## Writing Tone
concise, confident, helpful

## Rules: Do
- Use --pico-* custom properties for all colors, spacing, and typography
- Use 2rem radius for cards/containers, 9999px for buttons/inputs/badges
- Use weight 300 for body text, 400 for h1-h3, 500 for h4-h6 and labels
- Use #FFFFFF backgrounds with rgba(0,0,0,0.05) borders for elevated surfaces
- Preserve visual hierarchy and keep interaction states explicit

## Rules: Don't
- Don't use colors outside the monochrome palette (exception: success #16A34A, danger #DC2626)
- Don't use border-radius values other than 0.5rem, 1rem, 1.5rem, 2rem, or 9999px
- Don't use font weights other than 300, 400, or 500
- Don't add heavy shadows or gradients that break the flat, paper-like feel
- Don't hard-code colors; reference --pico-* tokens (exception: #FFFFFF surfaces, #1A1A1A footer)

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

<!-- TYPEUI_SH_MANAGED_END -->