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
| `lock` | `30` | Page-tree write-lock lifetime and maximum acquisition wait in seconds (`CMS_LOCK`) |
| `multidomain` | `false` | Enable domain-based page routing |
| `navdepth` | `2` | Maximum depth of the navigation tree menu |
| `prune` | `30` | Days before soft-deleted items are permanently removed. Set to `false` to disable |
| `chunksize` | `100` | Pages fetched per publication batch, or routes/items queued and hydrated per synchronization job (`CMS_CHUNKSIZE`) |
| `upload.filesize` | `50` | Maximum file upload size in MB |
| `upload.mimetypes` | See below | Allowed MIME types or prefixes for all CMS interfaces |
| `versions` | `10` | Maximum number of versions to retain per page, element, or file |

Set the upload policy with `CMS_UPLOAD_FILESIZE` and the comma-separated `CMS_UPLOAD_MIMETYPES`. The default MIME types are `application/gzip`, `application/pdf`, `application/vnd.*`, `application/zip`, `audio/*`, `image/*`, `text/*`, and `video/*`.

### Default Roles

| Role | Permissions |
|------|-------------|
| `admin` | All permissions (`*`) |
| `viewer` | View-only access |
| `publisher` | All except publish and purge |
| `editor` | All except publish and purge |

### Stancl tenancy mode

Pagible remains usable without tenancy and with custom `Tenancy::$callback` integrations. Applications using `stancl/tenancy` in single-database mode can additionally connect Stancl's tenant lifecycle to Pagible from an application service provider:

```php
\Aimeos\Cms\Tenancy::stancl();
```

Stancl remains the source of tenant identification. Its initialization and termination events replace Pagible's scoped `Tenancy` and `Access` instances, so tenant-specific access catalogs and permission-package scopes cannot survive a tenant switch within the same CLI or worker lifecycle. Disable Stancl's `DatabaseTenancyBootstrapper`; Pagible applies its own `tenant_id` query scopes on the shared database.

Initialize Stancl tenancy before any Pagible middleware or controller queries CMS models. This is especially important for the theme's complete-page cache middleware, which intentionally runs before Laravel's `web` middleware. Prefer applying Stancl's initialization middleware globally before the CMS routes. If only the catch-all page route needs domain initialization, add it to the outer page route group in the application's `config/cms/theme.php`:

```php
'pageroute' => [
    'middleware' => [
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
    ],
],
```

Route-group middleware wraps the built-in `ServeCachedPage` middleware, so `Tenancy::value()` is populated before a cache key or page query is evaluated. Initializing tenancy in `web`, in a controller, or in middleware that runs after `ServeCachedPage` is too late. Apply the same ordering to the search, sitemap, contact, and CSRF routes when those endpoints are tenant-aware.

Enable Stancl's `QueueTenancyBootstrapper` for tenant-aware queued index synchronization. Sync jobs retain an explicit tenant ID for generic integrations. In Stancl mode, the payload initialized by Stancl must match that ID or the job fails before querying. The Scout queue connection must not be marked with Stancl's `central => true` option, and transactions using `afterCommit()` must commit before a `$tenant->run()` context ends.

Tenant scopes protect newly built queries and new CMS models receive the active `tenant_id`. Already-loaded models are not rebound after a tenant switch: ordinary Eloquent saves, deletes, relationship mutations, publishing, and storage cleanup continue to use their stored keys and paths. Do not retain CMS model instances across tenant contexts.

### Access catalog

The access catalog is independent from CMS editor permissions and from any single protected resource. It owns the available values and resolves them through Laravel Gate; page restrictions are one consumer. The normalized catalog is memoized for the current request by tenant, while effective grants are memoized by user and tenant. The underlying provider remains responsible for longer-lived caching:

```php
use Aimeos\Cms\Access;

Access::using(
    list: fn() => app(AccessPermissions::class)->names(),
    add: fn( string $value ) => app(AccessPermissions::class)->add( $value ),
    delete: fn( array $values ) => app(AccessPermissions::class)->delete( $values ),
    grants: fn( $user ) => app(AccessPermissions::class)->grants( $user ),
);
```

`Permission::has('access:view')` reports whether a catalog or package adapter has been configured, and `Access::list()` returns its normalized values. The `add`, `delete`, and `grants` callbacks are optional; without write callbacks the catalog remains read-only. A grant resolver must return all effective frontend-access values for the user, including direct and role-derived values in the active tenant and guard. Its result is authoritative and avoids enumerating the catalog for authenticated page queries. Return `null` for users whose permissions cannot be enumerated, such as blanket access implemented only through `Gate::before()`; Pagible then preserves the catalog-and-Gate fallback. Pass `null` as the list callback to reset custom configuration.

For a supported permission package, call its adapter once from an application service provider instead. Spatie must have its teams migration and `permission.teams` enabled for tenant-specific assignments; Laratrust must have its teams migration and `laratrust.teams.enabled` enabled. Bouncer's adapter selects its built-in tenant scope. Laratrust permission checks are exposed as tenant-aware Laravel Gate definitions:

```php
Access::spatie();
Access::bouncer();
Access::laratrust();
```

Each package adapter accepts the same optional effective-grants resolver, for example `Access::spatie(grants: fn( $user ) => ...)`. Pagible does not derive grants from package assignment APIs automatically because those APIs can bypass application-level `Gate::before()` rules and explicit denials. Applications that can enumerate their complete effective result should provide the callback; otherwise the adapter retains Gate evaluation.

