# PagibleAI CMS - Simple as Wordpress, the power of Contentful!

The easy, flexible and scalable API-first PagibleAI CMS package:

* Manage structured content like in Contentful
* AI generates/enhances drafts and images
* Hierarchical page tree with drag & drop
* Assign shared content to multiple pages
* Save, publish, schedule and revert drafts
* Full version history and audit trail
* Define new content elements in seconds
* Extremly fast JSON frontend API
* Versatile GraphQL admin API
* Multi-language support
* Multi-domain routing
* Multi-tenancy capable
* Importer for WordPress, etc.
* MCP API with 30+ tools for LLM-driven content management
* Full-text search across SQLite, MySQL, PostgreSQL and SQL Server
* Scales from single page with SQLite to millions of pages with DB clusters
* Fully Open Source

It can be installed into any existing Laravel application.

## Table of content

* [Installation](#installation)
* [Authorization](#authorization)
* [Configuration](#configuration)
* [Maintenance](#maintenance)
* [Multi-domain](#multi-domain)
* [Multi-tenancy](#multi-tenancy)
* [MCP API](#mcp-api)
* [Security](#security)

## Installation

You need a working Laravel installation. If you don't have one, you can create it using:

```bash
composer create-project laravel/laravel pagible
cd pagible
```

The application will be available in the `./pagible` sub-directory.
Then, run this command within your Laravel application directory:

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

### Configuration

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

### Maintenance

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

### Multi-domain

Using multiple page trees with different domains is possible by adding `CMS_MULTIDOMAIN=true` to your `.env` file.

### Multi-tenancy

PagibleAI CMS supports single database multi-tenancy using existing Laravel tenancy packages or code implemented by your own.

The [Tenancy for Laravel](https://tenancyforlaravel.com/) package is most often used. How to set up the package is described in the [Multi-tenancy SaaS Setup](/multi-tenancy-saas-setup) article.

## MCP API

PagibleAI CMS offers tools within the Laravel MCP API that LLMs can use to interact with the CMS. Please have a look at the [PagibleAI MCP documentation](https://pagible.com/configure-mcp) page for details how to set up the MCP API and Passport for authentication.

## Security

If you find a security related issue, please contact `security at aimeos.org`.

Special thanks to:
- Lwin Min Oo
