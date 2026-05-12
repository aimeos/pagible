# PagibleAI CMS - Simple as Wordpress, the power of Contentful!

The easy, flexible and scalable API-first PagibleAI CMS package for Laravel.

## Table of Contents

* [About](#about)
* [Features](#features)
* [Tech Stack](#tech-stack)
* [Architecture](#architecture)
* [Project Structure](#project-structure)
* [Getting Started](#getting-started)
* [Configuration](#configuration)
* [Maintenance](#maintenance)
* [Multi-domain](#multi-domain)
* [Multi-tenancy](#multi-tenancy)
* [MCP API](#mcp-api)
* [Security](#security)
* [Contributing](#contributing)
* [What's Next](#whats-next)
* [License](#license)
* [Acknowledgements](#acknowledgements)
* [Links](#links)

## About

PagibleAI CMS is an API-first content management system that can be installed into any existing Laravel application. It combines the ease of use of WordPress with the structured content management capabilities of Contentful, powered by AI for content generation, image manipulation, and translation.

Whether you need a simple blog or a multi-tenant, multi-domain CMS serving millions of pages, PagibleAI scales to your needs — from a single SQLite-backed page to database clusters.

## Features

* **Structured Content** - Manage structured content like in Contentful
* **AI-Powered** - AI generates/enhances drafts and images
* **Hierarchical Pages** - Hierarchical page tree with drag & drop
* **Shared Content** - Assign shared content to multiple pages
* **Versioning** - Save, publish, schedule and revert drafts
* **Audit Trail** - Full version history and audit trail
* **Extensible Elements** - Define new content elements in seconds
* **JSON API** - Extremely fast JSON frontend API
* **GraphQL API** - Versatile GraphQL admin API
* **Multi-language** - Multi-language support
* **Multi-domain** - Multi-domain routing
* **Multi-tenancy** - Multi-tenancy capable
* **Importers** - Importer for WordPress, etc.
* **MCP API** - 30+ tools for LLM-driven content management
* **Full-text Search** - Across SQLite, MySQL, PostgreSQL and SQL Server
* **Scalable** - From single page with SQLite to millions of pages with DB clusters
* **Open Source** - Fully open source

## Tech Stack

| Technology | Purpose |
|---|---|
| **PHP 8.1+** | Backend language |
| **Laravel 11.x / 12.x / 13.x** | Web framework |
| **Vue.js 3** | Admin panel frontend |
| **Vuetify** | Admin UI component library |
| **Lighthouse** | GraphQL API |
| **Prism / Prisma PHP** | AI/LLM integration |
| **Vite** | Frontend build tool |

## Architecture

PagibleAI CMS is a modular monorepo split into 10 sub-packages. Each package handles a specific concern and can be used independently:

* **Core** provides the data models (Page, Element, File, Version), multi-tenancy, permissions, and migrations
* **Admin** delivers the Vue.js-based admin panel with drag & drop page management
* **GraphQL** exposes the admin API via Lighthouse for content editing
* **JSON:API** provides a read-only frontend API for content delivery
* **AI** integrates LLM providers through Prism/Prisma PHP for content generation, image manipulation, and translation
* **Search** implements a custom Laravel Scout engine supporting FTS5 (SQLite), MATCH/AGAINST (MySQL), tsvector (PostgreSQL), and CONTAINSTABLE (SQL Server)
* **MCP** offers 30+ tools for LLM-driven content management
* **Backup** provides per-tenant backup and restore with media files, integrity verification, and cross-tenant support
* **Import** provides importers from external CMS platforms (WordPress, etc.)
* **Theme** handles frontend rendering with Blade templates
* **Themes** contains high-quality frontend themes

Pages are organized as a nested set tree (using `_lft`/`_rgt` columns). All content changes are tracked as immutable version snapshots — editors see the latest draft while the public sees the published version. Caching is per-page with configurable duration.

## Project Structure

```
pagible/
├── src/          Meta-package (install orchestrator + serve command)
├── core/         Models, permissions, tenancy, utilities, migrations
├── admin/        Vue.js admin panel (Vuetify + Vite)
├── ai/           AI features (Prism / Prisma PHP)
├── graphql/      GraphQL API (Lighthouse)
├── backup/       Backup and restore
├── import/       CMS importers (WordPress, etc.)
├── search/       Full-text search (Laravel Scout)
├── jsonapi/      Read-only JSON:API
├── mcp/          MCP server (30+ tools)
├── theme/        Frontend rendering (Blade templates)
├── themes/       Frontend themes (CSS)
├── tests/        Shared test infrastructure
├── config/       Configuration files
└── phpunit.xml   Aggregated test runner
```

## Getting Started

### Prerequisites

* PHP 8.2 or higher
* Composer
* Node.js & npm (for admin panel development)
* A working Laravel 11.x, 12.x, or 13.x installation

### Installation

If you don't have an existing Laravel application, create one first:

```bash
composer create-project laravel/laravel pagible
cd pagible
```

Then install PagibleAI CMS:

```bash
composer req aimeos/pagible
php artisan cms:install
php artisan migrate
```

Now, adapt the `.env` file of your application and change the `APP_URL` setting to your domain. If you are using `php artisan serve` for testing, add the port of the internal web server (`APP_URL=http://localhost:8000`). Otherwise, the uploading files will fail because they wouldn't be loaded!

Add a line in the "post-update-cmd" section of your `composer.json` file to update the admin backend files after each update:

```json
"post-update-cmd": [
    "@php artisan vendor:publish --force --tag=cms-admin --tag=cms-graphql",
    "@php artisan vendor:publish --tag=cms-theme",
    "@php artisan migrate",
    ...
],
```

### Authorization

To allow users to edit CMS content or to create a new users if they don't exist yet, you can use the `cms:user` command (replace the e-mail address by the users one):

```bash
php artisan cms:user -e editor@example.com
```

This adds admin privileges for the specified user. For more information regarding authorization and permissions, please have a look into the [authorization and permission](https://pagible.com/authorization-and-permissions) page.

The CMS admin backend is available at (replace "mydomain.tld" with your own one):

```
http://mydomain.tld/cmsadmin
```

## Configuration

To generate texts/images from prompts, analyze image/video/audio content, or execute actions based
on your prompts, you have to configure one or more of the AI service providers supported by the
[Prism](https://github.com/prism-php/prism/blob/main/config/prism.php) and
[Prisma](https://php-prisma.org/#supported-providers) packages.

**Note:** You only need to configure API keys for the AI service providers you are using, not for all!

All service providers require to sign-up and create an account first. They will provide
an API key which you need to add to your `.env` file or as environment variable, e.g.:

```
GEMINI_API_KEY="..."
OPENAI_API_KEY="..."
CLIPDROP_API_KEY="..."
DEEPL_API_KEY="..."

# Text translation
CMS_AI_TRANSLATE_API_KEY="${DEEPL_API_KEY}"
# For DeepL Pro accounts
# CMS_AI_TRANSLATE_URL="https://api.deepl.com/"

# Analyze content and generate text/images
CMS_AI_WRITE_API_KEY="${GEMINI_API_KEY}"
CMS_AI_REFINE_API_KEY="${GEMINI_API_KEY}"
CMS_AI_DESCRIBE_API_KEY="${GEMINI_API_KEY}"
CMS_AI_IMAGINE_API_KEY="${GEMINI_API_KEY}"
CMS_AI_INPAINT_API_KEY="${GEMINI_API_KEY}"
CMS_AI_REPAINT_API_KEY="${GEMINI_API_KEY}"

# Image manipulation
CMS_AI_ERASE_API_KEY="${CLIPDROP_API_KEY}"
CMS_AI_ISOLATE_API_KEY="${CLIPDROP_API_KEY}"
CMS_AI_UNCROP_API_KEY="${CLIPDROP_API_KEY}"
CMS_AI_UPSCALE_API_KEY="${CLIPDROP_API_KEY}"

# Audio transcription
CMS_AI_TRANSCRIBE_API_KEY="${OPENAI_API_KEY}"
```

For best results and all features, you need Google, OpenAI, Clipdrop, and DeepL at the moment and they are also configured by default. If you want to use a different provider or model, you can to configure them in your `.env` file too. Please have a look into the [./config/cms/ai.php](https://github.com/aimeos/pagible/blob/master/config/cms.php) for the used environment variables.

**Note:** You can also configure the base URLs for each provider using the `url` key in each provider configuration, e.g.:

```php
    'transcribe' => [ // Transcribe audio
        'provider' => env( 'CMS_AI_TRANSCRIBE', 'openai' ),
        'model' => env( 'CMS_AI_TRANSCRIBE_MODEL', 'whisper-1' ),
        'api_key' => env( 'CMS_AI_TRANSCRIBE_API_KEY' ),
        'url' => 'https://openai-api.compatible-provider.com'
    ],
```

**Note:** To protect forms like the contact form against misuse and spam, you can configure [HCaptcha](https://pagible.com/configure-hcaptcha).

## Maintenance

For scheduled publishing, you need to add this line to the `routes/console.php` class:

```php
\Illuminate\Support\Facades\Schedule::command('cms:publish')->daily();
```

To clean up soft-deleted pages, elements and files regularly, add these lines to the `routes/console.php` class:

```php
\Illuminate\Support\Facades\Schedule::command('model:prune', [
    '--model' => [
        \Aimeos\Cms\Models\Page::class,
        \Aimeos\Cms\Models\Element::class,
        \Aimeos\Cms\Models\File::class
    ],
])->daily();
```

## Multi-domain

Using multiple page trees with different domains is possible by adding `CMS_MULTIDOMAIN=true` to your `.env` file.

## Multi-tenancy

PagibleAI CMS supports single database multi-tenancy using existing Laravel tenancy packages or code implemented by your own.

The [Tenancy for Laravel](https://tenancyforlaravel.com/) package is most often used. How to set up the package is described in the [Multi-tenancy SaaS Setup](/multi-tenancy-saas-setup) article.

## MCP API

PagibleAI CMS offers tools within the Laravel MCP API that LLMs can use to interact with the CMS. Please have a look at the [PagibleAI MCP documentation](https://pagible.com/configure-mcp) page for details how to set up the MCP API and Passport for authentication.

## Security

If you find a security related issue, please contact `security at aimeos.org`.

* All user-generated content is sanitized with HTMLPurifier
* Content Security Policy (CSP) headers are enforced
* Forms are protected with HCaptcha
* All API endpoints are rate-limited
* URL validation on all user-submitted links

## Contributing

Contributions are welcome! Here's how you can help:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/your-feature`)
3. Make your changes
4. Run the tests: `vendor/bin/phpunit`
5. Run static analysis: `vendor/bin/phpstan analyze`
6. Commit your changes (`git commit -m 'Add your feature'`)
7. Push to your branch (`git push origin feature/your-feature`)
8. Open a Pull Request

Please make sure all tests pass and PHPStan reports no new errors before submitting.

## What's Next

* Extend admin panels and sub-panels by extensions
* Additional CMS importers (Drupal, Joomla, Statamic, TYPO3)
* Observability & Audit Trail
* User and group restrictions
* Webhook & Event System
* More themes

## License

PagibleAI CMS is licensed under the [LGPL-3.0 license](LICENSE).

## Acknowledgements

Special thanks to:
- Lwin Min Oo

## Links

* Website: [pagible.com](https://pagible.com)
* GitHub: [github.com/aimeos/pagible](https://github.com/aimeos/pagible)
