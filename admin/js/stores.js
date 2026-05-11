/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

import gql from 'graphql-tag'
import { markRaw } from 'vue'
import { defineStore } from 'pinia'
import { apolloClient, clearUploadLink } from './graphql'
import {
  urladmin,
  urlproxy,
  urlpage,
  urlfile,
  multidomain,
  locales as appLocales
} from './config'

const FETCH_ME = gql`
  query {
    me {
      permission
      settings
      email
      name
      token
    }
  }
`

const LOGIN = gql`
  mutation ($email: String!, $password: String!) {
    cmsLogin(email: $email, password: $password) {
      permission
      settings
      email
      name
      token
    }
  }
`

const LOGOUT = gql`
  mutation {
    cmsLogout {
      email
      name
    }
  }
`

const SAVE_SETTINGS = gql`
  mutation ($settings: JSON!) {
    cmsUser(settings: $settings) {
      settings
    }
  }
`

const FETCH_SCHEMAS = gql`
  query {
    schemas {
      name
      label
      types
      content
      meta
      config
    }
  }
`

export const useAppStore = defineStore('app', {
  state: () => ({
    urladmin,
    urlproxy,
    urlpage,
    urlfile,
    multidomain
  })
})

export const useUserStore = defineStore('user', {
  state: () => ({
    me: null,
    urlintended: null,
    saveTimer: null
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

    intended(url) {
      return url ? (this.urlintended = url) : this.urlintended
    },

    async isAuthenticated() {
      if (this.me !== null) {
        return !!this.me
      }

      await apolloClient
        .query({
          query: FETCH_ME
        })
        .then((response) => {
          if (response.errors) {
            throw response
          }

          this.me = response.data.me
            ? { ...response.data.me, permission: JSON.parse(response.data.me.permission || '{}'), settings: JSON.parse(response.data.me.settings || '{}') }
            : false

          if (this.me?.token) {
            const app = useAppStore()
            app.urlproxy = urlproxy.replace('url=', 'token=' + encodeURIComponent(this.me.token) + '&url=')
          }
        })
        .catch((error) => {
          console.error('Failed to fetch user data', error)
          this.me = false
        })

      return !!this.me
    },

    login(email, password) {
      return apolloClient
        .mutate({
          mutation: LOGIN,
          variables: {
            email: email,
            password: password
          }
        })
        .then((response) => {
          if (response.errors) {
            throw response.errors
          }

          this.me = response.data.cmsLogin || false

          if (this.me?.permission) {
            this.me.permission = JSON.parse(this.me.permission)
          }

          if (this.me?.settings) {
            this.me.settings = JSON.parse(this.me.settings)
          }

          if (this.me?.token) {
            const app = useAppStore()
            app.urlproxy = urlproxy.replace('url=', 'token=' + encodeURIComponent(this.me.token) + '&url=')
          }

          return this.me
        })
        .catch((error) => {
          this.me = false
          throw error
        })
    },

    logout() {
      clearTimeout(this.saveTimer)
      this.saveTimer = null

      return apolloClient
        .mutate({
          mutation: LOGOUT
        })
        .then((response) => {
          if (response.errors) {
            throw response.errors
          }

          return response.data.cmsLogout || false
        })
        .finally(async () => {
          this.me = null

          useClipboardStore().$reset()
          useSideStore().$reset()
          clearUploadLink()

          const { disconnect } = await import('./echo')
          disconnect()

          return apolloClient.clearStore()
        })
    },

    async user() {
      if (await this.isAuthenticated()) {
        return this.me
      }

      return null
    },

    getData(panel, key, defval = null) {
      return this.me?.settings?.[panel]?.[key] ?? defval
    },

    saveData(panel, key, value) {
      if (!this.me) return

      if (!this.me.settings) {
        this.me.settings = {}
      }

      if (!this.me.settings[panel]) {
        this.me.settings[panel] = {}
      }

      this.me.settings[panel][key] = value

      clearTimeout(this.saveTimer)
      this.saveTimer = setTimeout(() => this.flush(), 60000)
    },

    flush() {
      if (!this.saveTimer || !this.me?.settings) return

      clearTimeout(this.saveTimer)
      this.saveTimer = null

      const messages = useMessageStore()

      apolloClient
        .mutate({
          mutation: SAVE_SETTINGS,
          variables: {
            settings: JSON.stringify(this.me.settings)
          }
        })
        .then((response) => {
          if (response.errors) {
            throw response.errors
          }
        })
        .catch((error) => {
          messages.add('Failed to save user settings:\n' + error, 'error')
          console.error('Failed to save user data', error)
        })
    }
  }
})

export const useClipboardStore = defineStore('clipboard', {
  state: () => ({}),

  actions: {
    clear() {
      this.$reset()
    },

    get(key, defval = null) {
      return this[key] ?? defval
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

      this[key] = value
    }
  }
})


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

import languages from './languages'

export const useLanguageStore = defineStore('language', {
  state: () => ({
    available: appLocales
  }),

  actions: {
    default() {
      return Object.keys(this.available)[0] || 'en'
    },

    translate(key) {
      return languages[key] ?? languages[key?.substring(0, 2)] ?? key
    }
  }
})

/**
 * Store for queued messages to display to the user
 */
export const useMessageStore = defineStore('message', {
  state: () => ({
    queue: []
  }),

  actions: {
    add(msg, type = 'info', timeout = null) {
      if (this.queue.length >= 10) {
        console.warn('Message queue overflow, dropping message:', msg)
        return
      }

      this.queue.push({
        text: msg,
        color: type,
        contentClass: 'text-pre-line',
        timeout: timeout || (type === 'error' ? 10000 : 3000)
      })
    }
  }
})

/**
 * Available element schemas fetched from GraphQL
 */
let _loading = null

export const useSchemaStore = defineStore('schema', {
  state: () => ({ themes: {}, content: {}, meta: {}, config: {} }),
  actions: {
    load() {
      if (_loading) return _loading instanceof Promise ? _loading : Promise.resolve()

      _loading = apolloClient.query({
        query: FETCH_SCHEMAS
      }).then((result) => {
        const content = {}, meta = {}, config = {}
        const parse = (v) => typeof v === 'string' ? JSON.parse(v) : v || {}
        const list = (result.data?.schemas || []).map(t => markRaw({
          ...t,
          types: parse(t.types),
          content: parse(t.content),
          meta: parse(t.meta),
          config: parse(t.config)
        }))

        for (const theme of list) {
          for (const key in theme.content) content[key] = markRaw(theme.content[key])
          for (const key in theme.meta) meta[key] = markRaw(theme.meta[key])
          for (const key in theme.config) config[key] = markRaw(theme.config[key])
        }

        this.themes = Object.freeze(Object.fromEntries(list.map(t => [t.name, t])))
        this.content = Object.freeze(content)
        this.meta = Object.freeze(meta)
        this.config = Object.freeze(config)

        _loading = true
      }).catch((err) => {
        _loading = null
        throw err
      }).then(() => _loading)

      return _loading
    }
  }
})

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
    shown(key, what) {
      if (typeof this.show[key] === 'undefined') {
        this.show[key] = {}
      }

      if (typeof this.show[key][what] === 'undefined') {
        this.show[key][what] = true
      }

      return this.show[key][what]
    },

    toggle(key, what) {
      if (!this.show[key]) {
        this.show[key] = {}
      }
      this.show[key][what] = !this.show[key][what]
    }
  }
})

