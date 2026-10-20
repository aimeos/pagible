/**
 * @license MIT, https://opensource.org/license/mit
 */

import { toRaw } from 'vue'

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

// Delay before server requests triggered by typing (searches, lookups, validation requests)
export const REQUEST_DELAY = 500
// Delay before local updates triggered by typing or repeated events
export const INPUT_DELAY = 300
// Duration of drag and drop reorder animations
export const DRAG_ANIMATION = 300

/**
 * Creates a debounced version of a function that returns a Promise. All calls of one burst
 * share the result of the final invocation and the returned function exposes a cancel()
 * method that clears any pending invocation and resolves the waiting calls with undefined
 * and a flush() method that runs a pending invocation immediately.
 *
 * @param {Function} func Function to debounce
 * @param {number} delay Delay in milliseconds
 * @returns {Function} Debounced function (with cancel() and flush() methods) that returns a Promise
 */
/**
 * Checks if the key event belongs to the input of an input method editor, e.g. Enter confirming a CJK word
 *
 * Safari sends the confirming keydown after the composition ended, only recognizable by its key code.
 *
 * @param {KeyboardEvent} ev Keyboard event
 * @returns {Boolean} TRUE if the event is part of the composition
 */
export function composing(ev) {
  return ev.isComposing || ev.keyCode === 229
}

/**
 * Direction of moving an entry by Alt + arrow key as keyboard alternative to dragging
 *
 * @param {KeyboardEvent} ev Keyboard event
 * @param {Boolean} horizontal Use the left/right instead of the up/down arrow keys
 * @param {Boolean} rtl Right-to-left layout which reverses the left/right direction
 * @returns {Number} -1 for backward, 1 for forward and 0 for other keys
 */
export function moveStep(ev, horizontal = false, rtl = false) {
  if (!ev.altKey || ev.ctrlKey || ev.metaKey || ev.shiftKey) {
    return 0
  }

  const step = (horizontal ? { ArrowLeft: -1, ArrowRight: 1 } : { ArrowUp: -1, ArrowDown: 1 })[ev.key] || 0
  return horizontal && rtl ? -step : step
}

export function debounce(func, delay) {
  let timer
  let invoke = null // runs the pending invocation
  let waiting = [] // settle callbacks of the calls of the current burst

  function debounced(...args) {
    const context = this

    clearTimeout(timer)

    return new Promise((resolve, reject) => {
      waiting.push({ resolve, reject })

      invoke = () => {
        const calls = waiting
        waiting = []
        invoke = null

        try {
          const result = func.apply(context, args)
          calls.forEach((call) => call.resolve(result))
        } catch (error) {
          calls.forEach((call) => call.reject(error))
        }
      }

      timer = setTimeout(invoke, delay)
    })
  }

  debounced.flush = () => {
    clearTimeout(timer)
    invoke?.()
  }

  debounced.cancel = () => {
    invoke = null
    clearTimeout(timer)
    waiting.forEach((call) => call.resolve(undefined))
    waiting = []
  }

  return debounced
}

/**
 * Returns the string with the first character in upper case
 *
 * @param {string} str String to convert
 * @returns {string} Converted string
 */
export function ucfirst(str) {
  return str ? str[0].toUpperCase() + str.slice(1) : ''
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
 * Returns the date in the "YYYY-MM-DD HH:MM:SS" UTC format used by the server
 *
 * @param {Date} date Date to format, now by default
 * @returns {string} Formatted date
 */
export function dbDate(date = new Date()) {
  return date.toISOString().substring(0, 19).replace('T', ' ')
}

/**
 * Formats a date value for display in the given UI locale
 *
 * @param {string|number|Date} value Date value
 * @param {string} locale UI locale, e.g. $vuetify.locale.current
 * @param {boolean} time Include the time, otherwise only the date
 * @returns {string} Localized date
 */
export function formatDate(value, locale, time = true) {
  const date = new Date(value)
  return time ? date.toLocaleString(locale) : date.toLocaleDateString(locale)
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

const BASE64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_'

/**
 * Generates a random content ID which is unique within a page.
 * The first character is always a letter, the others are base64url characters.
 *
 * @returns String Random content ID
 */
const uid = function () {
  return Array.from(
    crypto.getRandomValues(new Uint8Array(6)),
    (byte, i) => BASE64[i === 0 ? byte % 52 : byte & 63]
  ).join('')
}

export { uid }
