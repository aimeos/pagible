/**
 * @license MIT, https://opensource.org/license/mit
 */

const UNSAFE_KEYS = ['__proto__', 'constructor', 'prototype']

/**
 * Parses a JSON string, strips prototype-polluting keys and freezes the result.
 */
export function frozenParse(str) {
  try {
    return Object.freeze(
      JSON.parse(str || '{}', (key, value) =>
        UNSAFE_KEYS.includes(key) ? undefined : value
      ) || {}
    )
  } catch {
    return Object.freeze({})
  }
}

/**
 * Parses a JSON string while stripping prototype-polluting keys.
 */
export function safeParse(str, fallback = {}) {
  try {
    return (
      JSON.parse(str || 'null', (key, value) =>
        UNSAFE_KEYS.includes(key) ? undefined : value
      ) ?? fallback
    )
  } catch {
    return fallback
  }
}

/**
 * Recursively removes prototype-polluting keys from an already-parsed value.
 */
export function sanitize(value) {
  if (Array.isArray(value)) {
    return value.map(sanitize)
  }

  if (value && typeof value === 'object') {
    const out = {}
    for (const key in value) {
      if (!UNSAFE_KEYS.includes(key) && Object.prototype.hasOwnProperty.call(value, key)) {
        out[key] = sanitize(value[key])
      }
    }
    return out
  }

  return value
}
