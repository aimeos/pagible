/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

import gettext from './i18n'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { createVuetify } from 'vuetify'
import { aliases, mdi } from 'vuetify/iconsets/mdi-svg'


const localeMap = { zh: 'zhHans' }
const locales = import.meta.glob('../node_modules/vuetify/lib/locale/*.js')

export async function switchLocale(code) {
  const name = localeMap[code] || code
  const loader = locales[`../node_modules/vuetify/lib/locale/${name}.js`]

  if (loader) {
    const mod = await loader()
    vuetify.locale.messages.value[code] = mod.default
  }
}

const vuetify = createVuetify({
  components,
  directives,
  display: {
    thresholds: {
      md: 960,
      lg: 1280,
      xl: 1920,
      xxl: 2560
    }
  },
  icons: {
    defaultSet: 'mdi',
    aliases,
    sets: { mdi }
  },
  locale: {
    locale: gettext.current,
    fallback: 'en',
    messages: {}
  },
  theme: {
    defaultTheme: 'system',
    themes: JSON.parse(document.querySelector('#app')?.dataset.theme || '{}')
  }
})

export default vuetify
