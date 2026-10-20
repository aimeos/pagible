/**
 * @license MIT, https://opensource.org/license/mit
 */

import { defineStore } from 'pinia'

/**
 * Side store with contextual information
 *
 * store: {
 *   type: {
 *     "heading": 3,
 *     "text": 8,
 *     "article": 1
 *   }
 * },
 * show: {
 *   type: {
 *     "heading": false,
 *     "text": true,
 *   }
 * }
 */
export const useSideStore = defineStore('side', {
  state: () => ({
    store: {},
    show: {}
  }),

  actions: {
    // all entries are shown until they are toggled
    shown(key, what) {
      return this.show[key]?.[what] ?? true
    },

    toggle(key, what) {
      if (!this.show[key]) {
        this.show[key] = {}
      }
      this.show[key][what] = !this.shown(key, what)
    }
  }
})
