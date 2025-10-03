/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

import { createPinia } from 'pinia'
import { createApp, defineAsyncComponent } from 'vue'
import VueObserveVisibility from 'vue3-observe-visibility'
import apollo from './graphql'

import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import './assets/base.css'

import i18n from './i18n'
import logger from './log'
import router from './routes'
import vuetify from './vuetify'
import App from './App.vue'


const app = createApp(App)
const pinia = createPinia()


const fields = import.meta.glob("@/fields/*.vue")
for(const path in fields) {
  const name = path.split("/").at(-1).split(".")[0]
  app.component(name, defineAsyncComponent(fields[path]))
}


app.use(logger)
  .use(pinia)
  .use(router)
  .use(i18n)
  .use(vuetify)
  .use(apollo)
  .use(VueObserveVisibility)
  .mount('#app')
