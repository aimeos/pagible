/** @license MIT, https://opensource.org/license/mit */

import { reverb } from './config'

let echoPromise = null
let echoInstance = null
let idleTimer = null
let activeChannels = 0
let tenant = ''
let failures = 0 // number of consecutive failures to load the Echo libraries
let retryAt = 0 // time when loading the Echo libraries is tried again

const IDLE_TIMEOUT = 5 * 60 * 1000
const RETRY_DELAY = 30 * 1000
const RETRY_MAX = 10 * 60 * 1000

/**
 * Returns the time to wait before loading the Echo libraries again after the given number of failures
 */
export function backoff(count) {
  return count > 0 ? Math.min(RETRY_DELAY * 2 ** (count - 1), RETRY_MAX) : 0
}

// Active subscriber callbacks, notified with (null, RECONNECT) after the socket reconnects
// so views can re-sync state that changed while the connection was down.
const subscriptions = new Set()

// Subscriber counts per channel name, the channel is left when the last one unsubscribes
const channelRefs = new Map()

// Synthetic action delivered to every subscriber when the websocket reconnects.
export const RECONNECT = 'reconnect'

// Actions broadcast on the per-type channel, named '{type}.{action}'. Lists patch the row in
// place for PATCH_ACTIONS and reload for the structural ones. Events changing several items at
// once carry "ids" instead of "id" and patch every listed row (see listEcho). VARIANT_ACTIONS are
// only sent for several pages at once, e.g. after translating or ignoring language variants.
export const PATCH_ACTIONS = ['saved', 'published', 'restored', 'dropped']
export const VARIANT_ACTIONS = ['translated', 'source', 'ignored']
export const LIST_ACTIONS = [...PATCH_ACTIONS, 'added', 'moved', 'purged', ...VARIANT_ACTIONS]

function resetIdleTimer() {
  clearTimeout(idleTimer)
  idleTimer = null
  if (activeChannels === 0 && echoPromise) {
    idleTimer = setTimeout(() => {
      if (activeChannels === 0) {
        disconnect()
      }
    }, IDLE_TIMEOUT)
  }
}

/**
 * Returns if realtime updates are configured and the Echo libraries haven't failed to load recently.
 * After a failure, e.g. a chunk not available during a deployment, loading them is tried again later.
 */
export function realtime() {
  return Date.now() >= retryAt && !!reverb()
}

function getEcho() {
  const config = realtime() && reverb()
  if (!config) return Promise.resolve(null)

  clearTimeout(idleTimer)
  idleTimer = null

  if (!echoPromise) {
    const pending = Promise.all([import('laravel-echo'), import('pusher-js')])
      .then(([{ default: Echo }, pusherModule]) => {
        const Pusher = pusherModule.default || pusherModule
        tenant = config.tenant ?? ''
        const echo = new Echo({
          Pusher,
          broadcaster: 'reverb',
          key: config.key,
          wsHost: config.host,
          wsPort: config.port,
          wssPort: config.port,
          forceTLS: config.scheme === 'https',
          enabledTransports: ['ws', 'wss']
        })
        bindReconnect(echo)
        failures = 0

        // disconnect() closes the instance it waited for, which mustn't become the current one then
        if (echoPromise === pending) {
          echoInstance = echo
        }
        return echo
      })
      .catch((err) => {
        console.warn('Laravel Echo not available:', err.message)
        retryAt = Date.now() + backoff(++failures)

        if (echoPromise === pending) {
          echoPromise = null
          echoInstance = null
        }
        return null
      })

    echoPromise = pending
  }

  return echoPromise
}

// Re-sync subscribers after an automatic reconnect: pusher fires 'connected' again once the
// dropped socket comes back; the first 'connected' is the initial connect, so only later ones
// signal a reconnect during which events may have been missed.
export function bindReconnect(echo, reconnect = resync) {
  let connected = false
  const conn = echo.connector?.pusher?.connection
  conn?.bind?.('connected', () => {
    if (connected) { reconnect() }
    connected = true
  })
}

export async function disconnect() {
  clearTimeout(idleTimer)
  idleTimer = null
  activeChannels = 0
  subscriptions.clear()
  channelRefs.clear()
  const pending = echoPromise
  echoPromise = null
  echoInstance = null
  const echo = await pending
  if (echo) {
    try { echo.connector?.pusher?.disconnect() } catch { /* the connection may already be closed */ }
    echo.disconnect()
  }
}

