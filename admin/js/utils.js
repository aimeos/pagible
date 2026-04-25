/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

import gettext from './i18n'
import { useAppStore, useLanguageStore } from './stores'

/**
 * Creates a debounced version of a function that returns a Promise
 *
 * @param {Function} func Function to debounce
 * @param {number} delay Delay in milliseconds
 * @returns {Function} Debounced function that returns a Promise
 */
export function debounce(func, delay) {
  let timer

  return function (...args) {
    return new Promise((resolve, reject) => {
      const context = this

      clearTimeout(timer)
      timer = setTimeout(() => {
        try {
          resolve(func.apply(context, args))
        } catch (error) {
          reject(error)
        }
      }, delay)
    })
  }
}

/**
 * Checks if a value is empty (null, undefined, or empty object)
 *
 * @param {*} val Value to check
 * @returns {boolean} True if the value is empty
 */
export function empty(val) {
  return val == null || (typeof val === 'object' && Object.keys(val).length === 0)
}

/**
 * Returns available locales as a list for dropdown menus
 *
 * @param {boolean} none If true, prepends a "None" option with null value
 * @returns {Array<{value: string|null, title: string}>} Locale options
 */
export function locales(none = false) {
  const languages = useLanguageStore()
  const list = []

  if (none) {
    list.push({ value: null, title: gettext.$gettext('None') })
  }

  languages.available.forEach((code) => {
    list.push({
      value: code,
      title: languages.translate(code) + ' (' + code.toUpperCase() + ')'
    })
  })

  return list
}

/**
 * Returns filter dropdown items for language selection in list views
 *
 * @param {String} allIcon Icon for the "All" item
 * @param {String} langIcon Icon for each language item
 * @returns {Array} List of { title, icon, value } objects
 */
export function languageFilter(allIcon, langIcon) {
  const list = [
    {
      title: gettext.$gettext('All'),
      icon: allIcon,
      value: { lang: null }
    }
  ]

  for (const entry of locales()) {
    list.push({
      title: entry.title,
      icon: langIcon,
      value: { lang: entry.value }
    })
  }

  return list
}

/**
 * Builds an HTML srcset string from a width-to-path map
 *
 * @param {Object} map Object mapping widths to file paths, e.g. {200: 'img/small.jpg', 800: 'img/large.jpg'}
 * @returns {string} Srcset string for use in <img> elements
 */
export function srcset(map) {
  let list = []
  for (const key in map || {}) {
    list.push(`${url(map[key])} ${key}w`)
  }
  return list.join(', ')
}

/**
 * Converts text to a URL-safe slug
 *
 * @param {string} text Input text
 * @returns {string} Lowercase slug with special characters replaced by hyphens
 */
export function slugify(text) {
  if (!text) return ''
  return text
    .replace(/[?&=%#@!$^*()+=\[\]{}|\\"'<>;:.,_\s]/gu, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
    .toLowerCase()
}

/**
 * Formats a value as a displayable string
 *
 * @param {*} value Value to format
 * @returns {string} Formatted string representation
 */
export function stringify(value) {
  if (value == null) return ''
  return typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value)
}

/**
 * Converts a base64-encoded string to a Blob
 *
 * @param {string} base64 Base64-encoded binary data
 * @param {string} mimeType MIME type for the resulting Blob
 * @returns {Blob|null} Blob object or null if input is empty
 */
export function toBlob(base64, mimeType = 'image/png') {
  if (!base64) {
    return null
  }

  const binary = atob(base64)
  const byteArray = new Uint8Array(binary.length)

  for (let i = 0; i < binary.length; i++) {
    byteArray[i] = binary.charCodeAt(i)
  }

  return new Blob([byteArray], { type: mimeType })
}

const supported = [
  'ar',
  'bg',
  'cs',
  'da',
  'de',
  'el',
  'en',
  'en-GB',
  'en-US',
  'es',
  'et',
  'fi',
  'fr',
  'he',
  'hu',
  'id',
  'it',
  'ja',
  'ko',
  'lt',
  'lv',
  'nb',
  'nl',
  'pl',
  'pt',
  'pt-BR',
  'ro',
  'ru',
  'sk',
  'sl',
  'sv',
  'th',
  'tr',
  'uk',
  'vi',
  'zh',
  'zh-Hans',
  'zh-Hant'
]

/**
 * Returns available locales that support AI translation, excluding the current locale
 *
 * @param {string|null} current Locale code to exclude from the list
 * @returns {Array<{code: string, name: string}>} Translation-supported locale options
 */
export function txlocales(current = null) {
  const languages = useLanguageStore()
  const list = []

  languages.available.forEach((code) => {
    if (supported.includes(code) && code !== current) {
      list.push({
        code: code,
        name: languages.translate(code) + ' (' + code.toUpperCase() + ')'
      })
    }
  })

  return list
}

/**
 * Generates a unique content ID based on the current date and time.
 *
 * @returns String Unique content ID
 */
const uid = (function () {
  const BASE64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_'
  const EPOCH = new Date('2025-01-01T00:00:00Z').getTime()

  let counter = 0

  return function () {
    // IDs will repeat after ~70 years
    const value = (((Date.now() - EPOCH) / 4096) << 7) | counter
    counter = (counter + 1) & 0b01111111

    return Array.from({ length: 6 }, (_, i) => {
      const index = (value >> (6 * (5 - i))) & 63
      return BASE64[i === 0 ? index % 52 : index]
    }).join('')
  }
})()

export { uid }

/**
 * Resolves a file path to a full URL using the app's file or proxy URL
 *
 * @param {string} path Relative path, absolute URL, or blob URL
 * @param {boolean} proxy If true, routes external URLs through the media proxy
 * @returns {string} Resolved URL
 */
export function url(path, proxy = false) {
  if (!path) return ''

  if (typeof path !== 'string') {
    return path
  }

  const app = useAppStore()

  if (proxy && path.startsWith('http')) {
    return app.urlproxy.replace(/_url_/, encodeURIComponent(path))
  }

  if (path.startsWith('http') || path.startsWith('blob:')) {
    return path
  }

  return app.urlfile.replace(/\/+$/g, '') + '/' + path
}
