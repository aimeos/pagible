/**
 * @license MIT, https://opensource.org/license/mit
 */

import { toRaw } from 'vue'

export { frozenParse, safeParse, sanitize } from './json'

export const IMAGE_MIME_FILTER = { mime: ['image/gif', 'image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'] }

export const AUDIO_MIME_FILTER = { mime: ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/ogg', 'audio/webm', 'audio/wav', 'audio/wave', 'audio/x-wav', 'audio/vnd.wave', 'audio/flac', 'audio/x-flac'] }

export const VIDEO_MIME_FILTER = { mime: ['video/mp4', 'video/webm', 'video/ogg'] }

export const MEDIA_MIME_FILTER = { mime: [...IMAGE_MIME_FILTER.mime, ...VIDEO_MIME_FILTER.mime] }

export const PAGE_BULK_LIMIT = 1000

// Wraps the browser navigation so it can be replaced in tests
export const browser = {
  assign(url) {
    window.location.assign(url)
  }
}

/**
 * Deep clones a value, unwrapping reactive proxies at every level. structuredClone()
 * alone fails on nested proxies (e.g. from spreading reactive objects).
 *
 * @param {*} value Value to clone
 * @returns {*} Plain deep copy of the value
 */
export function clone(value) {
  value = toRaw(value)

  if (Array.isArray(value)) {
    return value.map(clone)
  }

  if (value === null || typeof value !== 'object') {
    return value
  }

  const proto = Object.getPrototypeOf(value)

  if (proto !== Object.prototype && proto !== null) {
    return structuredClone(value)
  }

  const copy = {}

  for (const key of Object.keys(value)) {
    copy[key] = clone(value[key])
  }

  return copy
}

/**
 * Creates a debounced version of a function that returns a Promise. The returned function
 * exposes a cancel() method that clears any pending invocation.
 *
 * @param {Function} func Function to debounce
 * @param {number} delay Delay in milliseconds
 * @returns {Function} Debounced function (with a cancel() method) that returns a Promise
 */
export function debounce(func, delay) {
  let timer

  function debounced(...args) {
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

  debounced.cancel = () => clearTimeout(timer)

  return debounced
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
 * Validates a form, scrolls to the first visible invalid input and focuses it
 *
 * Validating the form is required because inputs only show their errors after the
 * user interacted with them, e.g. empty required fields aren't highlighted otherwise.
 *
 * @param {Object} form VForm component instance
 * @returns {Promise<boolean>} True if an invalid input has been focused
 */
export async function focusInvalid(form) {
  const result = await form?.validate()

  for (const { id } of result?.errors || []) {
    const input = document.getElementById(id)

    if (input && input.getClientRects().length) {
      input.closest('.v-input')?.scrollIntoView?.({ block: 'center', behavior: 'smooth' })
      input.focus({ preventScroll: true })
      return true
    }
  }

  return false
}

/**
 * Checks if any value in an object is truthy
 *
 * @param {Object} obj Object to check
 * @returns {boolean} True if any value is truthy
 */
export function hasTrue(obj) {
  for (const k in obj) {
    if (obj[k]) return true
  }
  return false
}

/**
 * Checks if any value in an object has a truthy property
 *
 * @param {Object} obj Object to check
 * @param {string} prop Property name to check on each value
 * @returns {boolean} True if any value has a truthy property
 */
export function hasProp(obj, prop) {
  for (const k in obj) {
    if (obj[k]?.[prop]) return true
  }
  return false
}

/**
 * Returns a title string from a data object by checking title, text, or joining primitive values
 *
 * Array values (e.g. table rows/cells) are flattened so their content also
 * contributes to the title when no title or text field is set.
 *
 * @param {Object} data Data object to extract title from
 * @returns {string} Title string (max 100 chars)
 */
export function itemTitle(data) {
  const flatten = (v) =>
    Array.isArray(v)
      ? v.flatMap(flatten)
      : v && typeof v !== 'object' && typeof v !== 'boolean'
        ? [v]
        : []

  return (
    (
      data?.title ||
      data?.text ||
      Object.values(data || {})
        .flatMap(flatten)
        .filter((v) => !!String(v).trim())
        .join(' - ')
    ).substring(0, 100) || ''
  )
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

