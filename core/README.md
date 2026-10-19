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
| `db` | `sqlite` | Database connection name from `CMS_DB_CONNECTION`, falling back to `DB_CONNECTION` |
| `disks.public.name` | `public` | Filesystem disk for public uploads (`CMS_DISK`) |
| `disks.private.name` | `local` | Filesystem disk for page-access-protected uploads (`CMS_PRIVATE_DISK`) |
| `disks.private.ttl` | `300` | Lifetime in seconds of temporary private storage URLs (`CMS_PRIVATE_TTL`) |
| `image.driver` | `gd` | Intervention Image driver used for image processing (`CMS_IMAGE_DRIVER`) |
| `image.quality` | `75` | Quality (1-100) of the WebP/JPEG preview images |
| `image.preview-sizes` | `[480, 720, 960, 1920]` | Preview image widths in pixels for uploaded images |
| `locales` | `APP_LOCALE` (`en`) | Comma-separated ISO language codes (env `CMS_LOCALES`), e.g. `en,de,fr`. First locale is the default for new content. With only one locale, the admin hides all translation features |
| `lock` | `30` | Page-tree write-lock lifetime and maximum acquisition wait in seconds (`CMS_LOCK`) |
| `multidomain` | `false` | Enable domain-based page routing |
| `navdepth` | `2` | Maximum depth of the navigation tree menu |
| `prune` | `30` | Days before soft-deleted items are permanently removed. Set to `false` to disable |
| `upload.filesize` | `50` | Maximum file upload size in MB |
| `upload.mimetypes` | See below | Allowed MIME types or prefixes for all CMS interfaces |
| `versions` | `10` | Maximum number of versions to retain per page, element, or file |

Set the upload policy with `CMS_UPLOAD_FILESIZE` and the comma-separated `CMS_UPLOAD_MIMETYPES`. The default MIME types are `application/gzip`, `application/pdf`, `application/vnd.*`, `application/zip`, `audio/*`, `image/*`, `text/*`, and `video/*`.

Uploads are public by default. File fields can opt into page access protection, which stores the file on the private disk and authorizes delivery against the page using it. The public and private disk names must be different.

Managed originals, previews, and historical version objects are stored below
`cms/{tenant}/{file-uuid}/` (or `cms/{file-uuid}/` for the default tenant).
Named tenant IDs must contain 1-100 ASCII letters, digits, underscores, or hyphens and must not
be UUIDs; invalid IDs are rejected when the tenant context is resolved.
The storage migration copies and verifies legacy objects before changing their database paths
and leaves remote hot-link URLs unchanged. Run upgrades from the CLI during a maintenance window
with CMS file writes stopped; remote object stores may require several requests per managed path.

Relocation requires `file:relocate`, is synchronous, and accepts at most 100 unique File IDs per request. Use smaller batches
for large originals or deep File histories. Remote private disks that provide temporary URLs let
clients download large protected media directly; local private files are streamed by the application.

### Default Roles

| Role | Permissions |
|------|-------------|
| `admin` | All permissions (`*`) |
| `viewer` | View-only access |
| `publisher` | All content permissions, including publish, purge, relocation, and page access changes |
| `editor` | Publisher permissions except publish, purge, and immediate page access changes |

### Stancl tenancy mode

