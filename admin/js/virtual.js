/**
 * @license MIT, https://opensource.org/license/mit
 */

import { nextTick, toRaw } from 'vue'
import { uid } from './utils'

const keys = new WeakMap()

/**
 * Returns a stable fallback identity for an item.
 */
export function key(item) {
  if (!item || typeof item !== 'object') {
    return item
  }

  // reactive proxies and their raw objects share the same key
  const raw = toRaw(item)

  if (!keys.has(raw)) {
    keys.set(raw, uid())
  }

  return keys.get(raw)
}

/**
 * Passes the fallback identity of an item on to its replacement, e.g. a changed copy.
 */
export function rekey(from, to) {
  if (from && to && typeof from === 'object' && typeof to === 'object') {
    keys.set(toRaw(to), key(from))
  }

  return to
}

/**
 * Reveals an item in a virtual list.
 */
export async function reveal(list, key, align) {
  await nextTick()

  if (align === 'bottom') {
    list?.scrollToBottom()
  } else {
    list?.scrollToKey(key, align)
  }
}

/**
 * Returns the surrounding admin scroller, falling back to the document.
 */
export function scrollParent(element) {
  return element.closest('.scroll') || element.ownerDocument
}
