/**
 * @license MIT, https://opensource.org/license/mit
 */

import { createPinia } from 'pinia'
import { createApp, defineAsyncComponent } from 'vue'
import { fieldComponents } from './fieldtypes'
import visible from './directives/visible'
import apollo, { apolloClient } from './graphql'
import i18n from './i18n'
import logger from './log'
import router, { addPluginRoutes } from './routes'
import './session'
import { useConfirmStore, useMessageStore } from './stores'
import { pageUrl } from './urls'
import vuetify from './vuetify'
import App from './App.vue'

import 'vuetify/styles'
import './assets/base.css'

const app = createApp(App)
const pinia = createPinia()
let purify = null
let purifyLoading = null

app.directive('safe-svg', (el, binding) => {
  if (binding.value === binding.oldValue) return
  if (purify) {
    el.innerHTML = purify.sanitize(binding.value, {
      USE_PROFILES: { svg: true, svgFilters: true }
    })
    return
  }
  if (!purifyLoading) {
    purifyLoading = import('dompurify').then(mod => {
      purify = mod.default
    }).catch(error => {
      // the next update tries to load it again, e.g. after a network error
      purifyLoading = null
      throw error
    })
  }
  purifyLoading.then(() => {
    el.innerHTML = purify.sanitize(binding.value, {
      USE_PROFILES: { svg: true, svgFilters: true }
    })
  }).catch(error => {
    console.error('Loading DOMPurify failed', error)
  })
})
app.directive('visible', visible)


for (const name in fieldComponents) {
  app.component(name, defineAsyncComponent(fieldComponents[name]))
}

app
  .use(logger)
  .use(pinia)
  .use(i18n)
  .use(router)
  .use(vuetify)
  .use(apollo)

// Expose host services to plugin panels and sub-panels.
app.provide('apollo', apolloClient)
app.provide('confirm', useConfirmStore(pinia))
app.provide('messages', useMessageStore(pinia))
app.provide('pageUrl', pageUrl)

// Pinia is active now, so usePluginStore() is safe; register a route per plugin panel.
addPluginRoutes()

// In dev the SPA is served by Vite, so the page never passes through Laravel's `web` middleware
// that starts a session and sets the XSRF-TOKEN cookie (production gets it from the /cmsadmin page
// load). Prime it with one GET through the dev proxy before mounting, so the first CSRF-protected
// POST (GraphQL or chat) carries a valid token. import.meta.env.DEV is statically false in builds,
// so this branch is stripped from production bundles.
if (import.meta.env.DEV) {
  fetch('/cmsadmin', { credentials: 'include' }).finally(() => app.mount('#app'))
} else {
  app.mount('#app')
}
