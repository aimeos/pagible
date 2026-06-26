import { fileURLToPath, URL } from 'node:url'
import { readdirSync } from 'node:fs'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vuetify from 'vite-plugin-vuetify'

// Pre-include Vuetify deep component imports so that vite-plugin-vuetify's
// auto-imports do not trigger dependency re-optimization and a full-page
// reload on a cold cache.  This is critical for Cypress component tests
// which run on CI without a warm Vite cache.
const broken = new Set(['VCalendar', 'VOverflowBtn'])

const vuetifyComponents = readdirSync('node_modules/vuetify/lib/components', { withFileTypes: true })
  .filter(d => d.isDirectory() && !broken.has(d.name))
  .map(d => `vuetify/components/${d.name}`)

const vuetifyLabs = readdirSync('node_modules/vuetify/lib/labs', { withFileTypes: true })
  .filter(d => d.isDirectory())
  .map(d => `vuetify/labs/${d.name}`)

// Group large vendor packages into dedicated chunks.  Rolldown (Vite 8) only
// supports the function form of manualChunks, so each node_modules package is
// matched by path and mapped to its chunk name.
//
// ckeditor5 is deliberately NOT grouped here: assigning its `export *` barrel
// to a manual chunk forces the bundler to preserve the chunk's full export
// surface, which pulls in every CKEditor feature and defeats tree-shaking
// (5.9 MB instead of ~0.9 MB).  Leaving it out lets Rolldown auto-split and
// tree-shake it down to only the editor features actually imported.
const chunkGroups = {
  charts: ['chart.js', 'vue-chartjs'],
  cropper: ['cropperjs'],
  diff: ['diff'],
  dompurify: ['dompurify'],
  graphql: ['graphql', 'graphql-tag', '@apollo/client', 'apollo-link-batch-http', 'apollo-upload-client'],
  markdown: ['mdast-util-from-markdown', 'mdast-util-to-markdown'],
  tree: ['@he-tree/vue'],
  // is-plain-obj is shared by the graphql chunk; pinning it to its own chunk
  // avoids a circular chunk dependency.
  shared: ['is-plain-obj'],
}

function manualChunks(id) {
  if(!id.includes('node_modules')) return
  for(const chunk in chunkGroups) {
    if(chunkGroups[chunk].some(pkg => id.includes(`node_modules/${pkg}/`))) return chunk
  }
}

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vuetify({ styles: { configFile: 'js/styles/settings.scss' } }),
    {
      name: 'exclude-ckeditor-umd',
      generateBundle(_, bundle) {
        for(const key in bundle) {
          if(key.includes('.umd-')) delete bundle[key]
        }
      }
    },
    {
      // Emit the shared-runtime shims (vue, vue-router, vuetify, graphql-tag) as entry
      // chunks with a strict signature so their `export *` re-exports survive tree-shaking
      // and stay addressable in the manifest for the plugin import map. preserveSignature is
      // set per-chunk here instead of globally: a global setting would force the full
      // Vue/Vuetify/router export surface into the eager app bundle (~17 KB gzip) because the
      // shims share chunks with the main app. Scoping it keeps the host build unchanged.
      name: 'cms-plugin-shims',
      buildStart() {
        for(const name of ['vue', 'vue-router', 'vuetify', 'graphql-tag']) {
          this.emitFile({
            type: 'chunk',
            id: fileURLToPath(new URL(`./js/shims/${name}.js`, import.meta.url)),
            name: `shim-${name}`,
            preserveSignature: 'strict'
          })
        }
      }
    },
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./js', import.meta.url)),
      'ckeditor5/translations': fileURLToPath(new URL('./node_modules/ckeditor5/dist/translations', import.meta.url)),
    },
    dedupe: ['pinia', 'vue']
  },
  optimizeDeps: {
    include: [
      'pinia',
      'vue',
      'vue3-gettext',
      'dompurify',
      ...vuetifyComponents,
      ...vuetifyLabs,
    ]
  },
  // Dev-only: proxy the credentialed backend calls to the Laravel app so the SPA is same-origin
  // with them. Then the XSRF-TOKEN cookie is readable by the SPA and CSRF-protected POSTs (chat,
  // GraphQL mutations, file uploads) carry a valid token instead of being rejected cross-origin.
  // Use relative URLs in index.html (e.g. data-urlchat="/cmsapi/chat") so they hit this proxy.
  server: {
    proxy: {
      '/graphql': { target: 'http://localhost:8000', changeOrigin: true },
      '/cmsapi': { target: 'http://localhost:8000', changeOrigin: true },
      '/cmsproxy': { target: 'http://localhost:8000', changeOrigin: true },
      '/storage': { target: 'http://localhost:8000', changeOrigin: true },
      // GET through Laravel's `web` group: starts the session and sets the XSRF-TOKEN cookie that
      // main.js primes on boot in dev (production gets it from the real /cmsadmin page load).
      '/cmsadmin': { target: 'http://localhost:8000', changeOrigin: true },
    }
  },
  build: {
    manifest: true,
    rollupOptions: {
      output: {
        manualChunks
      }
    }
  },
  experimental: {
    renderBuiltUrl: () => {
      return { relative: true }
    },
  },
})
