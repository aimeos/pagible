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

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vuetify({ styles: { configFile: 'src/styles/settings.scss' } })
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
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
  build: {
    manifest: true,
  },
  experimental: {
    renderBuiltUrl: () => {
      return { relative: true }
    },
  },
})
