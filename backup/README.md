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
| `cms.disks.public.name` | `public` | Storage disk for public media files |
| `cms.disks.private.name` | `local` | Storage disk for protected media files |

The public and private disk names must be different.

Named tenant IDs must contain 1-100 ASCII letters, digits, underscores, or hyphens and must not
be UUIDs. The empty ID remains valid for the default tenant.

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

Creates a ZIP archive named `pagible-{tenant}-{timestamp}.zip` containing NDJSON exports of all `cms_*` tables and the public and protected media owned by the exported File records and their historical versions. Soft-deleted Files remain recoverable. Remote hot-links, orphaned storage objects, and objects belonging to other tenants are not copied.

The signed manifest contains SHA-256 checksums for every database and media entry. Its HMAC signature uses the application's `APP_KEY`, so restoring the archive in another installation requires the same key. Backup ZIPs are not encrypted and can contain protected media; keep the backup disk private or encrypt the archives at rest.

Backup, restore, File catalog commits, relocation, and cleanup share one per-tenant media gate.
Slow upload preparation remains outside the gate because prepared objects use new immutable paths.
Only one of these operations can commit or move media for a tenant at a time.

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
| `--no-media` | | Skip media files; reject file disk changes and opposite-disk leftovers |
| `--media-only` | | Only restore media files; require live files to use the archived disks |
| `--list` | | List available backups |
| `--verify` | | Verify backup integrity without restoring |
| `--force` | | Skip confirmation prompts |

Every restore verifies the signed manifest and all database and media checksums before writing anything. `--verify` performs the same verification without restoring. Media from the backup overwrites existing objects on its archived logical disk. Before each overwrite or opposite-disk deletion, restore journals the previous object in a private local temporary directory. Database import and disk reconciliation then commit together; on failure, the previous media is restored and newly created objects are removed. Ensure the application storage directory has enough free space for the media that may be overwritten. A normal restore leaves each local File path on at most its catalog disk.

CMS UUIDs are global primary keys. A cross-tenant restore therefore fails before writing media when
an archived entity ID still belongs to another tenant. Remove the source records first or remap their
IDs externally when both copies must coexist.

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

MIT
