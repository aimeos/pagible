/**
 * @license MIT, https://opensource.org/license/mit
 */

import { safeParse } from './utils'

const el = document.querySelector('#app')
const dataset = el?.dataset || {}

export const multidomain = parseInt(dataset.multidomain) || 0
export const urladmin = dataset.urladmin || '/cmsadmin'
export const urlproxy = dataset.urlproxy || '/cmsproxy?url=_url_'
export const urlpage = dataset.urlpage || '/_path_'
export const urlfile = dataset.urlfile || '/storage'
export const urlgraphql = dataset.urlgraphql || '/graphql'
// `??` not `||`: the layout emits an empty string when the cms.chat route is absent (feature off),
// which must stay empty rather than fall back to a path that would 404; only a missing attribute defaults.
export const urlchat = dataset.urlchat ?? '/cmsapi/chat'

// Strip prototype-polluting keys from the server-rendered bootstrap data via the shared
// safeParse(). The import creates a config<->utils cycle (utils -> stores -> config), but it's
// harmless: safeParse is a hoisted function declaration, so its binding is initialized before any
// module body runs and these load-time calls resolve it even mid-cycle.
export const locales = safeParse(dataset.locales, ['en'])
export const theme = safeParse(dataset.theme, {})
