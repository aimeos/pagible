/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

import { markRaw } from 'vue'
import { debounce, safeParse, sanitize } from './utils'

let echoPromise = null
let echoInstance = null
let idleTimer = null
let activeChannels = 0
let wasConnected = false
let tenant = ''

const IDLE_TIMEOUT = 5 * 60 * 1000

// Active subscriber callbacks, notified with (null, RECONNECT) after the socket reconnects
// so views can re-sync state that changed while the connection was down.
const subscriptions = new Set()

// Synthetic action delivered to every subscriber when the websocket reconnects.
export const RECONNECT = 'reconnect'

// Actions broadcast on the per-type channel, one per operation, named '{type}.{action}'.
// List/tree views patch the row in place for PATCH_ACTIONS and reload for the rest
// ('added' / 'moved' / 'purged'); an open detail view reloads when its own item is 'saved'.
export const PATCH_ACTIONS = ['saved', 'published', 'restored', 'dropped']
export const LIST_ACTIONS = [...PATCH_ACTIONS, 'added', 'moved', 'purged']

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

function getEcho() {
  const node = document.querySelector('#app')
  if (!node?.dataset?.reverb) return Promise.resolve(null)

  clearTimeout(idleTimer)
  idleTimer = null

  if (!echoPromise) {
    echoPromise = Promise.all([import('laravel-echo'), import('pusher-js')])
      .then(([{ default: Echo }, pusherModule]) => {
        const Pusher = pusherModule.default || pusherModule
        const config = safeParse(node.dataset.reverb)
        tenant = config.tenant ?? ''
        echoInstance = new Echo({
          Pusher,
          broadcaster: 'reverb',
          key: config.key,
          wsHost: config.host,
          wsPort: config.port,
          wssPort: config.port,
          forceTLS: config.scheme === 'https',
          enabledTransports: ['ws', 'wss']
        })
        bindReconnect(echoInstance)
        return echoInstance
      })
      .catch((err) => {
        console.warn('Laravel Echo not available:', err.message)
        echoPromise = null
        echoInstance = null
        return null
      })
  }

  return echoPromise
}

// Re-sync subscribers after an automatic reconnect: pusher fires 'connected' again once the
// dropped socket comes back; the first 'connected' is the initial connect, so only later ones
// signal a reconnect during which events may have been missed.
function bindReconnect(echo) {
  const conn = echo.connector?.pusher?.connection
  conn?.bind?.('connected', () => {
    if (wasConnected) { resync() }
    wasConnected = true
  })
}

export async function disconnect() {
  clearTimeout(idleTimer)
  idleTimer = null
  activeChannels = 0
  wasConnected = false
  subscriptions.clear()
  const pending = echoPromise
  echoPromise = null
  echoInstance = null
  const echo = await pending
  if (echo) {
    try { echo.connector?.pusher?.disconnect() } catch {}
    echo.disconnect()
  }
}

/**
 * Current socket id of the open websocket connection, or '' when not connected.
 * Sent as the X-Socket-ID header so the server can exclude this tab via toOthers().
 */
export function socketId() {
  return echoInstance?.socketId?.() || ''
}

export function setupEcho(vm, type, onEvent, actions = LIST_ACTIONS) {
  vm.echoPromise = markRaw(subscribe(type, onEvent, actions).then((cleanup) => {
    if (vm.destroyed) { cleanup?.() } else { vm.echoCleanup = cleanup }
  }))
}

export function cleanEcho(vm) {
  vm.reloadDebounced?.cancel()
  vm.reloadDebounced = null

  if (vm.echoCleanup) {
    vm.echoCleanup()
  } else if (vm.echoPromise) {
    vm.echoPromise.then((cleanup) => cleanup?.())
  }
  vm.echoCleanup = null
  vm.echoPromise = null
}

export async function subscribe(contentType, callback, actions = LIST_ACTIONS) {
  const echo = await getEcho()
  if (!echo) return null

  activeChannels++
  const name = channelName(contentType)
  const channel = echo.private(name)
  // track each handler so cleanup removes only this subscriber's listeners: the per-type
  // channel is shared by the list/tree and any open detail view of the same type, so leaving
  // the whole channel would tear out the other's listeners
  const handlers = actions.map((action) => {
    const event = `.${contentType}.${action}`
    const handler = (data) => callback(data, action)
    channel.listen(event, handler)
    return [event, handler]
  })
  subscriptions.add(callback)

  return () => {
    handlers.forEach(([event, handler]) => channel.stopListening(event, handler))
    subscriptions.delete(callback)
    activeChannels = Math.max(0, activeChannels - 1)
    resetIdleTimer()
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

/**
 * Builds the in-place row update a list/tree view applies from a patch event.
 */
export function eventPatch(event) {
  return {
    ...sanitize(event.data),
    id: event.id,
    published: event.published,
    deleted_at: event.deleted_at,
    publish_at: event.publish_at,
    updated_at: event.updated_at,
    editor: event.editor,
    latest_id: event.latest_id
  }
}

/**
 * Routes a per-type channel event for a list/tree view: a reconnect or a structural change flags
 * the list for reload, a patch action updates the row in place. The originating tab is already
 * excluded server-side via toOthers(), so other tabs (even of the same user) still update.
 * Reaches into vm.{outdated,patch}, the shared contract of the list components.
 */
export function listEcho(vm, event, name) {
  if (name === RECONNECT) { vm.outdated = true; return }
  if (!PATCH_ACTIONS.includes(name)) { vm.outdated = true; return }
  vm.patch(eventPatch(event))
}

/**
 * Subscribes a detail view to its per-type channel and reloads (debounced) when its own item is
 * saved elsewhere, or after a reconnect that may have missed a save - guarded by enabled() (e.g.
 * not dirty and permitted). cleanEcho() cancels the pending reload on unmount.
 */
export function setupReload(vm, type, id, reload, enabled) {
  vm.reloadDebounced = debounce(reload, 300)
  // a detail view reloads its own item on any patch action (saved/published/restored/dropped,
  // mirroring what the list patches) plus the reconnect resync
  setupEcho(vm, type, (event, name) => {
    if ((name === RECONNECT || event?.id === id) && enabled()) {
      vm.reloadDebounced()
    }
  }, PATCH_ACTIONS)
}
