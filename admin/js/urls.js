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

  if (proxy && path.startsWith('http')) {
    return app.urlproxy.replace(/_url_/, encodeURIComponent(path))
  }

  if (path.startsWith('http') || path.startsWith('blob:')) {
    return path
  }

  return app.urlfile.replace(/\/+$/g, '') + '/' + path
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
