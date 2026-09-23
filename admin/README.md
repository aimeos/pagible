# PagibleAI CMS Admin Backend

Admin panel for [Pagible CMS](https://pagible.com), built with Vue 3, Vuetify and CKEditor 5.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible). To install the full CMS, use:

```bash
composer require aimeos/pagible
```

## Contents

- [Tech Stack](#tech-stack)
- [Installation](#installation)
- [Configuration](#configuration)
- [Development](#development)
- [Directory Structure](#directory-structure)
- [Architecture](#architecture)
- [Admin Extensions](#admin-extensions)
- [License](#license)

## Tech Stack

| Library | Purpose |
|---------|---------|
| **Vue 3** | UI framework (Composition API) |
| **Vite** | Build tool and dev server |
| **Vuetify** | Material Design component library |
| **Pinia** | State management |
| **Apollo Client** | GraphQL client with batching and file uploads |
| **CKEditor 5** | Rich text editing |
| **Chart.js** | Metrics visualization |
| **vue3-gettext** | Internationalization (33 languages) |
| **Cypress** | Component and E2E testing |

## Installation

```bash
php artisan cms:install:admin
```

This publishes the admin assets to `public/vendor/cms/admin` and the configuration to `config/cms/admin.php`.

## Configuration

All options are in `config/cms/admin.php`.

### Theme Colors

The `colors` section defines the Vuetify theme colors for the `light` and `dark` modes. Each theme contains 14 color tokens, e.g. `background`, `surface`, `primary`, `secondary`, `error`, `info`, `success`, `warning`, `text-primary`, `text-secondary`, `map-accent` and `border`. Values use the 6-digit hex format (`#RRGGBB`).

### Media Proxy

| Option | Env variable | Default | Description |
|--------|--------------|---------|-------------|
| `proxy.maxsize` | `CMS_PROXY_MAXSIZE` | `10` | Maximum file size in MB downloadable via the proxy |
| `proxy.timeout` | `CMS_PROXY_TIMEOUT` | `30` | Stream timeout in seconds |
| `proxy.middleware` | | `['throttle:cms-proxy']` | Middleware applied to the proxy route |

## Development

Install the dependencies first:

```sh
npm install
```

| Command | Description |
|---------|-------------|
| `npm run dev` | Start the Vite dev server with hot reload |
| `npm run build` | Compile and minify for production |
| `npm run test:unit` | Run [Cypress component tests](https://on.cypress.io/component) headless |
| `npm run test:unit:dev` | Open the Cypress component test runner |
| `npm run test:e2e` | Run [Cypress](https://www.cypress.io/) E2E tests against the production build |
| `npm run test:e2e:dev` | Open the Cypress E2E runner against the Vite dev server |
| `npm run lint` | Lint and auto-fix with [ESLint](https://eslint.org/) |
| `npm run format` | Format the code with Prettier |
| `npm run gettext:extract` | Extract translatable strings |
| `npm run gettext:compile` | Compile translations |

`test:e2e:dev` is much faster during development, but always test the production build before deploying (e.g. in CI):

```sh
npm run build
npm run test:e2e
```

> [!NOTE]
> The running application serves a published copy of `dist/`. After `npm run build`, run `php artisan vendor:publish --tag=cms-admin --force` to see the changes.

## Directory Structure

```
admin/
├── js/
│   ├── main.js          # App entry point and plugin setup
│   ├── App.vue          # Root component
│   ├── routes.js        # Vue Router config (login, pages, elements, files)
│   ├── stores.js        # Pinia stores
│   ├── graphql.js       # Apollo Client setup (batch + upload links)
│   ├── config.js        # Configuration read from the #app data attributes
│   ├── plugin.js        # Public component surface for admin extensions
│   ├── ai.js            # AI text generation, translation and transcription
│   ├── i18n.js          # Internationalization config
│   ├── vuetify.js       # Vuetify theme and locale config
│   ├── components/      # Reusable components
│   ├── views/           # Page views (Login, PageList, PageDetail, etc.)
│   ├── fields/          # Dynamic field type components
│   └── assets/          # Stylesheets
├── cypress/
│   ├── unit/            # Cypress component tests
│   └── e2e/             # Cypress E2E tests
├── i18n/                # Translation files (33 languages)
├── src/                 # Laravel service provider, controllers, commands
├── tests/               # PHPUnit tests
└── index.html           # Development HTML template with data-attribute config
```

## Architecture

### App Configuration

The app reads its configuration from data attributes of the `#app` element (see `js/config.js`):

| Attribute | Description |
|-----------|-------------|
| `data-urladmin` | Admin base URL |
| `data-urlgraphql` | GraphQL endpoint |
| `data-urlproxy` | Media proxy URL (for CORS) |
| `data-urlpage` / `data-urlfile` | Public page and file URLs |
| `data-locales` | Available content locales (JSON) |
| `data-theme` | Vuetify theme (JSON) |
| `data-plugins` | Registered extension panels (JSON) |

### GraphQL API

All data operations use Apollo Client over GraphQL with two transport links:

- **Batch link**: groups up to 50 operations sent within 20ms
- **Upload link**: handles file uploads via `apollo-upload-client`

On 401/unauthenticated errors, the client redirects to the login view.

### Permissions

UI visibility is driven by the permission object in `user.me.permission`. Check access with `user.can('page:view')` or `user.can(['page:save', 'page:delete'])` (returns `true` if any permission matches). Common permissions:

| Permissions | Area |
|-------------|------|
| `page:view`, `page:save` | Pages |
| `element:view`, `element:save` | Shared elements |
| `file:view`, `file:add` | Files |
| `audio:transcribe`, `text:write`, `text:translate` | AI features |

Route guards enforce permissions and redirect unauthenticated users to the login view.

### State Management

The Pinia stores are defined in `js/stores.js`:

| Store | Purpose |
|-------|---------|
| `useUserStore` | Authentication, user info, permission checks, per-user settings |
| `useAppStore` | URL configuration |
| `useSchemaStore` | Element and content schemas |
| `useLanguageStore` | Available languages |
| `useMessageStore` | Snackbar notification queue |
| `useDrawerStore` / `useSideStore` | UI panel state |
| `useClipboardStore` | Copy/paste storage |
| `useDirtyStore` | Unsaved changes tracking |
| `useChangeStore` | Saved items pending update in the lists |
| `useViewStack` | Stack of opened detail views |
| `usePluginStore` | Registered extension panels |

### View Stack Navigation

Instead of route-based dialogs, detail views are opened on a stack using `openView()` and `closeView()` of `useViewStack`. This enables slide-in transitions and nested editing (e.g. page → file → element).

### Dynamic Fields

Field components in `js/fields/` (String, Select, Html, Table, Images, etc.) are registered automatically via `import.meta.glob()`. They are rendered based on the schema configuration, so pages and elements can define their own field layouts. All fields share a common interface:

- **Props**: `modelValue`, `config`, `assets`, `readonly`, `context`
- **Emits**: `update:modelValue`, `error`

### AI Features

Permission-gated AI functions in `js/ai.js`:

- **Text generation**: `write(prompt, context, files)`
- **Translation**: `translate(texts, to, from, context)`
- **Audio transcription**: `transcribe(file)`, recorded via the MediaRecorder API and AudioWorklet

### Internationalization

All user-facing strings must use `$gettext('message')` or `$pgettext('context', 'message')`. Translations for 33 languages are in `i18n/`. Run `npm run gettext:extract` after adding new strings and `npm run gettext:compile` after updating translations.

## Admin Extensions

Composer packages can add their own panels to the admin backend.

### Registering Panels

Register top-level panels, or sub-panels for the page, element or file editors, with `Aimeos\Cms\Plugin::register()`:

- The component URL must point to a Vite-built ES module whose default export is a Vue component.
- Components are loaded lazily.
- For top-level panels, the admin supplies the application shell.

The admin injects these shared objects into all extension components:

| Injection | Description |
|-----------|-------------|
| `apollo` | Shared Apollo GraphQL client |
| `messages` | Snackbar message store |
| `pluginAside` | Adds the filter sidebar to top-level panels, `null` in editor sub-panels |

`pluginAside(content, defaults)` shows the filter sidebar of the core list views together with its toggle button in the app bar. `content` is a function returning the filter groups, so translated labels stay up to date, and `defaults` contains the initial filter values used on reset. It returns the reactive filter, which the admin stores per user:

```js
export default {
  inject: { pluginAside: { default: null } },

  data() {
    const defaults = { status: null }
    return { filter: this.pluginAside?.(() => this.asideContent, defaults) ?? defaults }
  },

  computed: {
    asideContent() {
      return [{
        key: 'status',
        title: this.$pgettext('commerce', 'Status'),
        items: [
          { title: this.$pgettext('commerce', 'All'), icon: mdiPlaylistCheck, value: { status: null } },
          { title: this.$pgettext('commerce', 'Active'), icon: mdiCheckCircleOutline, value: { status: true } }
        ]
      }]
    }
  }
}
```

### Shared Components

Extensions should use the Vuetify and CMS components provided by the host, exported as `pluginComponents` from `js/plugin.js`. This keeps a single Vuetify runtime and gives extensions the same form, layout, list, feedback and navigation primitives as the core admin. Components declared by the extension itself are preserved.

CMS components are loaded only when rendered:

| Component | Description |
|-----------|-------------|
| `CmsActionMenu` | Responsive action menu/dialog shell |
| `CmsDialog` | Standard dialog with header, content and actions |
| `CmsFilePicker` | Media dialog; accepts `v-model` and optional `filter` and `grid` props, emits `add` with the selected file |
| `CmsLoadingSpinner` | Shared loading indicator |

Application-shell components, Vuetify Labs components and heavy specialized widgets are intentionally not part of this public surface. Reusable components can be added to `pluginComponents` together with contract tests; otherwise, extensions must register and maintain them themselves.

### Translations

Extensions keep their translations in their own package:

1. Register a catalog URL with `Plugin::i18n()`. It must contain one `{locale}` placeholder and point to the split JSON files published with the extension.
2. Use its key as the panel's `i18n` value.
3. Use the same key as gettext context for all extension strings, e.g. `$pgettext('commerce', 'Products')`.

The admin loads the catalog of the active locale into its shared gettext instance without overwriting the core or other package contexts. All labels and messages of extensions must be translated.

## License

MIT
