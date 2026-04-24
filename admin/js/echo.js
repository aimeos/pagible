/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

let echoPromise = null

function getEcho() {
  const node = document.querySelector('#app')
  if (!node?.dataset?.reverb) return Promise.resolve(null)

  if (!echoPromise) {
    echoPromise = Promise.all([import('laravel-echo'), import('pusher-js')])
      .then(([{ default: Echo }, { default: Pusher }]) => {
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

export async function subscribe(contentType, contentId, callback) {
  const echo = await getEcho()
  if (!echo || !contentId) return null

  const channelName = `cms.${contentType}.${contentId}`
  const channel = echo.private(channelName)
  channel.listen('.content.saved', callback)

  return () => {
    echo.leave(channelName)
  }
}
