/**
 * @license MIT, https://opensource.org/license/mit
 */

import { defineStore } from 'pinia'

export const useDrawerStore = defineStore('drawer', {
  state: () => ({
    aside: null,
    nav: null
  }),

  actions: {
    toggle(key) {
      this[key] = !this[key]
    }
  }
})
