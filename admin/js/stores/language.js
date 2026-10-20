/**
 * @license MIT, https://opensource.org/license/mit
 */

import { defineStore } from 'pinia'
import languages from '../languages'
import { locales as appLocales, locale as appLocale } from '../config'

export const useLanguageStore = defineStore('language', {
  state: () => ({
    available: appLocales
  }),

  actions: {
    default() {
      return this.available.includes(appLocale) ? appLocale : this.available[0] || 'en'
    },

    translate(key) {
      return languages[key] ?? languages[key?.substring(0, 2)] ?? key
    }
  }
})
