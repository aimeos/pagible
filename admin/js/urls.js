/**
 * @license MIT, https://opensource.org/license/mit
 */

import { useAppStore } from './stores'

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

  const absolute = /^https?:\/\//i.test(path)

  if (proxy && absolute) {
    return app.urlproxy.replace(/_url_/, encodeURIComponent(path))
  }

  if (absolute || path.startsWith('blob:')) {
    return path
  }

  return app.urlfile.replace(/\/+$/g, '') + '/' + path
}

/**
 * Builds the frontend URL of a page from the app's page URL template
 *
 * @param {Object} page Page data with domain and path
 * @param {boolean} absolute If true, returns an absolute URL using the current host, protocol and port
 * @returns {string} Page URL
 */
export function pageUrl(page, absolute = false) {
  let url = useAppStore().urlpage
  const domain = page?.domain || ''

  // function replacers, so "$" in paths or domains isn't treated as replacement pattern

  if (absolute) {
    const result = new URL(
      url
        .replace(/_domain_/, () => domain || window.location.hostname)
        .replace(/_path_/, () => page?.path || '/')
        .replace(/\/{2,}$/, '/'),
      window.location.origin
    )

    result.protocol = window.location.protocol
    result.port = window.location.port

    return result.href
  }

  if (!domain) {
    url = url.replace(/^[a-z][a-z\d+.-]*:\/\/_domain_/i, '').replace(/\/_domain_(?=\/|$)/, '')
  }

  return url
    .replace(/_domain_/, () => domain)
    .replace(/_path_/, () => page?.path || '')
    .replace(/([^:/])\/+$/, '$1')
}

/**
 * Resolves a public or private File URL for the admin editor.
 *
 * @param {Object} file File data including id, disk, path and previews
 * @param {string|null} path Original or preview path
 * @param {boolean} proxy Route a public remote URL through the media proxy
 * @returns {string} Resolved URL
 */
export function fileurl(file, path = null, proxy = false) {
  path ??= file?.path

  if (!file || file.disk !== 'private') {
    return url(path, proxy)
  }

  const preview = Object.entries(file.previews || {}).find((entry) => entry[1] === path)
  const variant = preview?.[0] || ''

  return useAppStore()
    .urlasset.replace(/_file_/, encodeURIComponent(file.id))
    .replace(/_variant_/, encodeURIComponent(variant))
    .replace(/\/+$/g, '')
}

/**
 * Generates a srcset for a public or private File.
 *
 * @param {Object} file File data including previews
 * @returns {string} Responsive image srcset
 */
export function filesrcset(file) {
  return Object.entries(file?.previews || {})
    .map(([width, path]) => `${fileurl(file, path)} ${width}w`)
    .join(',')
}

/**
 * Returns the URL of the login page of the application for single sign-on
 *
 * @param {String} template Login page URL (cms.admin.login), may contain the "_url_" placeholder
 * @param {String} back URL the login page should return to afterwards
 * @returns {String} Login page URL
 */
export function loginUrl(template, back = window.location.href) {
  return template.replace('_url_', encodeURIComponent(back))
}
