/**
 * @license MIT, https://opensource.org/license/mit
 */

import { markRaw } from 'vue'
import { defineStore } from 'pinia'
import { useDirtyStore } from './dirty'

// Maximum number of stacked views; the oldest ones without unsaved changes are removed when opening another one
const MAX_VIEWS = 3

export const useViewStack = defineStore('viewStack', {
  state: () => ({
    stack: []
  }),

  actions: {
    async closeView() {
      const dirtyStore = useDirtyStore()

      // only the changes of the view in front matter, the view unregisters itself when unmounted
      if (dirtyStore.entries.at(-1)?.dirty) {
        return dirtyStore.confirm(() => {
          this.stack.pop()
        }, true)
      }

      this.stack.pop()
      return true
    },

    openView(component, props = {}) {
      if (!component) {
        console.error('Component is not defined')
        return
      }

      if (this.stack.length >= MAX_VIEWS) {
        const dirtyStore = useDirtyStore()
        const entries = dirtyStore.entries
        // stacked views register after the routed view, in the same order as the stack
        const offset = entries.length - this.stack.length
        const drop = new Set()
        let count = this.stack.length - (MAX_VIEWS - 1)

        // views with (possibly) unsaved changes are kept even if the stack grows beyond the limit
        this.stack.forEach((view, idx) => {
          if (count > 0 && (!dirtyStore.dirty || (offset >= 0 && !entries[offset + idx]?.dirty))) {
            drop.add(view)
            count--
          }
        })

        this.stack = this.stack.filter((view) => !drop.has(view))
      }

      // detail views copy their initial item, so the props bag can stay raw
      this.stack.push({
        component: markRaw(component),
        props: markRaw(props || {})
      })
    }
  }
})
