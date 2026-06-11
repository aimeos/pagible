/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

const el = document.querySelector('#app')
const dataset = el?.dataset || {}

export const urladmin = dataset.urladmin || '/cmsadmin'
export const urlproxy = dataset.urlproxy || '/cmsproxy?url=_url_'
export const urlpage = dataset.urlpage || '/_path_'
export const urlfile = dataset.urlfile || '/storage'
export const urlgraphql = dataset.urlgraphql || '/graphql'
export const multidomain = parseInt(dataset.multidomain) || 0

// Strip prototype-polluting keys from the server-rendered bootstrap data.
// Inlined (not imported from utils) because these run at module load time and
// utils pulls in stores/config, which would create an init-order cycle.
const safeParse = (str, fallback) => {
  try {
    return JSON.parse(str || 'null', (key, value) =>
      key === '__proto__' || key === 'constructor' || key === 'prototype' ? undefined : value
    ) ?? fallback
  } catch {
    return fallback
  }
}

export const locales = safeParse(dataset.locales, ['en'])
export const theme = safeParse(dataset.theme, {})