The adapters require these package versions at minimum:

| Adapter | Minimum package version | API used by Pagible |
|---------|-------------------------|---------------------|
| Spatie | `spatie/laravel-permission` 6.2.0 | Permission model, `findOrCreate()`, model cache events, teams, and `PermissionRegistrar::setPermissionsTeamId()` |
| Bouncer | `silber/bouncer` 1.0.2 | Global ability model, `scope()->to()`, and `refresh()` |
| Laratrust | `santigarcor/laratrust` 8.3.0 | Permission model, teams, `isAbleTo()`, and permission gates |

These are runtime contracts, not compatibility probes: calling an adapter without its package, with an older release, or without the documented team configuration is an application configuration error. Applications must install a package release compatible with their Laravel version; the APIs above remain required.

Pagible does not validate the package's team configuration when an adapter is registered. If Spatie teams or Laratrust teams are disabled, permission checks are evaluated globally even though the current user is still required to belong to the active Pagible tenant. A permission assigned for one tenant can therefore authorize the same catalog value in another tenant. This risk applies only to misconfigured installations; with teams enabled, the required migrations installed, and assignments associated with the correct tenant, permission checks remain tenant-scoped.

Choose exactly one adapter. Each adapter exposes and manages the configured package's permission or ability model as the access catalog, so the provider catalog must be dedicated to access values. Use explicit custom callbacks when a provider model is shared with unrelated authorization permissions. Bouncer exposes only global abilities and leaves model-bound abilities untouched. Spatie reads and deletes only permissions for the configured default guard and uses the package's `findOrCreate()` and model events so its cache hooks remain active. Custom Spatie permission models must retain those package contracts and events.

Spatie and Laratrust permission definitions can remain global even when their assignments are team-scoped. Their `access:add` and `access:delete` capabilities therefore authorize management of the shared definition catalog, not merely the current team's assignments. Grant those capabilities only to editors allowed to make that global change.

The scoped `Access` instance activates Spatie or Bouncer lazily before its first operation in each tenant context. It clears its catalog and per-user grant results whenever the tenant changes. The fallback path preserves package hooks, `Gate::before()` rules, and explicit denials. The Spatie adapter also clears the user's loaded `roles` and `permissions` relations before its first grant resolution or Gate check in each tenant context, preventing relations from the previous tenant from being reused.

Configured catalogs register `access:view` as a CMS editor capability. Writable catalogs additionally register `access:add` and `access:delete`; `access:*` expands to the capabilities currently available. `Access::add()` creates an immutable value and `Access::delete()` removes up to 250 values. Deleting a value does not rewrite references held by consumers.

### Frontend page access

Frontend restrictions are stored independently in `cms_page_access`, with one row per access value and `(page_id, value)` as its composite primary key. Each row also stores `tenant_id`. No rows for a page mean public access, one row with an empty value permits an authenticated user accepted by `Tenancy::allows()` for the current tenant, and one or more non-empty values permit such a user when Laravel Gate grants any one of them. Page models are deliberately not passed to Gate.

Restriction writes are rejected while the access catalog is unavailable; releasing existing restrictions remains possible. Configure a callback returning an empty list to enable authentication-only restrictions without named access values. Deleting a catalog value does not rewrite existing page restrictions, which continue to fail closed until they are changed explicitly.

Use `PageAccess::set()` as the supported write API. It applies database-first, chunked changes so public page caches and external search documents are updated consistently:

```php
\Aimeos\Cms\Models\PageAccess::set( [$page->id], ['frontend.member'], auth()->user() );
\Aimeos\Cms\Models\PageAccess::set( [$page->id], null );
\Aimeos\Cms\Models\PageAccess::set( [$page->id], [], auth()->user(), descendants: true );
```

Access-value lists are trimmed, must contain only registered non-empty strings, deduplicated, sorted, and limited to 250 entries of at most 100 characters. An empty list is stored as one empty value for authentication-only access.

After access records have been committed, indexed Laravel Scout drivers are refreshed by queued jobs from the current page state. Bulk page, element, and file publication, deletion, restoration, and edits use the same queued reconciliation for the Pagible `cms` engine and external Scout engines. The `collection` and Laravel `database` drivers query model tables directly and need no index job. Jobs carry only the tenant, model class, and bounded ID list, then hydrate current state when handled. Run a queue worker with an asynchronous queue connection in production; the `sync` connection executes these jobs inline.

Access changes don't modify page-tree coordinates, so they acquire neither the tenant page-tree lock nor page row locks. They load the target pages and current canonical access values once, then update only pages whose values changed in one database transaction. SQL writes remain bounded, while cache invalidation and index synchronization dispatch afterward. Rolled-back changes dispatch nothing. Core emits a lightweight `PageInvalidated` event with affected paths grouped by domain without depending on a rendered-page cache; the theme package removes those rendered routes.

Page bulk operations are limited to 1,000 unique pages. Recursive calls also fail before writing if the resolved subtree exceeds 1,000 pages. Larger queued operations must split explicit page IDs into batches of at most 1,000.

Subtree operations require exactly one root ID belonging to the current tenant and fail before writing when it does not.

Operations compare canonical values and skip unchanged pages. Only changed page routes are invalidated, and search documents are refreshed only when access switches between public and restricted.

Do not persist or delete `PageAccess` instances directly. Those low-level writes deliberately have no cache or search side effects; use `PageAccess::set()` instead.

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

MIT
