# PagibleAI CMS - Simple as WordPress, the power of Contentful!

The easy, flexible and scalable API-first PagibleAI CMS package for Laravel.

* [About](#about)
* [Features](#features)
* [Tech Stack](#tech-stack)
* [Architecture](#architecture)
* [Installation](#installation)
* [Configuration](#configuration)
* [Packages](#packages)
* [Themes](#themes)
* [MCP API](#mcp-api)
* [Agent Skills](#agent-skills)
* [Security](#security)
* [Contributing](#contributing)
* [License](#license)

## About

PagibleAI CMS is an API-first content management system you can install into any Laravel
application. It combines the ease of use of WordPress with the structured content of Contentful,
and uses AI to write, translate and publish content and to create and edit images.

From a simple blog on SQLite to multi-tenant, multi-domain sites with millions of pages,
PagibleAI scales with your needs.

## Features

* **Structured Content** - Manage structured content like in Contentful
* **AI-Powered** - AI generates and enhances drafts and images
* **AI Chat** - Let the AI create, refine, translate and publish pages for you
* **Hierarchical Pages** - Page tree with drag & drop
* **Shared Content** - Reuse content elements on multiple pages
* **Versioning** - Save, publish, schedule and revert drafts with a full history
* **Extensible Elements** - Define new content elements in seconds
* **APIs** - Fast JSON:API for the frontend, GraphQL for the admin and 40+ MCP tools for AI agents
* **Multi-language** - Translations with language fallbacks
* **Multi-domain & Multi-tenancy** - Several sites and customers in one application
* **Frontend Access** - Restrict pages and files to logged-in users or roles
* **Paid Page Access** - Sell subscriptions and one-time access via Stripe, Paddle or Mollie
* **Page Metrics** - Views, visits, conversions, search queries and keyword research
* **Full-text Search** - On SQLite, MySQL/MariaDB, PostgreSQL and SQL Server
* **Webhooks & CDN** - Signed webhooks and CDN purging when content changes
* **Importers** - Import from WordPress, TYPO3, Drupal and Joomla
* **Themes** - Ready-to-use themes for many industries
* **Open Source** - MIT licensed

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
* **Import** provides importers from external CMS platforms (WordPress, TYPO3, Drupal, Joomla)
* **Cashier** sells frontend page access through subscriptions or one-time payments using Stripe, Paddle, or Mollie
* **CDN** purges changed pages and removed files from Cloudflare, Fastly, Nginx, Varnish and Symfony HttpCache
* **Webhooks** delivers signed, tenant-scoped notifications for CMS events through the Laravel queue
* **Pulse** adds CMS metrics to the Laravel Pulse dashboard
* **Theme** handles frontend rendering, navigation, search, sitemaps and complete-page caching with Blade templates
* **Themes** contains high-quality frontend themes

Pages are organized as a nested set tree. All content changes are tracked as immutable version snapshots — editors see the latest draft while the public sees the published version. Anonymous page responses are cached completely and invalidated automatically when pages are published, deleted or their access rules change.

## Installation

Requirements: PHP 8.2+ and Laravel 11, 12 or 13. If you don't have a Laravel application yet,
create one first:

```bash
composer create-project laravel/laravel pagible
cd pagible
```

Then install PagibleAI CMS and give a user access to the admin panel (the user is created if it
doesn't exist):

```bash
composer req aimeos/pagible
php artisan cms:install
php artisan cms:user -e editor@example.com
```

Set `APP_URL` in your `.env` file to your domain. For a quick local test, use
`APP_URL=http://localhost:8000`, start the web server with `php artisan cms:serve` and open
`http://localhost:8000/cmsadmin`. On your own domain, the admin panel is available at
`https://mydomain.tld/cmsadmin`.

Roles and permissions are described on the
[authorization and permissions](https://pagible.com/authorization-and-permissions) page.

Make sure the Laravel scheduler runs so scheduled pages get published and deleted items get
removed after 30 days:

```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Configuration

### AI providers

Configure API keys only for the AI providers you want to use. By default, PagibleAI uses Google
Gemini, OpenAI, Clipdrop and DeepL:

```
GEMINI_API_KEY="..."
OPENAI_API_KEY="..."
CLIPDROP_API_KEY="..."
DEEPL_API_KEY="..."

CMS_AI_WRITE_API_KEY="${GEMINI_API_KEY}"
CMS_AI_REFINE_API_KEY="${OPENAI_API_KEY}"
CMS_AI_TRANSLATE_API_KEY="${DEEPL_API_KEY}"
```

Each AI feature can use any provider and model supported by
[Prisma](https://php-prisma.org/#supported-providers), e.g. `CMS_AI_WRITE=anthropic`. All
features and settings are listed in the [AI package](ai/README.md).

### Files

Uploads are stored on the Laravel disk set by `CMS_DISK`. To protect files by page access, set
`CMS_PRIVATE_DISK` to a non-public disk.

### Multi-domain and multi-tenancy

Add `CMS_MULTIDOMAIN=true` to your `.env` file to use separate page trees per domain. For
multi-tenancy, PagibleAI works with packages like [Tenancy for Laravel](https://tenancyforlaravel.com/),
see the [multi-tenancy SaaS setup](https://pagible.com/multi-tenancy-saas-setup).

### Spam protection

Protect the contact form with [hCaptcha](https://pagible.com/configure-hcaptcha).

## Packages

Add these optional packages to `aimeos/pagible` if you need them:

| Package | Purpose |
|---|---|
| [aimeos/pagible-backup](backup/README.md) | Backup and restore including media files |
| [aimeos/pagible-import](import/README.md) | Import content from WordPress, TYPO3, Drupal and Joomla |
| [aimeos/pagible-cashier-stripe](cashier/README.md) | Sell page access via Stripe (also `-paddle` and `-mollie`) |
| [aimeos/pagible-cdn](cdn/README.md) | Purge changed pages from CDNs and caching proxies |
| [aimeos/pagible-webhooks](webhooks/README.md) | Signed webhooks for CMS events |
| [aimeos/pagible-pulse](pulse/README.md) | CMS cards for the Laravel Pulse dashboard |

## Themes

Choose from ready-made themes for portfolios, blogs, news sites, restaurants, real estate, fashion,
medical practices, craftsmen and more:

```bash
composer require aimeos/pagible-themes-aura
php artisan vendor:publish --tag=cms-theme
```

Each theme's README (e.g. [themes/aura](themes/aura/README.md)) describes its design and settings.

## MCP API

AI agents like Claude, ChatGPT or Cursor can search, create, edit, translate and publish pages,
elements and files using 40+ MCP tools. See the [MCP documentation](https://pagible.com/configure-mcp)
for the setup.

## Agent Skills

The [pagible-themes](skills/pagible-themes/SKILL.md) skill teaches coding agents like Claude Code,
Codex or Cursor to build content elements and themes:

```bash
npx skills add aimeos/pagible --skill pagible-themes
```

## Security

Please report security issues to `security at aimeos.org`.

## Contributing

Contributions are welcome! Fork the repository, create a feature branch and open a pull request.
Please make sure `vendor/bin/phpunit` passes and `vendor/bin/phpstan analyze` reports no new errors.

## License

PagibleAI CMS is licensed under the [MIT](LICENSE) license.

Special thanks to Lwin Min Oo.

* Website: [pagible.com](https://pagible.com)
* GitHub: [github.com/aimeos/pagible](https://github.com/aimeos/pagible)
