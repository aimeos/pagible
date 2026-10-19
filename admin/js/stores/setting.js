/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { reactive, ref, watch } from 'vue'
import { defineStore } from 'pinia'
import { apolloClient } from '../graphql'
import gettext from '../i18n'
import { useMessageStore } from './message'
import { useUserStore } from './user'

const SAVE_SETTINGS = gql`
  mutation ($settings: JSON!) {
    setUser(settings: $settings) {
      id
    }
  }
`

// Delay in milliseconds to collect setting changes before they are saved in one request
const SAVE_DELAY = 60000

/**
 * Panel settings of the signed-in user like list filters and sort orders, saved delayed in one request
 */
export const useSettingStore = defineStore('setting', {
  state: () => ({
    saveTimer: null
  }),

  actions: {
    clear() {
      // saves the pending changes while the user is still loaded
      this.flush()
      clearTimeout(this.saveTimer)
      this.saveTimer = null
    },

    /**
     * Returns the reactive list filter of the panel, saved per user on every change
     *
     * Call it while a component is set up, e.g. in data(), so the watcher stops on unmount.
     *
     * @param {String} panel Settings key of the panel, e.g. "page"
     * @param {Object} defaults Filter values used if nothing is saved yet
     */
    filter(panel, defaults) {
      const filter = reactive({ ...defaults, ...this.getData(panel, 'filter') })
      watch(filter, (value) => this.saveData(panel, 'filter', value))
      return filter
    },

    /**
     * Returns a ref of the panel setting, saved per user on every change
     *
     * Call it while a component is set up, e.g. in data(), so the watcher stops on unmount.
     *
     * @param {String} panel Settings key of the panel, e.g. "page"
     * @param {String} key Name of the setting, e.g. "sort"
     * @param {*} defval Value used if nothing is saved yet
     */
    setting(panel, key, defval) {
      const value = ref(this.getData(panel, key, defval))
      watch(value, (val) => this.saveData(panel, key, val), { deep: true })
      return value
    },

    getData(panel, key, defval = null) {
      return useUserStore().me?.settings?.[panel]?.[key] ?? defval
    },

    saveData(panel, key, value) {
      const me = useUserStore().me

      if (!me) return

      if (!me.settings) {
        me.settings = {}
      }

      if (!me.settings[panel]) {
        me.settings[panel] = {}
      }

      me.settings[panel][key] = value

      clearTimeout(this.saveTimer)
      this.saveTimer = setTimeout(() => this.flush(), SAVE_DELAY)
    },

    flush() {
      const settings = useUserStore().me?.settings

      if (!this.saveTimer || !settings) return

      clearTimeout(this.saveTimer)
      this.saveTimer = null

      const messages = useMessageStore()

      apolloClient.mutate({
        mutation: SAVE_SETTINGS,
        variables: {
          settings: JSON.stringify(settings)
        }
      }).catch((error) => {
        messages.error(gettext.$gettext('Failed to save user settings'), error)
      })
    }
  }
})

// saves the pending changes before the page is closed or hidden (may not be shown again)
if (typeof window !== 'undefined' && typeof document !== 'undefined') {
  window.addEventListener('pagehide', () => useSettingStore().flush())
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
      useSettingStore().flush()
    }
  })
}
