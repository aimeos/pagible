# Pagible Search

Full-text search engine for [Pagible CMS](https://pagible.com) using Laravel Scout. Supports SQLite FTS5, MySQL FULLTEXT, PostgreSQL tsvector, and SQL Server CONTAINSTABLE.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible). For full installation, use:

```bash
composer require aimeos/pagible
```

## Configuration

This package uses Laravel Scout configuration (`config/scout.php`). The installer sets the default driver to `cms` and enables soft deletes.

## Commands

### cms:install:search

Installs the Pagible Search package.

```bash
php artisan cms:install:search
```

Publishes the Laravel Scout config and sets the default driver to `cms` with soft delete support enabled.

### cms:index

Rebuilds the full-text search index for all pages, elements, and files.

```bash
php artisan cms:index
```

Processes all records (including trashed) in chunks, updating the search index. Each model stores two index rows per item: one for draft content (`latest=true`) and one for published content (`latest=false`).

## License

MIT
