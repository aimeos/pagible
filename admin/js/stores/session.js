/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { defineStore } from 'pinia'
import { AUTH_STATUS, apolloClient, clearUploadLink, unreachable } from '../graphql'
import { disconnect, resubscribe } from '../echo'
import gettext from '../i18n'
import { safeParse } from '../json'
import { urlproxy, urlcsrf, sessionlifetime } from '../config'
import { useAppStore } from './app'
import { useChangeStore } from './change'
import { useClipboardStore } from './clipboard'
import { useConfirmStore } from './confirm'
import { useDirtyStore } from './dirty'
import { useMessageStore } from './message'
import { useDrawerStore } from './drawer'
import { useSchemaStore } from './schema'
import { useSettingStore } from './setting'
import { useSideStore } from './side'
import { useTranslationStore } from './translation'
import { useUserStore } from './user'
import { useViewStack } from './viewstack'

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

// Tells the other admin tabs that the user signed in so they can continue with the new session
const authChannel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('cms-auth') : null

// Pending requests waiting for the user to sign in again after the session expired
let reauthWaiting = []
let reauthListener = null
// Checks the session after it would have expired without further requests
let sessionTimer = null
// Refreshes the media proxy token before it expires
let tokenTimer = null
// Delay until the next try after refreshing the proxy token failed
let tokenRetry = 30000

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

function fetchMe(relogin = true, force = true) {
  return apolloClient.query({
    query: FETCH_ME,
    fetchPolicy: force ? 'network-only' : 'cache-first',
    context: { relogin }
  })
}

// Converts the JSON encoded permissions and settings of the fetched user
function parseMe(me) {
  return { ...me, permission: safeParse(me.permission), settings: safeParse(me.settings) }
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

// Resets the data of the signed-in user kept by the other stores and the GraphQL client
async function resetStores() {
  useAppStore().urlproxy = urlproxy
  useClipboardStore().$reset()
  useChangeStore().$reset()
  useConfirmStore().close(false)
  const dirty = useDirtyStore()
  dirty.unregister()
  dirty.$reset()
  useDrawerStore().$reset()
  useMessageStore().clear()
  useSchemaStore().clear()
  useSettingStore().clear()
  useSideStore().$reset()
  // stops polling the translation batches of the previous user
  useTranslationStore().clear()
  useViewStack().$reset()
  clearUploadLink()

  await disconnect()
  await apolloClient.clearStore()
}

/**
 * Session of the signed-in user: login, logout, re-authentication after the session expired and the media proxy token
 */
export const useSessionStore = defineStore('session', {
  state: () => ({
    expired: false,
    // incremented on each login to invalidate the kept-alive views of the previous user
    id: 0,
    urlintended: null
  }),

  actions: {
    async clear() {
      this.expired = false
      reauthSettle(false)

      clearTimeout(sessionTimer)
      sessionTimer = null

      clearTimeout(tokenTimer)
      tokenTimer = null
      tokenRetry = 30000

      await resetStores()
    },

    intended(url) {
      return url ? (this.urlintended = url) : this.urlintended
    },

    applyProxyToken() {
      const user = useUserStore()

      clearTimeout(tokenTimer)
      tokenTimer = null

      if (!user.me?.token) return

      const app = useAppStore()
      app.urlproxy = urlproxy.replace('url=', 'token=' + encodeURIComponent(user.me.token) + '&url=')

      // The token's expiry is encoded as the first segment of "expires|uid|hmac";
      // refresh a minute before it lapses so proxied media keeps loading.
      let expires = 0
      try {
        expires = parseInt(atob(user.me.token).split('|')[0], 10) * 1000
      } catch {
        return
      }

      // a malformed expiry would schedule the refresh immediately and loop
      if (!Number.isFinite(expires)) {
        return
      }

      const delay = Math.min(Math.max(expires - Date.now() - 60000, 10000), 0x7fffffff)
      tokenTimer = setTimeout(() => this.refreshToken(), delay)
    },

    refreshToken() {
      const user = useUserStore()

      if (!user.me) return

      fetchMe(false).then((response) => {
        tokenRetry = 30000

        // the user may have signed out meanwhile
        if (user.me && response.data?.me?.token) {
          user.me.token = response.data.me.token
          this.applyProxyToken()
        }
      }).catch((error) => {
        console.error('Failed to refresh proxy token', error)

        // the media can't be loaded anymore after the token expired, an expired session
        // gets a new token when it's resumed
        if (user.me && !AUTH_STATUS.includes(error?.networkError?.statusCode)) {
          clearTimeout(tokenTimer)
          tokenTimer = setTimeout(() => this.refreshToken(), tokenRetry)
          tokenRetry = Math.min(tokenRetry * 2, 300000)
        }
      })
    },

    async isAuthenticated(force = false) {
      const user = useUserStore()

      if (user.me !== null) {
        return !!user.me
      }

      await fetchMe(false, force).then((response) => {
        user.me = response.data.me ? parseMe(response.data.me) : false

        this.applyProxyToken()
      }).catch((error) => {
        console.error('Failed to fetch user data', error)

        // only a rejected session means signed out, e.g. being offline is checked again next time
        if (!AUTH_STATUS.includes(error?.networkError?.statusCode)) {
          throw error
        }

        user.me = false
      })

      return !!user.me
    },

    login(email, password) {
      const user = useUserStore()

      return csrf().then(() => {
        return apolloClient.mutate({
          mutation: LOGIN,
          variables: {
            email: email,
            password: password
          }
        }).catch((error) => {
          // the state stays unknown if the server wasn't reachable and is fetched again next time
          if (!unreachable(error)) {
            user.me = false
          }
          throw error
        }).then((response) => {
          if (!response.data.cmsLogin) {
            user.me = false
            return user.me
          }

          user.me = null
          // Invalidate kept-alive views while the login route is still active, before the next
          // authenticated route is rendered.
          this.id++
          return this.clear()
            .then(() => this.isAuthenticated(true))
            .then(() => {
              authChannel?.postMessage('login')
              return user.me
            })
        })
      })
    },

    logout() {
      // save the pending settings while the session is still valid
      useSettingStore().flush()

      return apolloClient.mutate({
        mutation: LOGOUT
      }).then((response) => {
        return response.data.cmsLogout || false
      }).finally(() => {
        useUserStore().me = null
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
          variables: { email: useUserStore().me?.email, password },
          context: { relogin: true }
        })
      }).then(() => this.resume()).then((resumed) => {
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
      const user = useUserStore()

      if (!this.expired) {
        return Promise.resolve(false)
      }

      const email = user.me?.email

      return fetchMe().then((response) => {
        const me = response.data?.me

        if (!this.expired || !me || me.email !== email) {
          return false
        }

        user.me.permission = parseMe(me).permission
        user.me.token = me.token
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
      const user = useUserStore()

      if (!user.me || this.expired) {
        return Promise.resolve(!this.expired)
      }

      const email = user.me.email

      return fetchMe().then((response) => {
        return response.data?.me?.email === email
      }, (error) => {
        // offline or server errors don't mean the session expired
        return !AUTH_STATUS.includes(error?.networkError?.statusCode)
      }).then((valid) => {
        if (!valid && user.me && !this.expired) {
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
      useUserStore().me = false
      await this.clear()
    },

    async user() {
      if (await this.isAuthenticated()) {
        return useUserStore().me
      }

      return null
    }
  }
})
