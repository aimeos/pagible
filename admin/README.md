# PagibleAI CMS Admin Backend

Admin panel for [Pagible CMS](https://pagible.com) built with Vue 3, Vuetify, and CKEditor 5.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible). For full installation, use:

```bash
composer require aimeos/pagible
```

## Tech Stack

- **Vue 3** with Composition API
- **Vite** - Build tool and dev server
- **Vuetify** - Material Design component library
- **Pinia** - State management
- **Apollo Client** - GraphQL (batch + file upload support)
- **CKEditor 5** - Rich text editing
- **Chart.js** - Metrics visualization
- **vue3-gettext** - Internationalization (33 languages)
- **Cypress** - Component and E2E testing

## Configuration

After installation, the configuration is available in `config/cms/admin.php`:

### Theme Colors

The `colors` section defines Vuetify theme colors for `light` and `dark` modes. Each theme contains 14 color tokens (background, surface, primary, secondary, error, info, success, warning, text-primary, text-secondary, map-accent, border, etc.) using 6-char hex format (`#RRGGBB`).

### Media Proxy

| Option | Env Variable | Default | Description |
|--------|-------------|---------|-------------|
| `proxy.maxsize` | `CMS_PROXY_MAXSIZE` | `10` | Maximum downloadable file size in MB via proxy |
| `proxy.timeout` | `CMS_PROXY_TIMEOUT` | `30` | Stream timeout in seconds |
| `proxy.middleware` | | `['throttle:cms-proxy']` | Middleware applied to the proxy route |

## Commands

### cms:install:admin

Installs the Pagible CMS admin package.

```bash
php artisan cms:install:admin
```

Publishes admin assets and configuration to `public/vendor/cms/admin` and `config/cms/admin.php`.

## Project Setup

```sh
npm install
```

### Compile and Hot-Reload for Development

```sh
npm run dev
```

### Compile and Minify for Production

```sh
npm run build
```

### Run Component Tests with [Cypress Component Testing](https://on.cypress.io/component)

```sh
npm run test:unit:dev # or `npm run test:unit` for headless testing
```

### Run End-to-End Tests with [Cypress](https://www.cypress.io/)

```sh
npm run test:e2e:dev
```

This runs the end-to-end tests against the Vite development server.
It is much faster than the production build.

But it's still recommended to test the production build with `test:e2e` before deploying (e.g. in CI environments):

```sh
npm run build
npm run test:e2e
```

### Lint with [ESLint](https://eslint.org/)

```sh
npm run lint
```

### Other Commands

```sh
npm run format           # Prettier formatting
npm run gettext:extract  # Extract translatable strings
npm run gettext:compile  # Compile translations
```

## Directory Structure

```
admin/
├── src/
│   ├── main.js              # App entry point and plugin setup
│   ├── App.vue              # Root component (provides helpers via inject)
│   ├── routes.js            # Vue Router config (login, pages, elements, files)
│   ├── stores.js            # Pinia stores (auth, app, config, clipboard, etc.)
│   ├── graphql.js           # Apollo Client setup (batch + upload links)
│   ├── i18n.js              # Internationalization config
│   ├── vuetify.js           # Vuetify theme and locale config
│   ├── audio.js             # Audio recording and transcription
│   ├── utils.js             # UID generation
│   ├── components/          # Reusable components (30+)
│   ├── views/               # Page views (Login, PageList/Detail, etc.)
│   ├── fields/              # Dynamic field type components (23+)
│   └── assets/              # Stylesheets
├── tests/components/        # Cypress component tests
├── cypress/e2e/             # Cypress E2E tests
├── i18n/                    # Translation JSON files (33 languages)
└── index.html               # HTML template with data-attribute config
```

## Architecture Notes

### Admin Extensions

Composer packages register top-level panels and page, element or file editor sub-panels with `Aimeos\Cms\Plugin::register()`. The component URL must point to a Vite-built ES module whose default export is a Vue component. Components are loaded lazily; the admin host supplies the application shell for top-level panels and injects the shared `apollo` client and `messages` store.

External panels should use the host-owned Vuetify and CMS components exported as `pluginComponents` from `js/plugin.js`. This keeps one Vuetify runtime and gives extensions the same common form, layout, list, feedback and navigation primitives as the core admin. Components declared by the extension itself are preserved when the host wraps it.

CMS components are loaded only when rendered. `CmsActionMenu` provides the responsive action menu/dialog shell, while `CmsDialog` provides the standard dialog header, content and action layout. `CmsFilePicker` provides the standard media dialog; it accepts `v-model`, optional `filter` and `grid` properties, and emits `add` with the selected file. `CmsLoadingSpinner` provides the shared loading indicator.

Extensions keep their translations in their own package by registering a locale URL with `Plugin::i18n()`, then assigning its key as the panel's `i18n` value. Catalog URLs contain one `{locale}` placeholder and point to split JSON files published with the extension. Use the same key as the gettext context for all extension strings, for example `$pgettext('commerce', 'Products')`. The host loads the active package catalog into its shared gettext instance without allowing it to overwrite core or other package contexts.

Application-shell components, Vuetify Labs components and heavy specialized widgets are intentionally outside this public surface. Add reusable components to `pluginComponents` together with their contract tests; otherwise, an extension must register and maintain the component itself. All extension-facing labels and messages must be translated.

### Dynamic Field System

Field components in `src/fields/` (String, Select, Html, Table, Images, etc.) are auto-registered at startup via `import.meta.glob()` in `main.js`. They follow a common interface:

- **Props**: `modelValue`, `config`, `assets`, `readonly`, `context`
- **Emits**: `update:modelValue`, `error`

Fields are rendered dynamically based on schema configuration, allowing pages and elements to define their own field layouts.

### GraphQL API

All data operations use Apollo Client through GraphQL. Two transport links handle different needs:

- **Batch Link** - Groups multiple queries (up to 50 ops, 20ms interval)
- **Upload Link** - Handles file uploads via `apollo-upload-client`

On 401/unauthenticated errors, the client automatically redirects to the login view.

### View Stack Navigation

Instead of route-based dialogs, the app uses a stack-based overlay system. `App.vue` provides `openView()` and `closeView()` helpers via Vue's provide/inject, enabling modal-like slide-in transitions for detail views.

### Permission System

UI visibility is driven by a JSON permission object stored in `auth.me.permission`. Check access with `auth.can('page:view')` or `auth.can(['page:save', 'page:delete'])`. Common permissions:

- `page:view`, `page:save` - Page management
- `element:view`, `element:save` - Shared elements
- `file:view`, `file:add` - File management
- `audio:transcribe`, `text:write`, `text:translate` - AI features

Route guards enforce permissions and redirect unauthenticated users to login.

### State Management

Nine focused Pinia stores in `src/stores.js`:

- **useAuthStore** - Authentication, user info, permission checks
- **useAppStore** - URL configuration from HTML data attributes
- **useConfigStore** - App config (dot-notation access)
- **useSchemaStore** - Element/content schemas
- **useLanguageStore** - 180+ language translations
- **useMessageStore** - Snackbar notification queue
- **useDrawerStore** / **useSideStore** - UI panel state
- **useClipboardStore** - Copy/paste storage

### Internationalization

All user-facing strings must use `$gettext('message')` or `$pgettext('context', 'message')`. Translations are in `i18n/*.json` for 33 languages. Run `npm run gettext:extract` after adding new strings and `npm run gettext:compile` after updating translations.

### App Configuration

The app reads configuration from `index.html` data attributes on the `#app` element:

- `data-urladmin` - Admin base URL
- `data-urlgraphql` - GraphQL endpoint
- `data-urlproxy` - Media proxy URL (for CORS)
- `data-urlpage` / `data-urlfile` - Public page/file URLs
- `data-config` - JSON config (locales, themes)
- `data-schemas` - JSON field schemas (content, meta, config)

### AI Features

The app integrates AI capabilities (permission-gated):

- **Text generation** - `write(prompt, context, files)`
- **Translation** - `translate(texts, to, from, context)`
- **Audio transcription** - `transcribe(file)` via MediaRecorder API + AudioWorklet

## License

MIT
