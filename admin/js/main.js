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
import vuetify from './vuetify'
import App from './App.vue'

import 'vuetify/styles'
import './assets/base.css'

const app = createApp(App)
const pinia = createPinia()
let purify = null
let purifyLoading = null

app.directive('safe-svg', (el, binding) => {
  if (purify) {
    el.innerHTML = purify.sanitize(binding.value, {
      USE_PROFILES: { svg: true, svgFilters: true }
    })
    return
  }
  if (!purifyLoading) {
    purifyLoading = import('dompurify').then(mod => {
      purify = mod.default
    })
  }
  purifyLoading.then(() => {
    el.innerHTML = purify.sanitize(binding.value, {
      USE_PROFILES: { svg: true, svgFilters: true }
    })
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

// Expose the Apollo client to plugin sub-panels via inject('apollo'); the default
// export above is the Vue Apollo plugin, not the client itself.
app.provide('apollo', apolloClient)

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