Pagible remains usable without tenancy and with custom integrations that pass a callback returning the current tenant ID to `\Aimeos\Cms\Tenancy::valueUsing()` (the `Tenancy::$callback` property is deprecated). Applications using `stancl/tenancy` in single-database mode can additionally connect Stancl's tenant lifecycle to Pagible from an application service provider:

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
    userAccess: fn( $user, ?array $values ) => $values === null
        ? app(AccessPermissions::class)->assigned( $user )
        : app(AccessPermissions::class)->replace( $user, $values ),
);
```

`Permission::has('access:view')` reports whether a catalog or package adapter has been configured, and `Access::list()` returns its normalized values. The `add`, `delete`, `grants`, and `userAccess` callbacks are optional; without catalog write callbacks the catalog remains read-only, and `userAccess` enables user assignment management. The admin access route requires `access:view`; its Users tab appears when the editor can create users, manage frontend access, or manage CMS permissions. Direct frontend assignment reads and writes require `user:access`. The callback receives the user and either `null` to read direct assignments or the complete desired array to replace them atomically, then returns the refreshed direct assignments. Pagible serializes replacements for persisted Eloquent users; non-Eloquent integrations must provide their own atomic replacement. Effective grants remain the responsibility of `grants` or Laravel Gate. The complete catalog must contain no more than `cms.access.limit` distinct values. Pagible stops reading at the next distinct value and rejects an oversized catalog; additions are rejected before invoking the write callback when the limit has already been reached. Catalog membership and autocomplete search use the memoized bounded list.

A grant resolver must return all effective frontend-access values for the user, including direct and role-derived values in the active tenant and guard. Its result avoids Gate calls for each candidate but is filtered through the configured catalog. Return `null` for users whose permissions cannot be enumerated, such as blanket access implemented only through `Gate::before()`; Pagible then preserves the catalog-and-Gate fallback. Pass `null` as the list callback to reset custom configuration.

For a supported permission package, call its adapter once from an application service provider instead. Spatie must have its teams migration and `permission.teams` enabled for tenant-specific assignments; Laratrust must have its teams migration and `laratrust.teams.enabled` enabled. Bouncer's adapter selects its built-in tenant scope. Laratrust permission checks are exposed as tenant-aware Laravel Gate definitions:

```php
Access::spatie();
Access::bouncer();
Access::laratrust();
```

Each package adapter accepts the same optional effective-grants resolver, for example `Access::spatie(grants: fn( $user ) => ...)`. Pagible does not derive grants from package assignment APIs automatically because those APIs can bypass application-level `Gate::before()` rules and explicit denials. Applications that can enumerate their complete effective result should provide the callback. It avoids Gate calls for each catalog value and must return the complete result—never truncate it. When no authoritative result can be enumerated, return `null`; Pagible retains Gate evaluation. Page rendering, navigation, pricing checkout, and JSON:API use the same database-side access predicate so collection totals and pagination remain exact.

The adapters require these package versions at minimum:

| Adapter | Minimum package version | API used by Pagible |
|---------|-------------------------|---------------------|
| Spatie | `spatie/laravel-permission` 6.2.0 | Permission model, `syncPermissions()`, model cache events, teams, and `PermissionRegistrar::setPermissionsTeamId()` |
| Bouncer | `silber/bouncer` 1.0.2 | Global ability model, direct ability methods, `scope()->to()`, and targeted cache refresh |
| Laratrust | `santigarcor/laratrust` 8.3.0 | Permission model, direct permission methods, teams, `isAbleTo()`, and permission gates |

These are runtime contracts, not compatibility probes: calling an adapter without its package, with an older release, or without the documented team configuration is an application configuration error. Applications must install a package release compatible with their Laravel version; the APIs above remain required.

Pagible does not validate the package's team configuration when an adapter is registered. If Spatie teams or Laratrust teams are disabled, permission checks are evaluated globally even though the current user is still required to belong to the active Pagible tenant. A permission assigned for one tenant can therefore authorize the same catalog value in another tenant. This risk applies only to misconfigured installations; with teams enabled, the required migrations installed, and assignments associated with the correct tenant, permission checks remain tenant-scoped.

Choose exactly one adapter. Each adapter exposes and manages the configured package's permission or ability model as the access catalog, so the provider catalog must be dedicated to access values. Use explicit custom callbacks when a provider model is shared with unrelated authorization permissions. Bouncer exposes only global abilities and leaves model-bound abilities untouched. Spatie reads and deletes only permissions for the configured default guard and uses the package's `findOrCreate()` and model events so its cache hooks remain active. Custom Spatie permission models must retain those package contracts and events.

Spatie and Laratrust permission definitions can remain global even when their assignments are team-scoped. Their `access:add` and `access:delete` capabilities therefore authorize management of the shared definition catalog, not merely the current team's assignments. Grant those capabilities only to editors allowed to make that global change.

The scoped `Access` instance activates Spatie or Bouncer lazily before its first operation in each tenant context. It clears its catalog and per-user grant results whenever the tenant changes. The fallback path preserves package hooks, `Gate::before()` rules, and explicit denials. The Spatie adapter also clears the user's loaded `roles` and `permissions` relations before its first grant resolution or Gate check in each tenant context, preventing relations from the previous tenant from being reused.

Configured catalogs register `access:view` for catalog discovery. Writable catalogs additionally register `access:add` and `access:delete`. Configurations supporting direct assignment lookup, addition, and removal register `user:access`; `access:*` expands to the catalog capabilities currently available. `Access::add()` creates an immutable value and `Access::delete()` removes up to 250 values. `Access::set()` verifies tenant ownership before and after locking a persisted user, replaces up to 250 known values, and returns the refreshed direct assignments. Deleting a value does not rewrite references held by consumers.

### Frontend page access

`page:access` is a standard page permission for reading and replacing immediate page restrictions and listing the catalog names needed by the page detail and bulk access dialogs. It is independent from the `Access` catalog permissions and does not expand through `access:*`. The dedicated access-catalog screen still requires only `access:view`. The boolean restricted state remains available to page viewers without disclosing the configured names.

Frontend restrictions are stored independently in `cms_page_access`, with one row per access value and `(page_id, value)` as its composite primary key. Each row also stores `tenant_id`. No rows for a page mean public access, one row with an empty value permits an authenticated user accepted by `Tenancy::allows()` for the current tenant, and one or more non-empty values permit such a user when Laravel Gate grants any one of them. Page models are deliberately not passed to Gate.

Restriction writes are rejected while the access catalog is unavailable; releasing existing restrictions remains possible. Configure a callback returning an empty list to enable authentication-only restrictions without named access values. Deleting a catalog value does not rewrite existing page restrictions, which continue to fail closed until they are changed explicitly.

Use `PageAccess::set()` as the supported write API. It applies database-first, chunked changes so public page caches and external search documents are updated consistently:

```php
\Aimeos\Cms\Models\PageAccess::set( [$page->id], ['members'], auth()->user() );
\Aimeos\Cms\Models\PageAccess::set( [$page->id], null );
\Aimeos\Cms\Models\PageAccess::set( [$page->id], [], auth()->user(), descendants: true );
```

Access-value lists are trimmed, must contain only registered non-empty strings, deduplicated, sorted, and limited to 250 entries of at most 100 characters. An empty list is stored as one empty value for authentication-only access.

After access records have been committed, indexed Laravel Scout drivers are refreshed by queued jobs from the current page state. Bulk page, element, and file publication, deletion, restoration, and edits use the same queued reconciliation for the Pagible `cms` engine and external Scout engines. The `collection` and Laravel `database` drivers query model tables directly and need no index job. Jobs carry only the tenant, model class, and bounded ID list, then hydrate current state when handled. File-version pruning and purging also queue physical storage cleanup after commit. Run a queue worker with an asynchronous queue connection in production; the `sync` connection executes these jobs inline.

Pages are indexed once per language variant. For page searches with language fallback, external Scout engines (Meilisearch, Typesense, Algolia) filter by the `langs` field, or by `langs_trashed` when trashed items are included. These fields list the languages a variant is shown for: its own language and, for the source variant, the languages from `cms.locales` without a variant. Add both fields to the filterable attributes of the engine's page index and reindex the pages after changing `cms.locales`. Page lists filtered by translation state also need the `lang`, `stale` and `editor` fields as filterable attributes.

Access changes don't modify page-tree coordinates, so they acquire neither the tenant page-tree lock nor page row locks. They load the target pages and current canonical access values once, then update only pages whose values changed in one database transaction. SQL writes remain bounded, while cache invalidation and index synchronization dispatch afterward. Rolled-back changes dispatch nothing. Core emits a lightweight `PageInvalidated` event with affected paths grouped by domain without depending on a rendered-page cache; the theme package invalidates those rendered routes synchronously after commit. Redis, database, and Memcached stores use bounded native batch deletion, while other stores use Laravel's per-key fallback. Redis page keys use bounded tenant-specific cluster hash slots and asynchronous `UNLINK` calls, avoiding one hot tenant slot without issuing cross-slot commands. Keep `scout.queue` enabled with an asynchronous queue connection in production so external search indexing doesn't extend the write request.

Page bulk operations are limited to 1,000 unique pages. Recursive calls also fail before writing if the resolved subtree exceeds 1,000 pages. Larger queued operations must split explicit page IDs into batches of at most 1,000.

Subtree operations require exactly one root ID belonging to the current tenant and fail before writing when it does not.

Operations compare canonical values and skip unchanged pages. Only changed page routes are invalidated, and search documents are refreshed only when access switches between public and restricted.

Do not persist or delete `PageAccess` instances directly. Those low-level writes deliberately have no cache or search side effects; use `PageAccess::set()` instead.

## PHP API

Pages, shared elements and files are created and changed by the facades in `Aimeos\Cms\Facades`. They create the versions, update the search index, invalidate the caches and dispatch the events, so don't save the models directly.

| Class | Operations |
|-------|------------|
| `Pages` | `add`, `save`, `bulk`, `move`, `copy`, `publish`, `drop`, `restore`, `purge` |
| `Elements` | `add`, `save`, `bulk`, `publish`, `drop`, `restore`, `purge` |
| `Files` | `add`, `save`, `bulk`, `relocate`, `publish`, `drop`, `restore`, `purge` |
| `Variants` | `add`, `save`, `revert`, `bulk`, `publish`, `translate`, `setSource`, `ignore`, `drop`, `restore`, `purge` (language variants of pages) |

```php
use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Facades\Variants;

