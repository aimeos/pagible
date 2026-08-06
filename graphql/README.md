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

The `Page.restricted` field tells page viewers whether immediate frontend access rules exist without exposing their values. `Page.access` requires `page:access` and represents the rules independently from page versions:

- `null` means public access
- an empty list permits authenticated users of the current tenant
- a non-empty list permits users granted any listed access value

Users with `page:access` can replace this state with `setPageAccess(id:, access:, descendants:)`. The nullable `access` argument must be provided explicitly. Multiple selected page IDs are supported; recursive changes are limited to one root page. Page bulk operations are limited to 1,000 unique pages, and recursive calls fail before writing if the resolved subtree exceeds 1,000 pages. `Query.access` lists catalog names for both the general catalog screen and page access controls, so it accepts either `access:view` or `page:access`. Without arguments it returns the complete bounded catalog; callers can provide `term` and `first` for autocomplete searches. The permissions remain registered in separate namespaces.

The admin access route and navigation require `access:view`. Within its Users tab, user creation, frontend access, and CMS editor permissions remain independent capabilities:

- `createUser(email:)` requires `user:create`. It creates a user through the active Eloquent authentication provider with a hashed random password, the email as the internal name fallback, and no frontend access or CMS permissions. Existing emails are rejected rather than modified, and the created `CmsUserData` can be used without another lookup.
- `cmsUser(email:)` performs one exact normalized email lookup and returns the narrow `CmsUserData` projection, including an opaque ID for subsequent writes. The query accepts either `user:access` or `user:permission`; its `access` and `permissions` fields enforce those capabilities independently.
- `setUserAccess(id:, access:)` requires `user:access` and atomically replaces direct frontend values supported by the configured access adapter.
- `permissions` returns configured CMS role names and registered permission names, while `setUserPermissions(id:, permissions:)` atomically replaces the raw `cmsperms` entries. Both require `user:permission`; this is a super-admin capability that permits assigning any configured role or permission, including to the current user. Supported role, permission, wildcard, and deny values are validated before writing.

All user-specific operations resolve users through the configured authentication provider and apply `Tenancy::allows()` before disclosure or change. Missing and foreign-tenant users are indistinguishable. `CmsUserData` exposes only an opaque ID, the email, and independently protected authorization assignments; clients must treat the ID as opaque and use it instead of the mutable email for assignment writes. `CmsUser` remains the authenticated-user projection returned by `me`, and the application's standard `User` GraphQL type is not extended. The generated password is never returned, so applications need a password-reset or invitation flow before password login. Successful creation and assignment replacement emit the structured `UserChanged` audit event; the built-in watch listener records it as `cms.user` with actor, target, tenant, and resulting assignments.

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
