/**
 * @license MIT, https://opensource.org/license/mit
 */

import { defineStore } from 'pinia'

export const useConfirmStore = defineStore('confirm', {
  state: () => ({
    action: '',
    hint: '',
    items: [],
    pendingResolve: null,
    show: false,
    text: ''
  }),

  actions: {
    /**
     * Asks the user to confirm an action
     *
     * @param {String} action Translated action name used as title and button label
     * @param {String} text Translated question
     * @param {Array} items Affected entries as { name, info } objects
     * @param {String} hint Optional translated hint below the entries
     * @returns {Promise<Boolean>} True if the user confirmed the action
     */
    ask(action, text, items = [], hint = '') {
      this.close(false)
      Object.assign(this, { action, hint, items, show: true, text })

      return new Promise((resolve) => {
        this.pendingResolve = resolve
      })
    },

    close(value) {
      const fn = this.pendingResolve
      this.pendingResolve = null
      this.show = false

      if (fn) fn(value)
    },

    purge(items, hint = '') {
      return this.ask('', '', items, hint)
    }
  }
})
