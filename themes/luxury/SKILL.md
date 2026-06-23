---
name: luxury
description: Minimalist high-fashion design with editorial serif headlines, ivory and near-black palette, antique gold accents, sharp edges and generous whitespace.
license: MIT
metadata:
  author: Aimeos
---

# Luxury Theme Design System

## Mission
You are an expert frontend developer for the Luxury theme.
Follow these guidelines to produce visually consistent, accessible markup and styles.

## Brand
Restrained, editorial, high-fashion. Warm ivory background (#FBF9F4) with near-black text (#1C1A16) and sparing antique gold accents (#7A5F32). High-contrast serif display headlines, geometric sans-serif body, uppercase letter-spaced labels, sharp (zero-radius) edges, hairline rules, flat surfaces and abundant whitespace. The aesthetic is monochrome with a single gold reveal on interaction. Built on Pico CSS with `--pico-*` custom property overrides.

## Style Foundations
- Visual style: minimalist luxury / fashion editorial. No texture, pattern or heavy shadow; flat surfaces, hairline borders, generous negative space. Gold is decorative and rare — never a fill on large areas
- Typography: Body font=Futura, 'Century Gothic', 'Avenir Next', 'Helvetica Neue', Helvetica, Arial, sans-serif | Display font (headings/brand)=Didot, 'Bodoni 72', 'Bodoni MT', 'Bodoni Moda', 'Playfair Display', 'Hoefler Text', Garamond, 'Times New Roman', serif | Monospace=ui-monospace, monospace | weights=400 (h1-h3/brand/body), 500 (h4-h6/nav/labels/buttons) | Base size: 1.1875rem | Sizes use calc(var(--pico-font-size) * multiplier): h1=2.5x/3x(768px)/2.85x(hero)/4.25x(hero 768px), h2=1.7x/2x(768px), h3=0.95x, h4=0.82x | line-height: body=1.7, h1=1.08, h2=1.18, hero h1=1.04 | letter-spacing: headings=0.005em, hero h1=0.01em, body/.text=0.01em, nav=0.14em, brand=0.22em, buttons/labels=0.15em, eyebrow=0.32em — serif display headings are NOT negatively tracked
- Color tokens: --pico-color=#1C1A16, --pico-background-color=#FBF9F4, --pico-muted-color=#6E6557, --pico-muted-border-color=#1C1A1618, --pico-muted-background-color=#F3EEE4, --pico-contrast=#1C1A16, --pico-contrast-background=#FFFFFF, --pico-contrast-inverse=#FFFFFF, --pico-primary=#1C1A16 (near-black: links + primary CTA fill), --pico-primary-hover=#8A6E3F (gold reveal), --pico-secondary=#7A5F32 (antique gold accent), --pico-secondary-hover=#5F4A26, --pico-text-selection-color=#B79A5E40 | Surfaces: #FFFFFF cards, #F3EEE4 alternating sections, #1C1A16 footer | Heading colors: h1-h4=#1C1A16, h5=#3A352B, h6=#6E6557
- Border radius: --pico-border-radius=0 — everything is sharp-cornered (buttons, inputs, cards, images, containers). Do NOT introduce rounded corners | Shadows: kept faint and flat. box-shadow: 0 1px 2px #1C1A1608, 0 1px 1px #1C1A1605; card hover: 0 1rem 2.5rem #1C1A160F; drop-shadow (image/video/slideshow): 0 1.5rem 3rem #1C1A1614
- Max widths: 1280px (header/footer/page/docs/blog), 1200px (main container), 960px (CMS content), 50rem (text), 38rem (hero paragraph) | Section padding: 5.5rem / 8rem(768px); hero 9rem 0 7rem(768px) | Breakpoints: 576px, 768px, 992px, 1024px
- Components: hero (huge serif headline, gold uppercase eyebrow subtitle with a centered hairline rule below — NO pill badge), cards (1->3 col grid, flat hairline border, serif title, subtle hover lift), heading (centered h1/h2 with a 3rem gold hairline rule beneath via ::after), blog (featured+list with serif date), questions/FAQ accordion, contact form (gold focus ring), toc, slideshow, article, search dialog, docs sidebar (20rem, sticky), near-black footer
- Buttons: sharp (0 radius), uppercase, letter-spacing 0.15em, sans-serif label at 0.72x | Default=transparent with 1px near-black border, inverts to solid near-black on hover | Primary (.btn:first-child / nav CTA)=near-black fill, white text, fills gold (#8A6E3F) on hover | Padding: 1rem 2.25rem

## Accessibility
WCAG 2.2 AA. Skip-to-content link. Focus: 2px solid primary, offset 2px; inputs: 0 0 0 3px #8A6E3F1A (gold ring). Min touch target: 2.25rem. prefers-reduced-motion respected. Body/muted text colors meet 4.5:1 on ivory; reserve gold (#8A6E3F) for decorative rules, hover states and large/uppercase accents, not body copy. Semantic HTML (nav aria-label, dialog, details). RTL support.

## Writing Tone
understated, elegant, editorial

## Rules: Do
- Use --pico-* custom properties for all colors, spacing, and typography
- Keep all corners sharp: border-radius: 0 (or var(--pico-border-radius)); never add rounding
- Use the serif display font for headings, brand and card titles; geometric sans for body, nav, labels and buttons
- Use weight 400 for h1-h3/brand/body, 500 for h4-h6/nav/labels/buttons
- Uppercase + wide letter-spacing for nav, brand, eyebrows, breadcrumbs, buttons and form submit labels
- Use #FFFFFF surfaces with #1C1A1618 hairline borders and minimal shadow
- Use near-black (#1C1A16) for links and primary CTAs; reveal gold (#8A6E3F) on hover
- Use gold (#7A5F32) only as a rare accent: hairline rules, eyebrows, focus ring
- Keep whitespace generous and surfaces flat

## Rules: Don't
- Don't use rounded corners, pill shapes or large border-radius anywhere
- Don't use negative letter-spacing on serif display headings
- Don't use font weights other than 400 or 500, or bold the serif headlines
- Don't use textures, patterns, gradients or heavy/colored shadows
- Don't flood large areas with gold or use gold for body copy (contrast)
- Don't hard-code colors; reference --pico-* tokens (exception: #FFFFFF surfaces, gold tint #8A6E3F1A focus ring, shadow hex values)

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
