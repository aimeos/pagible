# Pagible MCP

MCP server for [Pagible CMS](https://pagible.com) with 33 tools for managing pages, elements, files, and AI-powered content operations. See [MCP.md](MCP.md) for setup and usage.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible). For full installation, use:

```bash
composer require aimeos/pagible
```

## Commands

### cms:install:mcp

Installs the Pagible MCP package.

```bash
php artisan cms:install:mcp
```

Publishes the Laravel MCP routes.

### cms:benchmark:mcp

Runs MCP tool performance benchmarks across pages, elements, and files.

```bash
php artisan cms:benchmark:mcp [options]
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
