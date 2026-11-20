# PagibleAI CMS - Simple as WordPress, the power of Contentful!

The easy, flexible and scalable API-first PagibleAI CMS package for Laravel.

## Table of Contents

* [About](#about)
* [Features](#features)
* [Tech Stack](#tech-stack)
* [Architecture](#architecture)
* [Project Structure](#project-structure)
* [Getting Started](#getting-started)
* [Optional Packages](#optional-packages)
* [Themes](#themes)
* [Agent Skills](#agent-skills)
* [Selling access to restricted pages](#selling-access-to-restricted-pages)
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
* **Frontend Access** - Restrict pages and protected files to logged-in users or roles
* **Importers** - Importers for WordPress and TYPO3
* **MCP API** - 40+ tools for LLM-driven content management
* **AI Chat** - Let the AI create, refine, translate and publish pages for you
* **Page Metrics** - Views, visits, conversions, search queries and keyword research
* **Webhooks & CDN** - Signed webhooks and CDN purging when content changes
* **Themes** - Ready-to-use themes for many industries
* **Full-text Search** - Across SQLite, MySQL, PostgreSQL and SQL Server
* **Paid Page Access** - Sell subscriptions and one-time access through Stripe, Paddle, or Mollie
* **Scalable** - From single page with SQLite to millions of pages with DB clusters
* **Open Source** - Fully open source

## Tech Stack

| Technology | Purpose |
|---|---|
| **PHP 8.2+** | Backend language |
| **Laravel 11.x / 12.x / 13.x** | Web framework |
| **Vue.js 3** | Admin panel frontend |
| **Vuetify** | Admin UI component library |
| **Lighthouse** | GraphQL API |
| **Prisma PHP** | Provider-neutral AI/LLM integration |
| **Laravel Scout** | Full-text search |
| **Laravel MCP** | MCP server |
| **Vite** | Frontend build tool |

## Architecture

PagibleAI CMS is a modular monorepo split into focused sub-packages. `aimeos/pagible` installs
the core, admin, AI, GraphQL, search, JSON:API, MCP, metrics and theme packages, all others are
optional:

* **Core** provides the data models (Page, Element, File, Version), multi-tenancy, permissions, and migrations
* **Admin** delivers the Vue.js-based admin panel with drag & drop page management
* **GraphQL** exposes the admin API via Lighthouse for content editing
* **JSON:API** provides a read-only frontend API for content delivery
* **AI** integrates LLM providers through Prisma PHP for chat, content generation, image manipulation, transcription and translation
* **Search** implements a custom Laravel Scout engine supporting FTS5 (SQLite), MATCH/AGAINST (MySQL/MariaDB), tsvector (PostgreSQL), and CONTAINSTABLE (SQL Server)
* **MCP** offers 40+ tools for LLM-driven content management
* **Metrics** adds page metrics and keyword research to the admin panel, GraphQL and MCP
* **Backup** provides per-tenant backup and restore with media files, integrity verification, and cross-tenant support
* **Import** provides importers from external CMS platforms (WordPress, TYPO3)
* **Cashier** sells frontend page access through subscriptions or one-time payments using Stripe, Paddle, or Mollie
* **CDN** purges changed pages and removed files from Cloudflare, Fastly, Nginx, Varnish and Symfony HttpCache
* **Webhooks** delivers signed, tenant-scoped notifications for CMS events through the Laravel queue
* **Pulse** adds CMS metrics to the Laravel Pulse dashboard
* **Theme** handles frontend rendering, navigation, search, sitemaps and complete-page caching with Blade templates
* **Themes** contains high-quality frontend themes

Pages are organized as a nested set tree. All content changes are tracked as immutable version snapshots — editors see the latest draft while the public sees the published version. Anonymous page responses are cached completely and invalidated automatically when pages are published, deleted or their access rules change.

## Project Structure

```
pagible/
├── src/          Meta-package (install orchestrator + serve command)
├── core/         Models, permissions, tenancy, versioning, publishing, migrations
├── admin/        Vue.js admin panel (Vuetify + Vite)
├── ai/           AI features (Prisma PHP)
├── graphql/      GraphQL API (Lighthouse)
├── search/       Full-text search (Laravel Scout)
├── jsonapi/      Read-only JSON:API
├── mcp/          MCP server (40+ tools)
├── metrics/      Page metrics and keyword research
├── theme/        Frontend rendering (Blade templates)
├── themes/       Frontend themes
├── backup/       Backup and restore
├── benchmark/    Benchmark commands and seeder
├── cashier/      Shared payment-backed frontend access
├── cashier-*/    Stripe, Paddle, and Mollie Cashier adapters
├── cdn/          CDN and caching proxy purging
├── import/       CMS importers (WordPress, TYPO3)
├── pulse/        Laravel Pulse integration
├── webhooks/     Webhook delivery for CMS events
├── tests/        Shared test infrastructure
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
```

The `cms:install` command publishes the files, migrates the database and adds the commands
updating the published CMS files after each `composer update` to the `post-update-cmd` script
in your `composer.json`.

Fresh Laravel applications contain a static `public/robots.txt` file which web servers serve
before Laravel receives the request. `cms:install` removes it if it contains the default rules
of Laravel, so Pagible's dynamic route serves the published rules together with the generated
sitemap URLs. If the file contains custom rules, copy them into the `robots.txt` configuration
element of the root page, publish the change and remove the file afterwards.

Change the `APP_URL` setting in the `.env` file of your application to your domain. If you are
using `php artisan cms:serve` for testing, add the port of the internal web server
(`APP_URL=http://localhost:8000`), otherwise uploaded files won't be loaded. `cms:install`
warns you if the port is missing.

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

For a quick local test, start the built-in web server with `php artisan cms:serve` and open
`http://localhost:8000/cmsadmin`.

## Optional Packages

Install these packages in addition to `aimeos/pagible` if you need their features:

| Package | Purpose |
|---|---|
| [aimeos/pagible-backup](backup/README.md) | Per-tenant backup and restore including media files (`cms:backup`, `cms:restore`) |
| [aimeos/pagible-import](import/README.md) | Import content from WordPress (`cms:wp-import`) and TYPO3 (`cms:t3-import`) |
| [aimeos/pagible-cdn](cdn/README.md) | Purge changed pages and removed files from CDNs and caching proxies |
| [aimeos/pagible-webhooks](webhooks/README.md) | Signed webhook notifications for CMS lifecycle events |
| [aimeos/pagible-pulse](pulse/README.md) | CMS cards for the Laravel Pulse dashboard |
| [aimeos/pagible-cashier-stripe](cashier/README.md) | Sell page access via Stripe (also `-paddle` and `-mollie`) |

## Themes

The `themes/` directory contains independently installable themes, e.g. for portfolios, blogs,
news sites, restaurants, real estate, fashion, medical practices and craftsmen. Install one with:

```bash
composer require aimeos/pagible-themes-aura
php artisan vendor:publish --tag=cms-theme
```

Each theme's README (e.g. [themes/aura](themes/aura/README.md)) describes its design, page types
and settings. Theme options like the page cache are documented in the [theme package](theme/README.md).

## Agent Skills

The [pagible-themes](skills/pagible-themes/SKILL.md) skill teaches coding agents to build content elements
and themes. It works with Claude Code, Codex, Cursor, OpenCode and other agents supporting
[Agent Skills](https://agentskills.io). Install it straight from GitHub with:

```bash
npx skills add aimeos/pagible --skill pagible-themes
```

## Selling access to restricted pages

Install exactly one Pagible Cashier provider in the Laravel application that
already contains Pagible CMS:

```bash
composer require aimeos/pagible-cashier-stripe
# or: aimeos/pagible-cashier-paddle
# or: aimeos/pagible-cashier-mollie

php artisan cms:install:cashier
```

For Stripe, an interactive installer offers to create the webhook when
`APP_URL`, `STRIPE_KEY`, and `STRIPE_SECRET` are ready. Automated installations
must opt in with `--webhook`.

Add Pagible's `CashierAccess` trait and the selected provider's `Billable` trait
to the application user model. Then configure the provider credentials and
verified webhooks, define a frontend access value such as `premium-members`, and
run the local readiness check:

```bash
php artisan cms:cashier:check
```

Use that same access value in both places in the CMS admin:

1. Add a `pricing` element to a public sales page and select `premium-members` as
   the package's frontend access role. Add a subscription price, a one-time
   price, or both, then publish the pricing content.
2. Open the page being sold, choose **Access**, select **Restricted**, add
   `premium-members`, and apply the change.

Verified payment webhooks grant and revoke the role automatically. A successful
one-time payment grants permanent access unless it is fully refunded or charged
back. A subscription grants access through its provider-confirmed paid period.
See the [Pagible Cashier guide](cashier/README.md) for the user-model example,
access-catalog setup, provider-specific price fields, webhook configuration,
private files, cancellations, and an end-to-end checklist.

## Configuration

File uploads use the Laravel disk configured by `CMS_DISK` and are public by default. Set
`CMS_PRIVATE_DISK` to a different, non-public Laravel disk to enable the translated
“Protect with page access” option in file fields. Temporary URLs generated by remote private
disks expire after `CMS_PRIVATE_TTL` seconds (300 by default); local private files are streamed
through the access-controlled CMS route.

To generate texts/images from prompts, analyze image/video/audio content, or execute actions based
on your prompts, you have to configure one or more of the AI service providers supported by the
[Prisma](https://php-prisma.org/#supported-providers) package.

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
CMS_AI_REFINE_API_KEY="${OPENAI_API_KEY}"
CMS_AI_WRITE_API_KEY="${GEMINI_API_KEY}"
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

For best results and all features, you need Google, OpenAI, Clipdrop, and DeepL at the moment and they are also configured by default. If you want to use a different provider or model, you can configure them in your `.env` file too (e.g. `CMS_AI_WRITE=anthropic` and `CMS_AI_WRITE_MODEL=...`). Please have a look into the published `config/cms/ai.php` file ([source](ai/config/cms/ai.php)) for all environment variables.

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

PagibleAI CMS registers its scheduled tasks automatically:

* `cms:publish` every 30 minutes to publish scheduled versions
* `model:prune` daily to remove soft-deleted pages, elements and files older than `cms.prune` days (30 by default)

You only have to make sure the Laravel scheduler runs, e.g. by adding this cron entry on your server:

```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Useful commands for maintenance:

* `php artisan cms:index` rebuilds the full-text search index
* `php artisan cms:publish` publishes all versions whose publish date has passed

## Multi-domain

Using multiple page trees with different domains is possible by adding `CMS_MULTIDOMAIN=true` to your `.env` file.

## Multi-tenancy

PagibleAI CMS supports single database multi-tenancy using existing Laravel tenancy packages or code implemented by your own.

The [Tenancy for Laravel](https://tenancyforlaravel.com/) package is most often used. How to set up the package is described in the [Multi-tenancy SaaS Setup](https://pagible.com/multi-tenancy-saas-setup) article.

## MCP API

PagibleAI CMS offers 40+ tools within the Laravel MCP API that LLMs and AI agents like Claude, ChatGPT or Cursor can use to search, create, edit, translate and publish pages, elements and files. Please have a look at the [PagibleAI MCP documentation](https://pagible.com/configure-mcp) page for details how to set up the MCP API and Passport for authentication.

## Security

If you find a security related issue, please contact `security at aimeos.org`.

* Structured input is validated against JSON schemas and HTML is sanitized with HTMLPurifier
* SVG uploads are sanitized and uploads enforce MIME type, size and pixel limits
* Content Security Policy (CSP) headers are enforced
* Forms are protected with HCaptcha
* All API endpoints are rate-limited and GraphQL queries have depth and complexity limits
* Remote URLs are validated and resolved hosts are pinned to prevent SSRF
* Protected files are only delivered after access checks with private, no-store responses

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
* Additional CMS importers (Drupal, Joomla, Statamic)
* More themes

## License

PagibleAI CMS is licensed under the [MIT](LICENSE).

## Acknowledgements

Special thanks to:
- Lwin Min Oo

## Links

* Website: [pagible.com](https://pagible.com)
* GitHub: [github.com/aimeos/pagible](https://github.com/aimeos/pagible)