$page = Pages::add( ['name' => 'About', 'path' => 'about', 'lang' => 'en'], $user, parent: $rootId );
$page = Pages::save( $page->id, ['title' => 'About us'], $user );
$page = Pages::publish( $page->id, $user );

Variants::add( $page->id, 'de', $user );
Variants::save( $page->id, 'de', ['title' => 'Über uns'], $user );
Variants::publish( $page->id, 'de', $user );
```

New pages are inactive until they are published, the `status` passed to `Pages::add()` is stored in the draft and applied by `publish()`.

`Pages::save()`, `bulk()` and `publish()` change the variant in the source language of the pages, the methods with the same names in `Variants` change the variant in the passed language. `Variants::revert()` saves the content of an old version like `save()` and marks the translation as outdated.

The facades resolve the instances of the classes in `Aimeos\Cms\Resource` from the container. To change the behavior everywhere (admin, GraphQL, MCP, AI, commands), bind your own subclass overriding the public or protected methods in your service provider:

```php
$this->app->singleton( \Aimeos\Cms\Resource\Pages::class, \App\Cms\MyPages::class );
```

### Input fields

`Pages::add()`, `save()` and `bulk()` as well as `Variants::save()` and `bulk()` accept these keys, all optional:

| Key | Content |
|-----|---------|
| `lang` | ISO language code of the page, e.g. `en` or `en-GB` (`add()` only, defaults to the app locale) |
| `name` | Short name shown in the navigation |
| `title` | Page title for the browser and search engines |
| `path` | URL path of the page, unique per domain |
| `domain` | Domain the page belongs to, empty for all domains |
| `to` | URL the page redirects to |
| `tag` | Tag to find a page, e.g. as starting point of a navigation |
| `theme` | Theme name, empty for the default `cms` theme |
| `type` | Page type of the theme, e.g. `page`, `blog` or `docs` |
| `status` | `0` = inactive, `1` = visible, `2` = hidden in navigation |
| `cache` | Cache lifetime in minutes |
| `content` | List of content elements, e.g. `['type' => 'text', 'group' => 'main', 'data' => ['text' => 'Hello']]`; `id` and `group` are generated if missing and the element types and their `data` are validated against the `schema.json` of the themes |
| `meta`, `config` | Meta and configuration entries keyed by their type, e.g. `['meta-tags' => ['type' => 'meta-tags', 'data' => [...]]]` |

`Elements::add()` takes `type`, `name`, `lang` and `data`, while `Files::add()` stores an uploaded file and `Files::save()` accepts `name`, `lang`, `description` and `transcription` (both keyed by language). Invalid values and unknown element types throw an `InvalidInputException`.

### Single and multiple items

- `x( string $id, ... )` changes one item and returns its model. It throws a `ModelNotFoundException` if the item doesn't exist.
- `xMany( array $ids, ... )` (e.g. `publishMany`, `dropMany`, `relocateMany`) changes several items and returns a `Collection` of the changed ones; IDs which don't exist are skipped. Up to 1,000 items (100 for `relocateMany`) can be changed at once.
- `bulk( array $ids, array $input, ... )` saves the same input to several items and returns a `BulkResult` object with the saved `ids`, their new `latest` version IDs, the applied `data` and the number of `failed` items.

The user is passed directly after the required arguments. Optional arguments like `at`, `ref` or `parent` are best passed as named arguments.

To avoid passing the same user to several calls, use `as()` of the facades:

```php
$pages = Pages::as( $request->user() );

