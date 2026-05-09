/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

import { markRaw } from 'vue'

let echoPromise = null
let idleTimer = null
let activeChannels = 0

const IDLE_TIMEOUT = 5 * 60 * 1000

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
        const config = JSON.parse(node.dataset.reverb)
        return new Echo({
          Pusher,
          broadcaster: 'reverb',
          key: config.key,
          wsHost: config.host,
          wsPort: config.port,
          wssPort: config.port,
          forceTLS: config.scheme === 'https',
          enabledTransports: ['ws', 'wss']
        })
      })
      .catch((err) => {
        console.warn('Laravel Echo not available:', err.message)
        echoPromise = null
        return null
      })
  }

  return echoPromise
}

export async function disconnect() {
  clearTimeout(idleTimer)
  idleTimer = null
  activeChannels = 0
  const pending = echoPromise
  echoPromise = null
  const echo = await pending
  if (echo) {
    try { echo.connector?.pusher?.disconnect() } catch {}
    echo.disconnect()
  }
}

export function setupEcho(vm, type, id, onEvent) {
  vm.echoPromise = markRaw(subscribe(type, id, onEvent).then((cleanup) => {
    if (vm.destroyed) { cleanup?.() } else { vm.echoCleanup = cleanup }
  }))
}

export function cleanEcho(vm) {
  if (vm.echoCleanup) {
    vm.echoCleanup()
  } else if (vm.echoPromise) {
    vm.echoPromise.then((cleanup) => cleanup?.())
  }
  vm.echoCleanup = null
  vm.echoPromise = null
}

export async function subscribe(contentType, contentId, callback) {
  const echo = await getEcho()
  if (!echo || !contentId) return null

  activeChannels++
  const channelName = `cms.${contentType}.${contentId}`
  const channel = echo.private(channelName)
  channel.listen('.content.saved', callback)

  return () => {
    echo.leave(channelName)
    activeChannels = Math.max(0, activeChannels - 1)
    resetIdleTimer()
  }
}
