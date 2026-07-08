---
name: style
description: Luxe fashion-commerce editorial design with a deep-indigo canvas, oversized numerals, large image-led storytelling, rounded capsules/cards, and restrained monochrome accents.
license: MIT
metadata:
  author: Aimeos
---

# Style Theme Design System

## Mission
You are an expert frontend developer for the Style theme.
Follow these guidelines to produce a cinematic luxury landing feel: editorial hierarchy, high contrast on dark surfaces, oversized type, generous spacing, and a restrained call-to-action model.

Implementation constraints:
- Use only the HTML markup and classes defined in `./theme/views/`.
- Do not add your own utility classes in this theme.
- Use nearest OS/system fonts and avoid remote font loading.

## Brand
Editorial luxury fashion commerce. Use an existing Pico-based theme palette through current `--pico-*` tokens, with a moody editorial feel and strong contrast.
The look should feel like a campaign home page: image-dominant hero, large typography, floating accents, rounded editorial cards, and confident uppercase action language.

## Style Foundations
- Visual style: editorial luxury. Avoid flat flat-card SaaS blocks unless image-supported.
- Typography:
  - Sans display and body: `system-ui`, `-apple-system`, `"Segoe UI"`, Arial, Helvetica, sans-serif
  - Mono: `ui-monospace`, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace
  - Body base: `16px` with generous line-height
  - Display scale: `140px` on desktop hero headings, responsive downscales for tablet/mobile.
  - Letter spacing: negative tracking for large headlines (`-1.5px`), uppercase where explicit.
- Radius and treatment:
  - Use rounded visual language for major surfaces (`24px`) and pill buttons.
  - Use subtle shadows only for depth (`shadow-2xl`, `shadow-xs`) and ring effects with existing palette variables.
- Spacing and layout:
  - Body max width: `1280px`
  - Horizontal inset: `px-8` desktop, reduced on small screens
  - Section rhythm: `120px` vertical in hero and content blocks
  - Breakpoints: `md`, `lg` only; no exotic grid systems.
- Component expectations:
  - Hero: full-bleed image panel group with one large leading image and two offset supporting images.
  - Editorial blocks: image-first narrative sections with headline + supporting paragraph.
  - Footer: compact two-row nav structure with muted legal strip.
  - Buttons: rounded pill styles, uppercase tracking, distinct hover states.

## Accessibility
WCAG 2.2 AA. Skip-to-content link. Focus: `2px` high-contrast outline using an existing `--pico-*` color with a visible offset.
Inputs and interactive elements use motion-safe hover/focus transitions and respect `prefers-reduced-motion`.
Min touch target: `2.25rem`.
Body and muted copy must maintain readable contrast on theme background tokens from `--pico-*`.
Semantic HTML and RTL support remain required.

## Writing Tone
concise, confident, fashion-editorial

## Rules: Do
- Use only existing `--pico-*` CSS variables for all color values.
- Use the theme palette and spacing consistently; avoid introducing arbitrary new neutral colors.
- Keep key sections centered in a large container with max width around `1280px`.
- Prioritize image-led layouts with layered presentation where possible.
- Use uppercase for eyebrow labels and all hero/section call-to-actions.
- Keep primary buttons as filled white with deep background fallback on hover.
- Keep borders/transitions subtle and translucent.
- Use large rounded visuals (`24px`, `ring-4`) for image cards.
- Preserve existing class usage from `./theme/views/`; do not introduce new utility classes or naming patterns.

## Rules: Don't
- Don't mix the old cream-and-ink visual grammar into new sections.
- Don't use low-contrast muted text over muted backgrounds.
- Don't use decorative gradients or heavy drop shadows as primary decoration.
- Don't create square pills or sharp-only geometry inconsistent with this theme.
- Don't rely only on Pico-specific CSS variables; keep theme CSS self-consistent.
- Don't add custom utility classes or style hooks not present in `./theme/views/`.
- Don't introduce raw color values (`#`, `rgb(`, `hsl(`) outside existing `var(--pico-...)` references.
- Don't add tailwind-style utility rules such as `.text-*`, `.bg-*`, `.flex`, `.rounded-*`, `.grid`, `.gap-*`, or other utility-structure in theme CSS.

## Expected Behavior
- Follow foundations first, then component consistency.
- Keep headline rhythm and image hierarchy consistent across hero, editorial and commerce sections.
- Use uppercase, restrained language with clear commerce intent.
- Prioritize readability and interaction clarity over flashy motion.

## Guideline Authoring Workflow
1. Restate the design intent in one sentence before proposing rules.
2. Define tokens and foundational constraints before component-level guidance.
3. Specify component anatomy, states, variants and responsive behavior.
4. Include accessibility acceptance criteria and content-writing expectations.
5. Add anti-patterns and migration notes for existing inconsistent UI.
6. End with a QA checklist that can be executed in code review.

## Required Output Structure
When generating design-system guidance for this theme, use this structure:
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

## Theme-Specific Tokens and Mapping
- `--hero-title-size`: `140px`
- `--section-padding`: `120px`
- `--content-width`: `1280px`

Map all color styles to existing `--pico-*` variables only. Example references: `--pico-color`, `--pico-background-color`, `--pico-muted-color`, `--pico-muted-background-color`, `--pico-primary`, `--pico-primary-hover`, `--pico-secondary`, `--pico-secondary-hover`.
Do not add theme-local color tokens such as `--theme-*`.

## Class and Source-of-Truth Rules
- Source-of-truth for reusable structure and classes is `./theme/views/`.
- If a style requirement needs new structure or a new class, update the view files first, then style that existing class name in CSS.
- Favor existing selectors and avoid adding helper utilities that duplicate class-based conventions.

## QA Checklist
- Hero displays the seasonal badge, two supporting images, and a dominant CTA at desktop widths.
- Headings at the largest scale remain readable at `lg` and wrap cleanly below `lg`.
- Buttons are pill-shaped, uppercase, and have clear hover/focus contrast.
- Footer links remain legible with clear hover contrast.
- Social/probably action links keep muted default states and full-visible hovered states.
- Section backgrounds remain consistently at `--theme-bg` with no accidental light surfaces.
- No broken image framing (no overflow clipping, no edge aliasing) on cards and hero blocks.
