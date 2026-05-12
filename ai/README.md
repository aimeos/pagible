# Pagible AI

AI features for [Pagible CMS](https://pagible.com) powered by PHP Prisma. Supports content generation, translation, image manipulation, and transcription.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible). For full installation, use:

```bash
composer require aimeos/pagible
```

## Configuration

After installation, the configuration is available in `config/cms/ai.php`. Each AI feature can use a different provider and model:

| Feature | Provider | Model | Description |
|---------|----------|-------|-------------|
| `write` | `gemini` | `gemini-2.5-flash` | Content generation |
| `refine` | `gemini` | `gemini-2.5-flash` | Content refinement |
| `describe` | `gemini` | `gemini-2.5-flash` | File description generation |
| `translate` | `deepl` | | Text translation |
| `imagine` | `gemini` | `gemini-2.5-flash-image` | Image generation |
| `inpaint` | `gemini` | `gemini-2.5-flash-image` | Image inpainting |
| `repaint` | `gemini` | `gemini-2.5-flash-image` | Image repainting |
| `erase` | `clipdrop` | | Object removal from images |
| `isolate` | `clipdrop` | | Background removal |
| `uncrop` | `clipdrop` | | Image extension |
| `upscale` | `clipdrop` | | Image upscaling |
| `transcribe` | `openai` | `whisper-1` | Audio transcription |

Global setting:

| Option | Env Variable | Description |
|--------|-------------|-------------|
| `maxtoken` | `CMS_AI_MAXTOKEN` | Maximum tokens for AI responses |

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
CMS_AI_WRITE_MODEL=gemini-2.5-flash
CMS_AI_WRITE_API_KEY=your-api-key
```

The translate feature also supports `CMS_AI_TRANSLATE_URL` for a custom endpoint.

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

LGPL-3.0-only
