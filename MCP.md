# PagibleAI MCP Server

PagibleAI CMS provides an [MCP](https://modelcontextprotocol.io/) server with tools for managing pages, shared content elements, media files, and AI-powered content operations. Any MCP-compatible client (ChatGPT, Claude, Codex, VS Code Copilot, etc.) can connect and manage your CMS content.

## Server Setup

### 1. Laravel Breeze for authentication

```bash
composer require laravel/breeze
php artisan breeze:install blade --dark
```

**Important:** CSS/JS builds are not added to Git by default when using version control. Use:

```bash
git add -f ./public/build/
```

### 2. OAuth with Laravel Passport

```bash
php artisan install:api --passport
```

#### Update User model

Update `./app/Models/User.php` file with:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
}
```

#### Add API guard

In `./config/auth.php` add in `guards`:

```php
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'passport',
            'provider' => 'users',
        ],
    ],
```

#### Add Passport keys

```bash
php artisan passport:keys
```

Add to environment variables:

```
PASSPORT_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----
<private key here>
-----END RSA PRIVATE KEY-----"

PASSPORT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----
<public key here>
-----END PUBLIC KEY-----"
```

In Kubernetes, create secrets for the private/public key:

```bash
kubectl create secret generic pagible-oauth-private --from-file=oauth-private=storage/oauth-private.key -n <namespace>
kubectl create secret generic pagible-oauth-public --from-file=oauth-public=storage/oauth-public.key -n <namespace>
```

Use secrets in Kubernetes YAML file as environment variables:

```yml
        - name: PASSPORT_PRIVATE_KEY
          valueFrom:
            secretKeyRef:
              name: pagible-dev-oauth-private
              key: oauth-private
        - name: PASSPORT_PUBLIC_KEY
          valueFrom:
            secretKeyRef:
              name: pagible-dev-oauth-public
              key: oauth-public
```

### 3. Install Laravel MCP

```bash
php artisan vendor:publish --provider=Laravel\\Mcp\\Server\\McpServiceProvider
```

In `./routes/ai.php`:

```php
Mcp::oauthRoutes();
Mcp::web('/mcp/cms', \Aimeos\Cms\Mcp\CmsServer::class)->middleware('auth:api');
```

Setup for Passport:

```bash
php artisan vendor:publish --tag=mcp-views
```

Update `./app/Providers/AppServiceProvider.php`:

```php
use Laravel\Passport\Passport;

public function boot(): void
{
    Passport::authorizationView(function ($parameters) {
        return view('mcp.authorize', $parameters);
    });
}
```

## Client Configuration

The MCP server URL is `https://example.com/mcp/cms/` for example. Replace `example.com` with your actual server domain.

### ChatGPT

In ChatGPT, go to **Settings → Apps → Create**. Enter a name (e.g. "Pagible") and paste the MCP server URL. Available on Pro, Team, Enterprise, and Edu plans.

### Claude.ai

In Claude.ai, go to **Settings → Connectors → Add custom connector**. Paste the MCP server URL and complete the OAuth flow. Available on Free (1 connector), Pro, Max, Team, and Enterprise plans.

### Claude Code (CLI)

```bash
claude mcp add --transport http --scope user pagible https://example.com/mcp/cms/
```

### Claude Desktop

In `claude_desktop_config.json` (macOS: `~/Library/Application Support/Claude/`, Windows: `%APPDATA%\Claude\`):

```json
{
  "mcpServers": {
    "pagible": {
      "type": "http",
      "url": "https://example.com/mcp/cms/"
    }
  }
}
```

### OpenAI Codex CLI

In `~/.codex/config.toml` (global) or `.codex/config.toml` (project-scoped):

```toml
[mcp_servers.pagible]
url = "https://example.com/mcp/cms/"
```

Then authenticate via OAuth:

```bash
codex mcp login pagible
```

### VS Code / GitHub Copilot

In `.vscode/mcp.json` in your project root:

```json
{
  "servers": {
    "pagible": {
      "type": "http",
      "url": "https://example.com/mcp/cms/"
    }
  }
}
```

## Key Concepts

**Draft/Publish workflow:** Creating or updating pages, elements, and files produces a draft version. Use the `publish-*` tools to make changes live. Scheduled publishing is supported via the `at` parameter (ISO 8601 datetime).

**Permissions:** Each tool requires a specific permission (e.g. `page:view`, `page:add`, `file:publish`). The user's `cmsperms` JSON array column controls access. See `Permission.php` for the full list.

**Multi-tenancy:** All operations are scoped to the authenticated user's tenant. Content from other tenants is never visible or modifiable.
