# Pagible Benchmark

Performance benchmarks for [Pagible CMS](https://pagible.com) development. They seed a separate tenant with generated pages, elements and files and measure the core models, GraphQL, JSON:API, MCP, search and theme rendering.

For installation as development dependency, use:

```bash
composer require --dev aimeos/pagible-benchmark
```

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Commands

### cms:benchmark

Seeds or removes the benchmark data and runs all benchmarks of the installed packages.

```bash
php artisan cms:benchmark --seed [options]
php artisan cms:benchmark [options]
php artisan cms:benchmark --unseed [options]
```

The single benchmarks can be run separately using `cms:benchmark:core`, `cms:benchmark:graphql`, `cms:benchmark:jsonapi`, `cms:benchmark:mcp`, `cms:benchmark:search` and `cms:benchmark:theme` with the same options except `--seed`, `--pages` and `--chunk` (`cms:benchmark:search --seed` indexes the benchmark data first).

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