export const useDirtyStore = defineStore('dirty', {
  state: () => ({
    dirty: false,
    pendingResolve: null,
    saveFn: null,
    show: false
  }),

  actions: {
    cancel() {
      this.show = false
      this.resolve(false)
    },

    confirm(action) {
      return new Promise((resolve) => {
        this.pendingResolve = resolve
        this.show = true
        this._action = action
      })
    },

    async discard() {
      await this.finalize()
    },

    async finalize() {
      this.dirty = false
      this.show = false

      const action = this._action
      this._action = null

      if (action) await action()

      this.resolve(true)
    },

    register(saveFn) {
      this.saveFn = saveFn
    },

    resolve(value) {
      const fn = this.pendingResolve
      this.pendingResolve = null
      this._action = null

      if (fn) fn(value)
    },

    async saveAndLeave() {
      if (this.saveFn) {
        const result = await this.saveFn(true)
        if (result === false) return
      }

      await this.finalize()
    },

    set(value) {
      if (value !== this.dirty) {
        this.dirty = value
      }
    },

    unregister() {
      this.dirty = false
      this.saveFn = null
      this.resolve(false)
    }
  }
})

export const useViewStack = defineStore('viewStack', {
  state: () => ({
    stack: []
  }),

  actions: {
    async closeView() {
      const dirtyStore = useDirtyStore()

      if (dirtyStore.dirty) {
        await dirtyStore.confirm(() => {
          dirtyStore.unregister()
          this.stack.pop()
        })
        return
      }

      dirtyStore.unregister()
      this.stack.pop()
    },

    openView(component, props = {}) {
      if (!component) {
        console.error('Component is not defined')
        return
      }

      if (this.stack.length >= 3) {
        this.stack.splice(0, this.stack.length - 2)
      }

      this.stack.push({
        component: markRaw(component),
        props: markRaw(props || {})
      })
    }
  }
})