$page = $pages->add( ['name' => 'About', 'path' => 'about'], parent: $rootId );
$page = $pages->publish( $page->id, at: '2030-01-01 00:00:00' );

Variants::as( $request->user() )->add( $page->id, 'de' );
```

It calls the methods of the resource with the user as named `user` argument, so optional arguments must be named too. Calls with `as( null )` are anonymous and throw a `PermissionException` unless they run inside `Permission::system()`. Mocks created by `shouldReceive()` receive the user like in direct calls.

### Permissions

Each method checks the permission of its action for the passed user, e.g. `page:save` for `Pages::save()` or `file:relocate` for `Files::relocate()`, and throws a `PermissionException` if the user lacks it. If no user is passed (`null`), the call is anonymous and throws a `PermissionException` too. For system changes in commands, jobs, seeders or payment webhooks, wrap the calls in `Permission::system()`:

```php
use Aimeos\Cms\Permission;

Permission::system( fn() => Pages::publish( $pageId ) );
```

Inside `system()`, calls without user aren't checked and their changes are recorded with `system` as editor, while users passed explicitly are still checked. For your own write operations, `Permission::check( $action, $user )` throws the same way and `Permission::allowed( $action, $user )` returns the result instead, while `Permission::can( $action, $user )` is always `false` without user.

`system()` only applies to the current process. Queued AI translations started inside `system()` without user run as system in the queue worker too, but your own queued jobs must wrap the CMS calls in `handle()` with `Permission::system()` themselves.

Every call without user inside `system()` is trusted, including those of event listeners and synchronous jobs it triggers, and may also change the page `config`. Therefore:

- Keep the callbacks small and only wrap the CMS calls that need it
- Never pass `$request->user()` or `Auth::user()` inside `system()` because guests would become the system user
- Only pass fixed or validated values, e.g. a page ID from your own database instead of the payload of a payment webhook
- Calls without user aren't bound to a tenant, so initialize the tenant first in commands and jobs, e.g. `Tenancy::run( $tenantId, fn() => Permission::system( ... ) )`

`Permission::set( $user, $entries )` assigns roles and permissions without checking the acting user, so check whether the current user may manage permissions before calling it from your own controllers.

Write operations only use the passed user. Read operations decide about previews of unpublished drafts by the authenticated user (`Auth::user()`), so queued jobs must log in the user (`Auth::setUser()`) to read drafts as editor.

The actions are also Laravel Gate abilities, so the CMS roles, deny entries and tenant checks apply to your application code too:

```php
$request->user()->can( 'page:publish' );
$this->authorize( 'page:save' );
Route::post( '/reports', ReportController::class )->middleware( 'can:page:view' );
```

```blade
@can('page:publish') … @endcan
```

The CMS decides these abilities before your own gates and policies, while all other abilities are left to them.

### Exceptions

All exceptions extend `Aimeos\Cms\Exception`, so catching it handles every CMS error:

| Exception | Thrown if |
|-----------|-----------|
| `Aimeos\Cms\Exceptions\InvalidInputException` | The input is invalid, unknown or exceeds the limits |
| `Aimeos\Cms\Exceptions\ConflictException` | The stored data doesn't allow the operation, e.g. the language variant already exists or is the source variant |
| `Aimeos\Cms\Exceptions\PermissionException` | The user lacks the permission for the operation |

Missing items throw Laravel's `Illuminate\Database\Eloquent\ModelNotFoundException`.

The classes in `Aimeos\Cms\Resource\Support` and methods marked `@internal` aren't part of the public API and may change without notice.

### Events

The resource methods dispatch these events from `Aimeos\Cms\Events` after the database transaction is committed, so rolled back changes are never announced. The content events are only built if broadcasting is enabled (`cms.broadcast`) or a listener is registered for them, which includes `Event::fake()` in tests:

Operations on one item dispatch the event of the action, e.g. `Published`, while operations on several items dispatch a single `Bulk` event instead. To handle every changed item regardless of how it was changed, use `Change::listen()`, which calls the listener once per item for both kinds of events:

```php
use Aimeos\Cms\ContentType;
use Aimeos\Cms\Events\Change;

