/**
 * @license MIT, https://opensource.org/license/mit
 */

import { defineStore } from 'pinia'

/**
 * Store for the unsaved changes of the open detail views
 *
 * Stacked detail views register on top of the view below, so closing them restores
 * the state of the view below. "dirty" is true if any registered view has unsaved changes.
 */
export const useDirtyStore = defineStore('dirty', {
  state: () => ({
    dirty: false,
    // registered views as { save, dirty }, the last one is the view in front
    entries: [],
    // called after leaving was confirmed, e.g. to navigate away
    pendingAction: null,
    pendingResolve: null,
    // the confirmation only applies to the view in front, e.g. when closing a stacked view
    pendingTop: false,
    // save function of the view in front
    saveFn: null,
    show: false
  }),

  actions: {
    cancel() {
      this.show = false
      this.resolve(false)
    },

    /**
     * Asks the user to save or discard the unsaved changes
     *
     * @param {Function|null} action Called after leaving was confirmed
     * @param {Boolean} top True if only the changes of the view in front are concerned
     * @returns {Promise<Boolean>} True if the user saved or discarded the changes
     */
    confirm(action = null, top = false) {
      // a previous confirmation which is still open is dismissed
      this.resolve(false)

      return new Promise((resolve) => {
        this.pendingResolve = resolve
        this.pendingAction = action
        this.pendingTop = top
        this.show = true
      })
    },

    async finalize() {
      for (const entry of this.targets()) {
        entry.dirty = false
      }
      this.sync(this.entries.length ? undefined : false)
      this.show = false

      // taken before running the action because it may unregister the view
      const action = this.pendingAction
      const resolve = this.pendingResolve
      this.pendingAction = null
      this.pendingResolve = null
      this.pendingTop = false
      let ok = false

      try {
        if (action) await action()
        ok = true
      } catch (error) {
        // called from event handlers, the caller of confirm() gets false instead
        console.error('Pending action failed', error)
      } finally {
        resolve?.(ok)
      }
    },

    register(saveFn) {
      this.entries.push({ save: saveFn, dirty: false })
      this.sync()
    },

    resolve(value) {
      const fn = this.pendingResolve
      this.pendingResolve = null
      this.pendingAction = null
      this.pendingTop = false

      if (fn) fn(value)
    },

    async saveAndLeave() {
      // the view in front is saved first
      for (const entry of [...this.targets()].reverse()) {
        if (entry.dirty && entry.save && !(await entry.save(true))) {
          return
        }
      }

      await this.finalize()
    },

    /**
     * Sets the unsaved state of the registered view (or the view in front)
     */
    set(value, saveFn = null) {
      const entry = saveFn ? this.entries.find((entry) => entry.save === saveFn) : this.entries.at(-1)

      if (entry) {
        entry.dirty = value
        this.sync()
      } else if (!saveFn) {
        this.dirty = value
      }
    },

    // updates the summary state from the registered views
    sync(dirty = this.entries.some((entry) => entry.dirty)) {
      this.dirty = dirty
      this.saveFn = this.entries.at(-1)?.save || null
    },

    // registered views concerned by the open confirmation
    targets() {
      return this.pendingTop ? this.entries.slice(-1) : this.entries
    },

    /**
     * Removes the registered view (or the view in front) and restores the state of the view below
     */
    unregister(saveFn = null) {
      const idx = saveFn ? this.entries.findIndex((entry) => entry.save === saveFn) : this.entries.length - 1

      if (idx >= 0) {
        this.entries.splice(idx, 1)
      }

      this.sync()

      // an open confirmation is dismissed, finalize() takes it before running the action
      if (this.pendingResolve) {
        this.show = false
        this.resolve(false)
      }
    }
  }
})
