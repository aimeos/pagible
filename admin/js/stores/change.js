/**
 * @license MIT, https://opensource.org/license/mit
 */

import { markRaw, toRaw } from 'vue'
import { defineStore } from 'pinia'

/**
 * Notifies the kept-alive list/tree views about items saved or published in
 * detail views, so they patch the matching node in place from the passed item
 * without reloading and without re-fetching from the server.
 *
 * Changes are kept as a stack per type because stacked detail views (e.g.
 * page -> file -> page -> element) can save several items of different types
 * before any underlying list re-renders. Each list reads pending items via
 * get() and removes the ones it has patched via patched().
 */
export const useChangeStore = defineStore('change', {
  state: () => ({
    changed: {}
  }),

  actions: {
    get(type) {
      return this.changed[type] || []
    },

    notify(type, item) {
      if (!type || !item?.id) return

      const list = this.get(type).filter((entry) => entry.id !== item.id)

      // a copy, marking the passed item raw would make it unreactive in the detail view
      list.push(markRaw({ ...toRaw(item) }))
      this.changed = { ...this.changed, [type]: list }
    },

    patched(type, ids) {
      const list = this.changed[type]

      if (!list?.length || !ids?.length) return

      this.changed = { ...this.changed, [type]: list.filter((entry) => !ids.includes(entry.id)) }
    }
  }
})