$stop = Change::listen( 'published', function( Change $change ) {
    if( $change->type() === ContentType::Page && $change->published ) {
        // $change->id, $change->versionId, $change->lang, $change->path, $change->editor, ...
    }
} );

$stop(); // removes the listener again
```

Instead of a closure, the name of a listener class with a `handle( Change $change )` method can be passed, which is resolved from the service container. If the class implements `ShouldQueue`, the changed items of each event are passed to it in one queued `Aimeos\Cms\Jobs\HandleChanges` job. It uses the `cms.queue` connection and queue name unless the listener class defines its own `$connection` or `$queue` property:

```php
use Aimeos\Cms\Events\Change;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifySearchIndex implements ShouldQueue
{
    public string $queue = 'search';

    public function handle( Change $change ) : void
    {
        // called once per changed item in the queue worker
    }
}

Change::listen( ['published', 'dropped'], NotifySearchIndex::class );
```

It accepts one or more action names (`added`, `saved`, `moved`, `published`, `dropped`, `restored`, `purged` and `translated`, `source`, `ignored` for page variants), the cases of the `ContentAction`, `LifecycleAction` and `VariantAction` enums, or `*` for all actions. Unknown action names throw an `InvalidArgumentException`. A `Change` contains the `contentType` (also available as `ContentType` enum case by `type()`), `action`, `id`, `versionId` (the published version for publish actions), `published` (TRUE if the version became public, FALSE if it's only scheduled), `lang`, `path` and `domain` of pages if known, `editor`, `tenant` and `source`. The `changes()` method of the `Event` and `Bulk` classes returns the same objects.

| Event | Dispatched when |
|-------|-----------------|
| `Added` | A page, element or file (or a page copy) was created |
| `Saved` | A new draft version was saved |
| `Moved` | A page was moved in the page tree |
| `Published` | The latest version was published or scheduled |
| `Dropped` | An item was moved to the trash |
| `Restored` | A trashed item was restored |
| `Purged` | An item was permanently deleted |
| `Bulk` | Several items were changed by one call (e.g. `bulk()` or `publishMany()`), instead of one event per item |
| `PageInvalidated` | Rendered pages must be removed from caches, e.g. after publishing, moving or trashing them |
| `FilesRemoved` | Stored files aren't served from the public disk any more, e.g. to remove them from a CDN |
| `PermissionChanged` | The CMS permissions of a user were changed by `Permission::set()` |
| `Observed` | A timing or metric value was recorded for monitoring |

`Added`, `Saved`, `Moved`, `Published`, `Dropped`, `Restored` and `Purged` share the properties of `Aimeos\Cms\Events\Event`:

| Property | Content |
|----------|---------|
| `contentType` | `page`, `element` or `file`, the `type()` method returns the case of the `Aimeos\Cms\ContentType` enum |
| `id` | ID of the item |
| `latestId` | ID of the latest version |
| `data` | Version data (without content, meta and config), the page route for lifecycle events of pages |
| `editor` | Name of the editor |
| `published`, `publishAt`, `updatedAt`, `deletedAt` | State of the item |
| `tenant` | Tenant ID |
| `source` | Interface which caused the change: `graphql`, `mcp` or `cli` |

`Bulk` contains the `contentType` (`type()` returns the enum case), the changed `ids`, the new `latest` version IDs by item ID, the shared `data`, the `editor`, the `action` (`saved` for `bulk()` calls, otherwise `published`, `dropped`, `translated`, etc.) and the page variant languages by page ID in `langs`. Single item events and `Bulk` events are broadcast as `{contentType}.{action}`, e.g. `page.saved`, and `Bulk` payloads contain `ids` instead of `id`. `PageInvalidated` contains the `tenant`, `domain` and the URL `paths`, and `FilesRemoved` contains the `tenant` and the storage `paths`.

### Reading content

Pages are read through the `Aimeos\Cms\Models\PageView` model, which combines the page tree with one language variant per page. Its query methods choose the variant:

| Method | Returns |
|--------|---------|
| `language( ?string $lang )` | The variant of the language, pages without it are skipped; `null` uses the source language (default) |
| `fallback( string $lang )` | The variant of the language or of its fallback languages (e.g. `de-CH` → `de`, see `cms.translate.chains`), else the source variant |
| `visible( string $lang )` | Like `fallback()`, but only published variants whose status isn't `0`, which is what visitors see. New variants are inactive until you save them with `status` `1` or `2` and publish them |
| `localized( string $lang, bool $editor )` | `fallback()` for editors and `visible()` for visitors |
| `allVariants()` | All variants, so each page is returned once per language |

The model attributes contain the **published** content. To show pages to visitors, use `live()`, which selects the variant like `visible()`, skips inactive pages (also for editors, unlike the `Status` scope) and pages the frontend user can't access, and loads the files and elements of the content:

```php
use Aimeos\Cms\Models\PageView;

