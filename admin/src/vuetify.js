/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

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
          surface: '#ffffff',
          primary: '#3070a0',
          info: '#00c8d8',
          error: '#f44038',
          success: '#00a070',
          warning: '#ffb080',
          accent: '#ffa890',
        }
      },
      dark: {
        colors: {
          background: '#000000',
          surface: '#101418',
          primary: '#105090',
          info: '#00c8d8',
          error: '#d06878',
          success: '#008040',
          warning: '#e0a080',
          accent: '#ffa890',
        }
      }
    },
  },
})

export default vuetify