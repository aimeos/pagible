# Pagible Backup

Backup and restore for [Pagible CMS](https://pagible.com) with per-tenant data export, media files, integrity verification, and cross-tenant restore. Supports SQLite, MySQL, MariaDB, PostgreSQL, and SQL Server.

For installation, use:

```bash
composer require aimeos/pagible-backup
```

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Configuration

The backup package uses these settings from the core configuration (`config/cms.php`):

| Config Key | Default | Description |
|------------|---------|-------------|
| `cms.db` | `sqlite` | Database connection name |
| `cms.disk` | `public` | Storage disk for media files |

## Commands

### cms:backup

Creates a backup of all CMS data for a tenant.

```bash
php artisan cms:backup [options]
```

| Option | Default | Description |
|--------|---------|-------------|
| `--tenant` | current tenant | Tenant ID to backup |
| `--disk` | `local` | Storage disk for the backup ZIP file |
| `--keep` | | Number of backups to keep (deletes oldest) |
| `--no-media` | | Skip media files |

Creates a ZIP archive named `pagible-{tenant}-{timestamp}.zip` containing NDJSON exports of all `cms_*` tables and media files. Includes a manifest with SHA-256 checksums for integrity verification.

### cms:restore

Restores CMS data from a backup.

```bash
php artisan cms:restore [file] [options]
```

| Option | Default | Description |
|--------|---------|-------------|
| `file` | | Backup ZIP filename |
| `--tenant` | from manifest | Target tenant ID (enables cross-tenant restore) |
| `--disk` | `local` | Storage disk containing the backup |
| `--merge` | | Merge (upsert) instead of replacing existing data |
| `--no-media` | | Skip media files |
| `--media-only` | | Only restore media files |
| `--list` | | List available backups |
| `--verify` | | Verify backup integrity without restoring |
| `--force` | | Skip confirmation prompts |

Examples:

```bash
# List available backups
php artisan cms:restore --list

# Verify backup integrity
php artisan cms:restore pagible-tenant1-20250101.zip --verify

# Restore to a different tenant
php artisan cms:restore pagible-tenant1-20250101.zip --tenant=tenant2

# Merge without replacing existing data
php artisan cms:restore pagible-tenant1-20250101.zip --merge
```

## Events

| Event | Properties | Description |
|-------|------------|-------------|
| `BackupCreated` | `$tenant`, `$path`, `$counts` | Dispatched after successful backup |
| `RestoreCompleted` | `$tenant`, `$file`, `$counts` | Dispatched after successful restore |
| `RestoreFailed` | `$tenant`, `$error` | Dispatched when restore fails |

## License

LGPL-3.0-only
