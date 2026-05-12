# Pagible GraphQL

GraphQL API for [Pagible CMS](https://pagible.com) built on Lighthouse. Provides full CRUD for pages, elements, files, and metrics.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible). For full installation, use:

```bash
composer require aimeos/pagible
```

## Configuration

After installation, the configuration is available in `config/cms/graphql.php`:

| Option | Env Variable | Default | Description |
|--------|-------------|---------|-------------|
| `filesize` | `CMS_GRAPHQL_FILESIZE` | `50` | Maximum file upload size in MB |
| `mimetypes` | `CMS_GRAPHQL_MIMETYPES` | See below | Allowed MIME types for uploads (comma-separated in env) |

Default allowed MIME types: `application/gzip`, `application/pdf`, `application/vnd.*`, `application/zip`, `audio/*`, `image/*`, `text/*`, `video/*`

## Commands

### cms:install:graphql

Installs the Pagible GraphQL package.

```bash
php artisan cms:install:graphql
```

Publishes the Lighthouse schema and configuration, registers CMS models/mutations/queries in the Lighthouse config, and adds the CMS schema import to `graphql/schema.graphql`.

### cms:benchmark:graphql

Runs GraphQL mutation and query benchmarks.

```bash
php artisan cms:benchmark:graphql [options]
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
