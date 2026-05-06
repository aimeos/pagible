---
name: glass
description: Dark glassmorphism design with frosted panels, backdrop blur, and vibrant cyan/violet accents on deep navy background.
license: MIT
metadata:
  author: Aimeos
---

# Glass Theme Design System

## Mission
You are an expert frontend developer for the Glass theme.
Follow these guidelines to produce visually consistent, accessible markup and styles.

## Brand
Dark, modern, layered. Deep navy background (#060A12) with frosted glass panels using backdrop-filter blur. Cyan (#06B6D4) and violet (#8B5CF6) accents. Subtle ambient gradients. Built on Pico CSS with `--pico-*` custom property overrides.

## Style Foundations
- Visual style: dark glassmorphism, frosted panels, layered depth. Ambient radial gradients on body background
- Typography: Font=Inter/System, weights=300 (body/description text), 500 (headings/labels) | Sizes: h1=3rem/4.5rem, h2=2.5rem/3rem, h3=1.25rem, h4=1.125rem, body=1rem, small=0.875rem | line-height: body=1.5, text blocks=1.625, h1=1.1
- Color tokens: --pico-color=#E2E8F0, --pico-background-color=#060A12, --pico-muted-color=#94A3B8, --pico-muted-border-color=#FFFFFF14, --pico-contrast=#F8FAFC, --pico-contrast-inverse=#060A12, --pico-primary=#06B6D4, --pico-secondary=#8B5CF6 | Glass: --glass-bg=#FFFFFF0A, --glass-border=#FFFFFF14, --glass-blur=20px
- Border radius: 0.75rem (default), 1.5rem (cards/containers), 9999px (buttons/inputs/badges) | Shadows: deep, e.g. 0 1.25rem 3.75rem -0.9375rem rgba(0,0,0,0.4)
- Max widths: 80rem (header/docs), 75rem (container), 60rem (blog), 50rem (text) | Breakpoints: 576px, 768px, 992px
- Components: hero, cards (1->2 col grid), blog (featured+list), questions/FAQ (details/summary accordion), contact form (pill inputs), toc, slideshow, article, search dialog, docs sidebar (20rem, sticky), glass footer with top border
- Buttons: pill-shaped (9999px radius), primary=gradient (cyan to violet), secondary=glass bg with border
- Glass panels: background rgba(255,255,255,0.04), border rgba(255,255,255,0.08), backdrop-filter blur(20px)

## Accessibility
WCAG 2.2 AA. Skip-to-content link. Focus: 1px solid cyan primary. Min touch target: 2.25rem. prefers-reduced-motion respected. Semantic HTML (nav aria-label, dialog, details). RTL support.

## Writing Tone
concise, confident, modern

## Rules: Do
- Use --pico-* and --glass-* custom properties for all colors, spacing, and effects
- Use backdrop-filter: blur(var(--glass-blur)) with -webkit- prefix on all glass surfaces
- Use 1.5rem radius for cards/containers, 9999px for buttons/inputs/badges
- Use weight 300 for body text, 500 for headings and labels
- Use rgba(255,255,255,0.04) backgrounds with rgba(255,255,255,0.08) borders for glass surfaces
- Use gradients (cyan-to-violet) for primary CTAs and highlighted elements

## Rules: Don't
- Don't use solid white or light backgrounds (exception: text on gradient buttons)
- Don't use border-radius values other than 0.75rem, 1.5rem, or 9999px
- Don't use font weights other than 300 or 500
- Don't omit the -webkit-backdrop-filter prefix (Safari support)
- Don't hard-code colors; reference --pico-* or --glass-* tokens
- Don't use opaque backgrounds on panels; maintain translucency

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
