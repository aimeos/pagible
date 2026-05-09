/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

import gettext from './i18n'
import { theme } from './config'
import { createVuetify } from 'vuetify'
import { aliases, mdi } from 'vuetify/iconsets/mdi-svg'

const localeMap = { zh: 'zhHans' }
const MAX_LOCALES = 3

export async function switchLocale(code) {
  const name = localeMap[code] || code

  try {
    const mod = await import(`../node_modules/vuetify/lib/locale/${name}.js`)
    const msgs = vuetify.locale.messages.value

    msgs[code] = mod.default
    const keys = Object.keys(msgs)

    if (keys.length > MAX_LOCALES) {
      const current = vuetify.locale.current.value

      for (const key of keys) {
        if (key !== current && key !== code && key !== 'en' && Object.keys(msgs).length > MAX_LOCALES) {
          delete msgs[key]
        }
      }
    }
  } catch {}
}

const vuetify = createVuetify({
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
    themes: theme
  }
})

export default vuetify
