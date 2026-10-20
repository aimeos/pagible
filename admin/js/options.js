/**
 * @license MIT, https://opensource.org/license/mit
 */

import gettext from './i18n'
import { useLanguageStore } from './stores'

let langCache = null

const supported = new Set([
  'ar', 'bg', 'cs', 'da', 'de', 'el', 'en', 'en-GB', 'en-US',
  'es', 'et', 'fi', 'fr', 'he', 'hu', 'id', 'it', 'ja', 'ko',
  'lt', 'lv', 'nb', 'nl', 'pl', 'pt', 'pt-BR', 'ro', 'ru', 'sk',
  'sl', 'sv', 'th', 'tr', 'uk', 'vi', 'zh', 'zh-Hans', 'zh-Hant'
])

/**
 * Returns the cached locale lists, rebuilt when the available languages change
 *
 * @returns {Object} { locales, tx, filter } lists of the available languages
 */
function langs() {
  const languages = useLanguageStore()

  if (langCache?.key !== languages.available) {
    const all = languages.available.map((code) => ({
      code,
      name: languages.translate(code) + ' (' + code.toUpperCase() + ')'
    }))

    langCache = {
      key: languages.available,
      locales: all.map(({ code, name }) => ({ value: code, title: name })),
      tx: all.filter(({ code }) => supported.has(code)),
      filter: {}
    }
  }

  return langCache
}

/**
 * Returns the translated cache duration options of pages in minutes
 */
export function cacheItems() {
  const { $gettext, $ngettext } = gettext

  return [
    { key: 0, val: $gettext('No cache') },
    ...[1, 5, 15, 30].map((num) => ({ key: num, val: $ngettext('%{num} minute', '%{num} minutes', num, { num }) })),
    ...[1, 3, 6, 12, 24].map((num) => ({ key: num * 60, val: $ngettext('%{num} hour', '%{num} hours', num, { num }) }))
  ]
}

/**
 * Returns the translated status options of pages
 */
export function statusItems() {
  const { $gettext } = gettext

  return [
    { key: 0, val: $gettext('Disabled') },
    { key: 1, val: $gettext('Enabled') },
    { key: 2, val: $gettext('Hidden in navigation') }
  ]
}

/**
 * Returns filter dropdown items for language selection in list views
 *
 * @param {String} allIcon Icon for the "All" item
 * @param {String} langIcon Icon for each language item
 * @returns {Array} List of { title, icon, value } objects
 */
export function languageFilter(allIcon, langIcon) {
  const cache = langs()

  // the translated "All" entry changes when the catalog is loaded or the admin language is switched
  return (cache.filter[gettext.current + allIcon + langIcon] ??= [
    { title: gettext.$gettext('All'), icon: allIcon, value: { lang: null } },
    ...cache.locales.map((entry) => ({ title: entry.title, icon: langIcon, value: { lang: entry.value } }))
  ])
}

/**
 * Returns available locales as a list for dropdown menus
 *
 * @param {boolean} none If true, prepends a "None" option with null value
 * @returns {Array<{value: string|null, title: string}>} Locale options
 */
export function localeItems(none = false) {
  const list = langs().locales
  return none ? [{ value: null, title: gettext.$gettext('None') }, ...list] : list
}

/**
 * Returns available locales that support AI translation, excluding the current locale
 *
 * @param {string|null} current Locale code to exclude from the list
 * @returns {Array<{code: string, name: string}>} Translation-supported locale options
 */
export function txLocaleItems(current = null) {
  const list = langs().tx
  return current ? list.filter((entry) => entry.code !== current) : list
}