$page = PageView::live( $request->user() )->whereKey( $pageId )->firstOrFail();

$page->title;   // published title of the variant
$page->lang;    // language of the variant, differs from the requested one for fallbacks
$page->content; // list of content element objects with id, type, group and data
$page->files;   // files referenced by the content
```

Without user, only public pages are returned, and the language defaults to the current locale, e.g. `PageView::live( $user, 'de' )` for German pages.

Every variant has its own URL path, e.g. `about` and `ueber-uns`, so look up pages by their URL with `allVariants()->where( 'path', $path )` like the theme package does. Use the page ID or `tag` to get the same page in different languages.

Drafts are stored in the latest version: `$page->latest?->data` contains the page properties and `$page->latest?->aux` the `content`, `meta` and `config` of the draft. Pages are nested sets, so `children`, `ancestors` and `descendants` as well as the query methods of [aimeos/laravel-nestedset](https://github.com/aimeos/laravel-nestedset) are available too. The `Aimeos\Cms\Models\Element` and `Aimeos\Cms\Models\File` models of shared elements and files also contain the published data and their draft in `latest`. The theme package renders the pages, see [Pagible Theme](../theme/README.md) to change the output.

### Testing

The CMS operations work against any database supported by Laravel, so tests can use an in-memory SQLite database with the `RefreshDatabase` trait (or Orchestra Testbench for packages). The factories of `PageView`, `Element` and `File` create the items through the facades as system user, so they get versions, search index entries and events like items created by editors:

```php
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Illuminate\Http\UploadedFile;

