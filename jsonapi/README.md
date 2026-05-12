# Pagible JSON:API

Read-only JSON:API server for [Pagible CMS](https://pagible.com) built on Laravel JSON:API.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible). For full installation, use:

```bash
composer require aimeos/pagible
```

## Configuration

After installation, the configuration is available in `config/cms/jsonapi.php`:

| Option | Env Variable | Default | Description |
|--------|-------------|---------|-------------|
| `maxdepth` | `CMS_JSONAPI_MAXDEPTH` | `1` | Maximum depth of included relationships (e.g., 1 = `include=children`, 2 = `include=children,children.children`) |

## Commands

### cms:install:jsonapi

Installs the Pagible JSON:API package.

```bash
php artisan cms:install:jsonapi
```

Publishes the Laravel JSON:API config, registers the CMS server, and adds the JSON:API exception handler to `bootstrap/app.php`.

### cms:benchmark:jsonapi

Runs read-only JSON:API benchmarks.

```bash
php artisan cms:benchmark:jsonapi [options]
```

| Option | Default | Description |
|--------|---------|-------------|
| `--tenant` | `benchmark` | Tenant ID |
| `--domain` | | Domain name |
| `--seed` | | Seed benchmark data first |
| `--pages` | `10000` | Number of pages to generate |
| `--tries` | `100` | Iterations per benchmark |
| `--chunk` | `50` | Rows per bulk insert batch |
| `--unseed` | | Remove benchmark data and exit |
| `--force` | | Run in production |

## License

LGPL-3.0-only
