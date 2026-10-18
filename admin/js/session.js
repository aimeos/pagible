/**
 * @license MIT, https://opensource.org/license/mit
 */

import router from './routes'
import { onSession } from './graphql'
import { useUserStore } from './stores'

// Connects the GraphQL client to the session of the signed-in user
onSession({
  active: () => !!useUserStore().me,
  reauth: () => useUserStore().reauth(),
  touch: () => useUserStore().touch(),
  ended: () => {
    useUserStore().me = false
    router.push({ name: 'login' })
  }
})