$root = PageView::factory()->published()->create( ['name' => 'Home', 'path' => ''] );
$page = PageView::factory()->for( $root, 'parent' )->published()->create( ['content' => [
    ['type' => 'text', 'data' => ['text' => 'Hello']],
]] );

PageView::factory()->count( 3 )->hidden()->create( ['parent_id' => $root->id] );
Element::factory()->published()->create( ['data' => ['type' => 'text', 'text' => 'Shared']] );
File::factory()->private()->create( ['file' => UploadedFile::fake()->createWithContent( 'doc.pdf', '%PDF-1.4' )] );
```

Without `published()`, only the draft is created and pages stay inactive on the live site. Pages are visible by default after publishing, `hidden()` hides them in the navigation and `inactive()` disables them. Files are fake 100x100 PNG images unless an uploaded file or URL is passed in `file`.

Add the `HasCmsPermissions` traits to the user model and its factory to create editors in tests:

```php
class User extends Authenticatable
{
    use \Aimeos\Cms\Concerns\HasCmsPermissions;
}

class UserFactory extends Factory
{
    use \Aimeos\Cms\Database\Factories\HasCmsPermissions;
}

// test
$editor = User::factory()->cms( 'editor', '!page:drop' )->create();

$this->actingAs( $editor )->post( '/reports' )->assertOk();
$this->assertTrue( $editor->can( 'page:save' ) );
$this->assertEquals( ['editor', '!page:drop'], $editor->cmsPermissions() );
```

`cms()` stores the roles, permissions and deny entries directly, while `$user->setCmsPermissions( $entries )` validates them like `Permission::set()`. Wrap the facade calls in `Permission::system()` to skip the permission checks or pass an editor to test them:

```php
use Aimeos\Cms\Events\Published;
use Aimeos\Cms\Exceptions\PermissionException;
use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Permission;
use Illuminate\Support\Facades\Event;

