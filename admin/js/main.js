/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

import { createPinia } from 'pinia'
import { createApp, defineAsyncComponent } from 'vue'
import visible from './directives/visible'
import apollo from './graphql'
import i18n from './i18n'
import logger from './log'
import router from './routes'
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


const fields = import.meta.glob('@/fields/*.vue')

for (const path in fields) {
  const name = path.split('/').at(-1).split('.')[0]
  app.component(name, defineAsyncComponent(fields[path]))
}

app
  .use(logger)
  .use(pinia)
  .use(i18n)
  .use(router)
  .use(vuetify)
  .use(apollo)
  .mount('#app')
