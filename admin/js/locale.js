/**
 * @license MIT, https://opensource.org/license/mit
 */

import gettext, { load, ready } from './i18n'

/**
 * Switches the admin texts and the Vuetify components to the language
 *
 * @param {string} code Language code, e.g. "de" or "pt-BR"
 * @param {Object} vuetify Global Vuetify instance ($vuetify)
 * @returns {Promise<boolean>} True if the language has been applied
 */
export function switchLanguage(code, vuetify) {
  return ready
    .then(() => {
      if (!gettext.available[code]) {
        return [false]
      }

      return Promise.all([load(code), import('./vuetify').then((v) => v.switchLocale(code))])
    })
    .then(([applied]) => {
      if (applied) {
        vuetify.locale.current = code
      }

      return !!applied
    })
}
