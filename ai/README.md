# Pagible AI

AI features for [Pagible CMS](https://pagible.com) powered by PHP Prisma. Supports content generation, translation, image manipulation, and transcription.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible). For full installation, use:

```bash
composer require aimeos/pagible
```

## Configuration

After installation, the configuration is available in `config/cms/ai.php`. Each AI feature can use a different provider and model:

| Feature | Default provider | Description |
|---------|------------------|-------------|
| `write` | `gemini` | Content generation and the admin AI chat |
| `refine` | `openai` | Content refinement |
| `describe` | `gemini` | File description generation |
| `translate` | `deepl` | Text translation |
| `imagine` | `gemini` | Image generation |
| `inpaint` | `gemini` | Image inpainting |
| `repaint` | `gemini` | Image repainting |
| `erase` | `clipdrop` | Object removal from images |
| `isolate` | `clipdrop` | Background removal |
| `uncrop` | `clipdrop` | Image extension |
| `upscale` | `clipdrop` | Image upscaling |
| `transcribe` | `openai` | Audio transcription |

If no model is configured, the provider's default model is used.

Global settings:

| Option | Env Variable | Default | Description |
|--------|-------------|---------|-------------|
| `maxtoken` | `CMS_AI_MAXTOKEN` | provider default | Maximum tokens per AI response |
| `maxsteps` | `CMS_AI_MAXSTEPS` | `25` | Maximum tool steps of the admin AI chat |
| `timeout` | `CMS_AI_TIMEOUT` | `300` | Maximum seconds an AI request may run |
| `maxinput` | `CMS_AI_MAXINPUT` | `1048576` | Maximum input size in bytes sent to a provider |
| `maxdepth` | `CMS_AI_MAXDEPTH` | `20` | Maximum nesting depth of structured input |
| `ratelimit` | `CMS_AI_RATELIMIT` | `60` | Maximum AI provider calls per minute and tenant made by queued page translations |
| `middleware` | | `['web', 'throttle:cms-ai']` | Middleware of the `cmsapi/chat` streaming route |

### Environment Variables

Each feature supports its own set of environment variables:

```
CMS_AI_{FEATURE}          # Provider name (e.g., gemini, deepl, openai, clipdrop)
CMS_AI_{FEATURE}_MODEL    # Model identifier
CMS_AI_{FEATURE}_API_KEY  # API authentication key
```

For example, to configure the write feature:

```env
CMS_AI_WRITE=gemini
CMS_AI_WRITE_API_KEY=your-api-key
```

The translate feature also supports `CMS_AI_TRANSLATE_URL` for a custom endpoint.

## Page translation

The "Translate" action in the admin page list creates or updates the language variants of the selected pages from their source language. Translations run as queued jobs on the queue configured in `cms.queue` and are saved as new drafts by the editor "AI draft", so they must be reviewed and published like any other change. Only the elements and fields which changed since the last translation are sent to the provider.

A translation is marked as "Needs update" when its source page is published with changes or when an old version of the translation is restored. "Ignore changes" marks it as up to date again without changing its content.

Large pages are split into several provider calls and all calls of a tenant are limited by `CMS_AI_RATELIMIT` (calls per minute). Jobs exceeding the limit are delayed instead of failing, while rate limit, overload and connection errors of the provider are retried after 10 seconds, 1 minute and 5 minutes.

### Excluding content from translation

Texts of the field types `string`, `text`, `plaintext` and `markdown` as well as table cells are translated, including fields within `items`. Add `"translate": false` in the `schema.json` of a theme to keep the value of a field unchanged in all languages, e.g. for codes or reference numbers:

```json
"currency": {
    "type": "string",
    "translate": false,
    "label": "Currency"
}
```

The same flag on an element type excludes all of its fields, e.g. for source code:

```json
"code": {
    "group": "basic",
    "translate": false,
    "fields": { ... }
}
```

## Commands

### cms:install:ai

Installs the Pagible AI package.

```bash
php artisan cms:install:ai
```

Publishes Prism PHP configuration, analytics bridge files, and the AI GraphQL schema.

### cms:description

Generates missing descriptions for pages and files using AI.

```bash
php artisan cms:description
```

- **Pages**: Generates SEO meta descriptions (max 160 characters) for pages that have content but no description. Uses the `write` AI provider.
- **Files**: Generates descriptions for images (JPEG, PNG, WebP), audio, and video files with empty descriptions. Uses the `describe` AI provider.

## License

MIT