/**
 * Subscribes again to the private channels whose authorization failed, e.g. after a reconnect
 * while the session was expired, and lets the subscribers reload the events they missed.
 */
export function resubscribe(echo = echoInstance) {
  const pusher = echo?.connector?.pusher
  const failed = (pusher?.allChannels?.() || []).filter((channel) => !channel.subscribed)

  failed.forEach((channel) => pusher.subscribe(channel.name))

  if (failed.length) {
    resync()
  }
}

/**
 * Current socket id of the open websocket connection, or '' when not connected.
 * Sent as the X-Socket-ID header so the server can exclude this tab via toOthers().
 */
export function socketId() {
  return echoInstance?.socketId?.() || ''
}

/**
 * Subscribes to one or several per-type channels and returns a disposer that removes the
 * listeners, also when called before the subscription has been established.
 */
export function setupEcho(type, onEvent, actions = LIST_ACTIONS, connect = subscribe) {
  let release = null
  let disposed = false

  const promise = Array.isArray(type)
    ? Promise.allSettled(type.map((name) => connect(name, (event, action) => onEvent(event, action, name), actions)))
        .then((results) => {
          const cleanups = results.filter((result) => result.status === 'fulfilled').map((result) => result.value)
          const failed = results.find((result) => result.status === 'rejected')

          if (failed) {
            cleanups.forEach((cleanup) => cleanup?.())
            throw failed.reason
          }

          return () => cleanups.forEach((cleanup) => cleanup?.())
        })
    : connect(type, onEvent, actions)

  promise
    .then((fn) => {
      if (disposed) fn?.()
      else release = fn
    })
    .catch((error) => console.warn('Echo subscription failed:', error))

  return () => {
    disposed = true
    release?.()
    release = null
  }
}

export async function subscribe(contentType, callback, actions = LIST_ACTIONS) {
  const echo = await getEcho()
  // the connection may have been closed by disconnect() while the libraries were loading
  if (!echo || echo !== echoInstance) return null

  activeChannels++
  const name = channelName(contentType)
  const channel = echo.private(name)
  const leave = joinChannel(echo, name)
  // track each handler so cleanup removes only this subscriber's listeners: the per-type
  // channel is shared by the list/tree and any open detail view of the same type, so it's
  // only left when the last subscriber unsubscribes
  const handlers = actions.map((action) => {
    const event = `.${contentType}.${action}`
    const handler = (data) => callback(data, action)
    channel.listen(event, handler)
    return [event, handler]
  })
  subscriptions.add(callback)

  let released = false

  return () => {
    if (released) return
    released = true

    handlers.forEach(([event, handler]) => channel.stopListening(event, handler))
    subscriptions.delete(callback)
    leave()

    // disconnect() already reset the counter of a closed connection, a later one isn't affected
    if (echo === echoInstance) {
      activeChannels = Math.max(0, activeChannels - 1)
      resetIdleTimer()
    }
  }
}

/**
 * Counts the subscribers of the channel and returns a function which releases one of them.
 * The channel is left when the last subscriber is released. Exposed for testing.
 */
export function joinChannel(echo, name, refs = channelRefs) {
  const entry = refs.get(name) || { count: 0 }
  let released = false

  entry.count++
  refs.set(name, entry)

  return () => {
    // the counts are cleared on disconnect, so a closed connection isn't touched again
    if (released || refs.get(name) !== entry) return
    released = true

    if (--entry.count <= 0) {
      refs.delete(name)
      echo.leave(name)
    }
  }
}

/**
 * Builds the private per-type channel name for a content type (list/tree and detail views share
 * it). The tenant segment is omitted when there is no active tenant (single-tenant setup),
 * matching the server's Channel helper - an empty segment would not match the {tenant} wildcard.
 */
export function channelName(contentType, t = tenant) {
  return t ? `cms.${t}.${contentType}` : `cms.${contentType}`
}

/**
 * Notifies every active subscriber that the socket reconnected and they may have missed events,
 * by invoking each callback with (null, RECONNECT). Exposed for testing.
 */
export function resync(subs = subscriptions) {
  subs.forEach((callback) => {
    // isolate subscribers so one that throws can't skip the rest
    try {
      callback(null, RECONNECT)
    } catch (err) {
      console.warn('Echo resync subscriber failed:', err)
    }
  })
}
