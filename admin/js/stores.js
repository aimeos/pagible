/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { defineAsyncComponent, h, markRaw, reactive, ref, watch } from 'vue'
import { defineStore } from 'pinia'
import { apolloClient, clearUploadLink } from './graphql'
import { disconnect, resubscribe } from './echo'
import gettext from './i18n'
import { safeParse, sanitize } from './json'
import {
  urladmin,
  urlasset,
  urlproxy,
  urlpage,
  urlcsrf,
  urlfile,
  multidomain,
  sessionlifetime,
  locales as appLocales,
  plugins
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
      id
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

const FETCH_TOKEN = gql`
  query {
    me {
      token
    }
  }
`

const SAVE_SETTINGS = gql`
  mutation ($settings: JSON!) {
    setUser(settings: $settings) {
      id
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
    urlasset,
    urlproxy,
    urlpage,
    urlfile,
    multidomain
  })
})

// Tells the other admin tabs that the user signed in so they can continue with the new session
const authChannel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('cms-auth') : null

// Pending requests waiting for the user to sign in again after the session expired
let reauthWaiting = []
let reauthListener = null
// Checks the session after it would have expired without further requests
let sessionTimer = null

function reauthListen(fn) {
  if (reauthListener) {
    window.removeEventListener('focus', reauthListener)
  }

  reauthListener = fn
  window.addEventListener('focus', reauthListener)

  if (authChannel) {
    authChannel.onmessage = reauthListener
  }
}

function reauthSettle(ok) {
  if (reauthListener) {
    window.removeEventListener('focus', reauthListener)
    reauthListener = null

    if (authChannel) {
      authChannel.onmessage = null
    }
  }

  const waiting = reauthWaiting
  reauthWaiting = []
  waiting.forEach(({ resolve, reject }) => (ok ? resolve() : reject(new Error('Unauthenticated'))))
}

function csrf() {
  return fetch(urlcsrf, {
    method: 'GET',
    headers: { Accept: 'application/json' },
    credentials: 'same-origin'
  }).then((response) => {
    if (!response.ok) {
      throw new Error(`CSRF endpoint returned ${response.status}`)
    }
    return response.json()
  })
}

export const useUserStore = defineStore('user', {
  state: () => ({
    expired: false,
    me: null,
    session: 0,
    urlintended: null,
    saveTimer: null,
    tokenTimer: null
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

    async clear() {
      this.expired = false
      reauthSettle(false)

      clearTimeout(sessionTimer)
      sessionTimer = null

      clearTimeout(this.saveTimer)
      this.saveTimer = null

      clearTimeout(this.tokenTimer)
      this.tokenTimer = null

      useAppStore().urlproxy = urlproxy
      useClipboardStore().$reset()
      useChangeStore().$reset()
      useConfirmStore().close(false)
      const dirty = useDirtyStore()
      dirty.unregister()
      dirty.$reset()
      useDrawerStore().$reset()
      useSchemaStore().clear()
      useSideStore().$reset()
      useViewStack().$reset()
      clearUploadLink()

      await disconnect()
      await apolloClient.clearStore()
    },

    intended(url) {
      return url ? (this.urlintended = url) : this.urlintended
    },

    applyProxyToken() {
      clearTimeout(this.tokenTimer)
      this.tokenTimer = null

      if (!this.me?.token) return

      const app = useAppStore()
      app.urlproxy = urlproxy.replace('url=', 'token=' + encodeURIComponent(this.me.token) + '&url=')

      // The token's expiry is encoded as the first segment of "expires|uid|hmac";
      // refresh a minute before it lapses so proxied media keeps loading.
      let expires = 0
      try {
        expires = parseInt(atob(this.me.token).split('|')[0], 10) * 1000
      } catch {
        return
      }

      const delay = Math.min(Math.max(expires - Date.now() - 60000, 10000), 0x7fffffff)
      this.tokenTimer = setTimeout(() => this.refreshToken(), delay)
    },

    refreshToken() {
      if (!this.me) return

      apolloClient.query({
        query: FETCH_TOKEN,
        fetchPolicy: 'network-only'
      }).then((response) => {
        if (response.data?.me?.token) {
          this.me.token = response.data.me.token
          this.applyProxyToken()
        }
      }).catch((error) => {
        console.error('Failed to refresh proxy token', error)
      })
    },

    async isAuthenticated(force = false) {
      if (this.me !== null) {
        return !!this.me
      }

      await apolloClient.query({
        query: FETCH_ME,
        fetchPolicy: force ? 'network-only' : 'cache-first'
      }).then((response) => {
        if (response.errors) {
          throw response
        }

        this.me = response.data.me
          ? { ...response.data.me, permission: safeParse(response.data.me.permission), settings: safeParse(response.data.me.settings) }
          : false

        this.applyProxyToken()
      }).catch((error) => {
        console.error('Failed to fetch user data', error)
        this.me = false
      })

      return !!this.me
    },

    login(email, password) {
      return csrf().then(() => {
        return apolloClient.mutate({
          mutation: LOGIN,
          variables: {
            email: email,
            password: password
          }
        }).then((response) => {
          if (response.errors) {
            throw response.errors
          }

          if (!response.data.cmsLogin) {
            this.me = false
            return this.me
          }

          this.me = null
          // Invalidate kept-alive views while the login route is still active, before the next
          // authenticated route is rendered.
          this.session++
          return this.clear()
            .then(() => this.isAuthenticated(true))
            .then(() => {
              authChannel?.postMessage('login')
              return this.me
            })
        }).catch((error) => {
          this.me = false
          throw error
        })
      })
    },

    logout() {
      return apolloClient.mutate({
        mutation: LOGOUT
      }).then((response) => {
        if (response.errors) {
          throw response.errors
        }

        return response.data.cmsLogout || false
      }).finally(() => {
        this.me = null
        return this.clear()
      })
    },

    /**
     * Waits until the expired session is renewed by relogin()/resume() or given up by expire()
     *
     * The session can also be renewed in another tab, which is checked when this tab gets
     * the focus again or the other tab reports a login.
     *
     * @returns {Promise} Resolved after the user signed in again, rejected otherwise
     */
    reauth() {
      this.expired = true
      reauthListen(() => this.resume().catch(() => {}))

      return new Promise((resolve, reject) => reauthWaiting.push({ resolve, reject }))
    },

    /**
     * Signs in the current user again without resetting the open views and retries
     * the requests which failed because the session expired
     *
     * @param {String} password Password of the current user
     */
    relogin(password) {
      return csrf().then(() => {
        return apolloClient.mutate({
          mutation: LOGIN,
          variables: { email: this.me?.email, password },
          context: { relogin: true }
        })
      }).then((response) => {
        if (response.errors) {
          throw response.errors
        }

        return this.resume()
      }).then((resumed) => {
        // not resumed but no longer expired: another tab renewed the session meanwhile
        if (!resumed && this.expired) {
          throw new Error(gettext.$gettext('Login failed'))
        }

        authChannel?.postMessage('login')
      })
    },

    /**
     * Continues with the current session if the same user is signed in again
     *
     * @returns {Promise<Boolean>} TRUE if the waiting requests are retried, FALSE if still expired
     */
    resume() {
      if (!this.expired) {
        return Promise.resolve(false)
      }

      const email = this.me?.email

      return apolloClient.query({
        query: FETCH_ME,
        fetchPolicy: 'network-only',
        context: { relogin: true }
      }).then((response) => {
        const me = response.data?.me

        if (!this.expired || !me || me.email !== email) {
          return false
        }

        this.me.permission = safeParse(me.permission)
        this.me.token = me.token
        this.applyProxyToken()
        this.expired = false

        reauthSettle(true)
        resubscribe()

        return true
      })
    },

    /**
     * Checks if the session is still valid and asks the user to sign in again if not
     *
     * Opens the re-login dialog before the next save fails instead of after it.
     *
     * @returns {Promise<Boolean>} TRUE if the session is valid, FALSE if expired
     */
    check() {
      if (!this.me || this.expired) {
        return Promise.resolve(!this.expired)
      }

      const email = this.me.email

      return apolloClient.query({
        query: FETCH_ME,
        fetchPolicy: 'network-only',
        context: { relogin: true }
      }).then((response) => {
        return response.data?.me?.email === email
      }, (error) => {
        // offline or server errors don't mean the session expired
        return ![401, 419].includes(error?.networkError?.statusCode)
      }).then((valid) => {
        if (!valid && this.me && !this.expired) {
          this.reauth().catch(() => {})
        }

        return valid
      })
    },

    /**
     * Notes the session was used, which extends its lifetime on the server
     */
    touch() {
      if (!sessionlifetime) {
        return
      }

      clearTimeout(sessionTimer)
      // a bit later so the server has surely expired the session before it's checked
      sessionTimer = setTimeout(() => this.check(), sessionlifetime * 60000 + 5000)
    },

    /**
     * Gives up the expired session, rejects the waiting requests and resets the state
     */
    async expire() {
      this.me = false
      await this.clear()
    },

    async user() {
      if (await this.isAuthenticated()) {
        return this.me
      }

      return null
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

      apolloClient.mutate({
        mutation: SAVE_SETTINGS,
        variables: {
          settings: JSON.stringify(this.me.settings)
        }
      }).then((response) => {
        if (response.errors) {
          throw response.errors
        }
      }).catch((error) => {
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

/**
 * Admin panel extensions registered by composer plugins.
 *
 * The definitions arrive as JSON in the #app element's data-plugins attribute.
 * Each plugin's `component` is a URL to a Vite-built ES module with a default
 * export, loaded lazily via dynamic import() and wrapped so a load failure shows
 * a fallback instead of breaking the host. Components are markRaw so Pinia does
 * not make them reactive.
 */
const PluginError = markRaw({
  render: () => h('div', { class: 'pa-4 plugin-error' }, gettext.$gettext('Failed to load plugin'))
})

function pluginComponent(def) {
  return {
    ...def,
    component: markRaw(defineAsyncComponent({
      loader: () => Promise.all([
        import(/* @vite-ignore */ def.component),
        import('./plugin')
      ]).then(([mod, host]) => host.pluginUi(mod.default)),
      errorComponent: PluginError
    }))
  }
}

export const usePluginStore = defineStore('plugin', {
  state: () => {
    const panels = {}
    const subpanels = {}

    for (const [key, def] of Object.entries(plugins.panels || {})) {
      panels[key] = pluginComponent({ ...def, key })
    }

    for (const [host, group] of Object.entries(plugins.subpanels || {})) {
      subpanels[host] = {}
      for (const [key, def] of Object.entries(group)) {
        subpanels[host][key] = pluginComponent(def)
      }
    }

    return { panels, subpanels }
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
// message actions are kept outside of the queue items because the items are passed as props
// to the snackbars; only their id is stored in the item as harmless data attribute
const messageActions = new Map()
let messageId = 0

export const useMessageStore = defineStore('message', {
  state: () => ({
    queue: []
  }),

  actions: {
    /**
     * Adds a message to the queue
     *
     * @param {String} msg Message text
     * @param {String} type Message type (info, success, warning, error)
     * @param {Number|null} timeout Display duration in milliseconds
     * @param {Object|null} action Optional button as { label, handler }
     */
    add(msg, type = 'info', timeout = null, action = null) {
      if (this.queue.length >= 10) {
        console.warn('Message queue overflow, dropping message:', msg)
        return
      }

      const item = {
        text: msg,
        color: type,
        contentClass: 'text-pre-line',
        timeout: timeout || (type === 'error' ? 10000 : action ? 8000 : 3000)
      }

      if (action) {
        const id = ++messageId
        messageActions.set(id, action)
        item['data-action'] = id
        item.onDismiss = () => messageActions.delete(id)
      }

      this.queue.push(item)
    },

    action(id) {
      return messageActions.get(id) || null
    },

    run(id) {
      const action = messageActions.get(id)
      messageActions.delete(id)
      action?.handler()
    }
  }
})

/**
 * Available element schemas fetched from GraphQL
 */
let _generation = 0
let _loading = null

function restart() {
  _generation++
  _loading = null
}

export const useSchemaStore = defineStore('schema', {
  state: () => ({ themes: {}, content: {}, meta: {}, config: {} }),
  actions: {
    clear() {
      restart()
      this.$reset()
    },

    load(fresh = false) {
      if (_loading) return _loading instanceof Promise ? _loading : Promise.resolve()

      const generation = _generation

      _loading = apolloClient.query({
        query: FETCH_SCHEMAS,
        fetchPolicy: fresh ? 'network-only' : 'cache-first'
      }).then((result) => {
        if (generation !== _generation) return

        const content = {}, meta = {}, config = {}
        const parse = (v) => typeof v === 'string' ? safeParse(v) : sanitize(v || {})
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
        if (generation === _generation) _loading = null
        throw err
      }).then(() => generation === _generation ? _loading : null)

      return _loading
    },

    reload() {
      restart()
      return this.load(true)
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

export const useConfirmStore = defineStore('confirm', {
  state: () => ({
    hint: '',
    items: [],
    pendingResolve: null,
    show: false
  }),

  actions: {
    close(value) {
      const fn = this.pendingResolve
      this.pendingResolve = null
      this.show = false

      if (fn) fn(value)
    },

    purge(items, hint = '') {
      this.close(false)
      Object.assign(this, { items, hint, show: true })

      return new Promise((resolve) => {
        this.pendingResolve = resolve
      })
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
      this.dirty = false
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

      list.push(markRaw(item))
      this.changed = { ...this.changed, [type]: list }
    },

    patched(type, ids) {
      const list = this.changed[type]

      if (!list?.length || !ids?.length) return

      this.changed = { ...this.changed, [type]: list.filter((entry) => !ids.includes(entry.id)) }
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

      // Detail views mutate their item and replace nested state such as page content. Keep that
      // item reactive while leaving the surrounding props bag raw for callbacks and other data.
      const viewProps = props?.item
        ? { ...props, item: reactive(props.item) }
        : props || {}

      this.stack.push({
        component: markRaw(component),
        props: markRaw(viewProps)
      })
    }
  }
})
