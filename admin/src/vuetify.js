import gettext from './i18n'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { createVuetify } from 'vuetify'
import { ar, bg, cs, da, de, el, en, es, et, fi, fr, he, hu, id, it, ja, ko, lt, lv, no, nl, pl, pt, ro, ru, sk, sl, sv, th, tr, uk, vi, zhHans } from 'vuetify/locale'


const vuetify = createVuetify({
  components,
  directives,
  icons: {
    defaultSet: 'mdi',
  },
  locale: {
    locale: gettext.current,
    fallback: 'en',
    messages: { ar, bg, cs, da, de, el, en, es, et, fi, fr, he, hu, id, it, ja, ko, lt, lv, no, nl, pl, pt, ro, ru, sk, sl, sv, th, tr, uk, vi, zhHans }
  },
  theme: {
    defaultTheme: 'system',
    themes: {
      light: {
        colors: {
          background: '#f0f4f8',
          primary: '#3070a0',
          info: '#03c9d7',
          error: '#f44336',
          success: '#00c853',
          warning: '#ffb080',
          accent: '#ffab91',
        }
      },
      dark: {
        colors: {
          background: '#121212',
          primary: '#105090',
          info: '#03c9d7',
          error: '#cf6679',
          success: '#03dac6',
          warning: '#cb6b0b',
          accent: '#ffab91',
        }
      }
    },
  },
})

export default vuetify