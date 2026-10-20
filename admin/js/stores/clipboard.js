/**
 * @license MIT, https://opensource.org/license/mit
 */

import { defineStore } from 'pinia'

export const useClipboardStore = defineStore('clipboard', {
  state: () => ({
    entries: {}
  }),

  actions: {
    get(key, defval = null) {
      return this.entries[key] ?? defval
    },

    set(key, value) {
      if (typeof key !== 'string') {
        return
      }

      if (typeof value === 'object' && value !== null) {
        const json = JSON.stringify(value)
        if (json && json.length > 256 * 1024) {
          console.warn('Clipboard entry too large, skipping')
          return
        }
      }

      this.entries[key] = value
    }
  }
})
