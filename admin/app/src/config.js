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
export const config = JSON.parse(dataset.config || '{}')
export const schemas = JSON.parse(dataset.schemas || '{}')
export const theme = JSON.parse(dataset.theme || '{}')
