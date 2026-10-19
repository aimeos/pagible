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
        if (this.me?.permission?.[act]) {
          return true
        }
      }
      return false
    },
  }
})
