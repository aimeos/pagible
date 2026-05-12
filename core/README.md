# Pagible Core

Core package for [Pagible CMS](https://pagible.com) providing models, permissions, tenancy, utilities, and migrations.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible). For full installation, use:

```bash
composer require aimeos/pagible
```

## Configuration

After installation, the configuration is available in `config/cms.php`:

| Option | Default | Description |
|--------|---------|-------------|
| `roles` | `['admin' => ['*'], ...]` | Named role definitions mapping to permission sets. Supports wildcards (`page:*`, `*:view`, `*`) and denials (`!page:purge`) |
| `broadcast` | `false` | Enable real-time broadcasting via Laravel Reverb so other editors see changes immediately |
| `db` | `sqlite` | Database connection name (references `config/database.php`) |
| `disk` | `public` | Filesystem disk for uploaded files |
| `image.preview-sizes` | `[480, 960, 1920]` | Preview image widths in pixels for uploaded images |
| `locales` | `en,ar,zh,fr,de,es,pt,pt-BR,ru` | Comma-separated ISO language codes. First locale is the default for new content |
| `multidomain` | `false` | Enable domain-based page routing |
| `navdepth` | `2` | Maximum depth of the navigation tree menu |
| `prune` | `30` | Days before soft-deleted items are permanently removed. Set to `false` to disable |
| `versions` | `10` | Maximum number of versions to retain per page, element, or file |

### Default Roles

| Role | Permissions |
|------|-------------|
| `admin` | All permissions (`*`) |
| `viewer` | View-only access |
| `publisher` | All except publish and purge |
| `editor` | All except publish and purge |

## Commands

### cms:install:core

Installs the Pagible CMS core package.

```bash
php artisan cms:install:core [--seed]
```

| Option | Description |
|--------|-------------|
| `--seed` | Add example pages to the database |

Publishes config, creates the SQLite database if needed, runs migrations, and optionally seeds example content.

### cms:user

Manages CMS user authorization.

```bash
php artisan cms:user [email] [options]
```

| Option | Description |
|--------|-------------|
| `email` | Email address of the user (creates if new) |
| `-a`, `--add=PERM` | Add permissions (repeatable, supports wildcards) |
| `-d`, `--disable` | Disable all permissions |
| `-e`, `--enable` | Enable all permissions (`*`) |
| `-l`, `--list` | List all permissions of the user |
| `-p`, `--password=PWD` | Set password (prompts if omitted during creation) |
| `-r`, `--remove=PERM` | Remove permissions (repeatable, supports wildcards) |
| `--role=ROLE` | Add a named role (e.g., `editor`, `publisher`, `admin`) |
| `--roles` | List all available roles and their permissions |

### cms:publish

Publishes scheduled versions where `publish_at` has passed. Registered to run automatically every 30 minutes.

```bash
php artisan cms:publish
```

### cms:benchmark:core

Runs core model performance benchmarks.

```bash
php artisan cms:benchmark:core [options]
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
