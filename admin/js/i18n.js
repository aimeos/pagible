/**
 * @license MIT, https://opensource.org/license/mit
 */

import { createGettext } from 'vue3-gettext'
import { plugins } from './config'

const gettext = createGettext({
  defaultLanguage: 'en',
  translations: {},
  silent: true
})

const cache = new Map()
let request = 0

function object(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
}

/**
 * Adds one package catalog to the shared translations. The package's own
 * context always wins, other contexts (e.g. theme element and field names)
 * are only added if missing so host and package messages are never replaced.
 */
export function mergeCatalog(translations, catalog, locale, context) {
  const current = translations[locale] || {}
  const merged = { ...current }

  for (const [id, contexts] of Object.entries(catalog?.[locale] || {})) {
    if (!object(contexts)) continue

    const existing = merged[id]
    const entry = object(existing) ? existing : existing === undefined ? {} : { '': existing }
    const own = contexts[context] === undefined ? {} : { [context]: contexts[context] }

    merged[id] = { ...contexts, ...entry, ...own }
  }

  return { ...translations, [locale]: merged }
}

/**
 * Translates a registered panel label in its package context.
 */
export function pluginLabel(panel, translator = gettext) {
  return panel.i18n
    ? translator.$pgettext(panel.i18n, panel.label)
    : translator.$gettext(panel.label)
}

function fetchCatalog(url) {
  if (!cache.has(url)) {
    const promise = fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    }).then((response) => {
      if (!response.ok) throw new Error(`HTTP ${response.status}`)
      return response.json()
    }).catch((error) => {
      cache.delete(url)
      throw error
    })

    cache.set(url, promise)
  }

  return cache.get(url)
}

/**
 * Loads the host and package catalogs for one locale into the shared instance.
 */
export async function load(locale) {
  const current = ++request
  const entries = Object.entries(plugins.i18n || {})
  const [core, results] = await Promise.all([
    import(`../i18n/${locale}.json`),
    Promise.allSettled(entries.map(([, url]) => fetchCatalog(url.replace('{locale}', locale))))
  ])
  let translations = core.default || core

  results.forEach((result, index) => {
    const [context, url] = entries[index]

    if (result.status === 'fulfilled') {
      translations = mergeCatalog(translations, result.value, locale, context)
    } else {
      console.warn(`Failed to load plugin translations from ${url}`, result.reason)
    }
  })

  if (current === request) {
    gettext.translations = translations
    gettext.current = locale
    return true
  }

  return false
}

// Traditional Chinese browser locales which use the zh-TW catalog
const hant = ['zh-hant', 'zh-hk', 'zh-mo', 'zh-tw']

/**
 * Returns the supported locale matching a browser language, e.g. "pt-BR" for "pt-br",
 * "zh-TW" for "zh-Hant-HK" and "pt" for "pt-PT"
 */
export function match(lang, supported) {
  const code = lang?.toLowerCase() || ''
  const find = (value) => supported.find((locale) => locale.toLowerCase() === value)

  return find(code)
    || (hant.some((prefix) => code.startsWith(prefix)) && find('zh-tw'))
    || find(code.split('-')[0])
}

export const ready = import(`../i18n/LINGUAS?raw`).then((content) => {
  const supported = content.default.trim().split(/\s+/)
  const locale =
    (navigator.languages || [navigator.language])
      .map((lang) => match(lang, supported))
      .find(Boolean) || 'en'

  gettext.available = Object.fromEntries(supported.map((value) => [value, value]))

  return load(locale)
})

export default gettext