public function testPublish() : void
{
    $page = PageView::factory()->create();

    Event::fake( [Published::class] );

    Permission::system( fn() => Pages::publish( $page->id ) );

    Event::assertDispatched( Published::class, fn( Published $event ) => $event->id === $page->id );
}

public function testPermission() : void
{
    $page = PageView::factory()->create();

    $this->expectException( PermissionException::class );
    Pages::publish( $page->id, User::factory()->cms( 'editor' )->create() );
}
```

For a complete page tree, `$this->artisan( 'cms:demo' )` of the theme package creates published demo pages.

Events are only dispatched if `cms.broadcast` is enabled, listeners for them are registered or `Event::fake()` is used. Alternatively, register a listener by `Change::listen()` and collect the passed `Change` objects. `Bulk` events are dispatched instead of the per-item events if several items are changed at once, e.g. by `publishMany()`.

The facades in `Aimeos\Cms\Facades` (`Pages`, `Elements`, `Files` and `Variants`) support `shouldReceive()` and `spy()` like all Laravel facades:

```php
use Aimeos\Cms\Facades\Pages;

// application code
Pages::publish( $id, $request->user() );

// test
Pages::shouldReceive( 'publish' )->once()->with( 'page-id', $user )->andReturn( $page );
```

The built-in admin, GraphQL, MCP and AI APIs use the facades too, so mocks also replace the operations they call.

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

### cms:previews

Creates missing preview images for the sizes configured in `cms.image.preview-sizes` and removes previews of sizes no longer configured. Only images supported by the image driver are updated, previews of other files (e.g. PDFs or videos) are kept. The previews are generated from the latest version of each file and stored in a new version (editor `cms:previews`), which is published if the latest version was. Replaced previews aren't deleted directly but when the older versions referencing them are pruned.

```bash
php artisan cms:previews [--tenant=ID] [--id=ID]... [--force]
```

| Option | Description |
|--------|-------------|
| `--tenant=ID` | Only update files of this tenant (`--tenant=` for the default, empty tenant); all tenants if omitted |
| `--id=ID` | Only update the files with these IDs (can be used multiple times) |
| `--force` | Replace all previews, e.g. after changing the image driver, format or quality |

If the command is stopped, run it again to update the remaining files. Only one instance runs at a time (per tenant for tenants managed by stancl/tenancy); the lock expires after one day if the command has been killed and is stored in the cache, so use a cache store shared by all servers if it can be started on more than one. Files changed or locked while the command is running are skipped and updated by the next run. If the storage is locked, e.g. by a running backup, the tenant is stopped. Skipped files, files which can't be updated and stopped tenants are logged as `cms.previews` warning, only the latter two make the command fail. If tenants are managed by stancl/tenancy, use `php artisan tenants:run cms:previews` to process all tenants.

## License

MIT
