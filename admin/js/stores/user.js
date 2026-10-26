/**
 * @license MIT, https://opensource.org/license/mit
 */

import { defineStore } from 'pinia'

/**
 * Signed-in user and its permissions
 */
export const useUserStore = defineStore('user', {
  state: () => ({
    me: null
  }),

  actions: {
    can(action) {
      if (!Array.isArray(action)) {
        action = [action]
      }

      for (const act of action) {
        // page actions include the same actions for language variants like on the server
        if (this.me?.permission?.[act] || (act.startsWith('variant:') && this.me?.permission?.['page:' + act.slice(8)])) {
          return true
        }
      }
      return false
    },

    /**
     * Checks if the user may add items, restricted users need at least one writable language
     *
     * @param {String} action Action name, e.g. "file:add"
     * @returns {Boolean} TRUE if allowed, FALSE if not
     */
    canAdd(action) {
      return this.can(action) && this.addLang() !== undefined
    },

    /**
     * Checks if the user may perform the action for items in the given language
     *
     * @param {String|Array} action Action name(s), any of them is sufficient
     * @param {String|null} lang Language code of the item, NULL or empty for language-neutral items
     * @returns {Boolean} TRUE if allowed, FALSE if not
     */
    canLang(action, lang) {
      if (!this.can(action)) {
        return false
      }

      if (this.unrestricted()) {
        return true
      }

      return !!lang && this.me.langs.includes(lang)
    },

    /**
     * Returns the language of new items added by the user
     *
     * Restricted users can only add items in their languages, so the first writable
     * language is used if the preferred one isn't writable.
     *
     * @param {String|null} preferred Language of the context, e.g. the edited page
     * @returns {String|null|undefined} Language code, NULL for language-neutral items of unrestricted users or undefined if no language is writable
     */
    addLang(preferred = null) {
      if (this.unrestricted()) {
        return null
      }

      return preferred && this.me.langs.includes(preferred) ? preferred : this.me.langs[0]
    },

    /**
     * Checks if the user may write in all languages
     *
     * @returns {Boolean} TRUE if unrestricted, FALSE if restricted to the assigned languages
     */
    unrestricted() {
      return !Array.isArray(this.me?.langs)
    },
  }
})
