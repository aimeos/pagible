# Pagible GraphQL

GraphQL API for [Pagible CMS](https://pagible.com) built on Lighthouse. Provides full CRUD for pages, elements, files, and metrics.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible). For full installation, use:

```bash
composer require aimeos/pagible
```

## Configuration

GraphQL-specific configuration is available in `config/cms/graphql.php`:

| Option | Env Variable | Default | Description |
|--------|-------------|---------|-------------|
| `maxdepth` | `CMS_GRAPHQL_MAXDEPTH` | `15` | Maximum query nesting depth |
| `maxcomplexity` | `CMS_GRAPHQL_MAXCOMPLEXITY` | `10000` | Maximum query complexity score |

The upload policy is shared by every CMS interface and configured through `upload.filesize` and `upload.mimetypes` in `config/cms.php`.

## Page access

The `Page.restricted` field tells page viewers whether immediate frontend access rules exist without exposing their values. `Page.access` requires `access:view` and represents the rules independently from page versions:

- `null` means public access
- an empty list permits authenticated users of the current tenant
- a non-empty list permits users granted any listed access value

Publishers with `access:view` can replace this state with `setPageAccess(id:, access:, descendants:)`. The nullable `access` argument must be provided explicitly. Multiple selected page IDs are supported; recursive changes are limited to one root page. Page bulk operations are limited to 1,000 unique pages, and recursive calls fail before writing if the resolved subtree exceeds 1,000 pages. The mutation requires both `page:publish` and `access:view` because access changes affect the live site immediately. Available named values remain exposed by the separately protected `Query.access` catalog.

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

MIT
