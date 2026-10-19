/**
 * @license MIT, https://opensource.org/license/mit
 */

import { nextTick } from 'vue'
const keys = new WeakMap()
let counter = 0

/**
 * Returns a stable fallback identity for an item.
 */
export function key(item) {
  if (!item || typeof item !== 'object') {
    return item
  }

  if (!keys.has(item)) {
    keys.set(item, 'key-' + ++counter)
  }

  return keys.get(item)
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
