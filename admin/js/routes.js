/**
 * @license MIT, https://opensource.org/license/mit
 */

import { reactive } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { useClipboardStore, useDirtyStore, useUserStore, useMessageStore, useViewStack } from './stores'
import { apolloClient } from './graphql'
import { urladmin } from './config'
import gettext from './i18n'

function itemProps() {
  let item
  return route => {
    if (!item || item.id !== route.params.id) {
      item = reactive({ id: route.params.id })
    }
    return { item }
  }
}

const router = createRouter({
  history: createWebHistory(urladmin),
  routes: [
    {
      path: '/',
      name: 'login',
      component: () => import('./views/Login.vue'),
      meta: {
        title: gettext.$gettext('Login')
      }
    },
    {
      path: '/pages',
      name: 'page:view',
      component: () => import('./views/PageList.vue'),
      meta: {
        auth: true,
        title: gettext.$gettext('Pages')
      }
    },
    {
      path: '/pages/:id',
      name: 'page:detail',
      component: () => import('./views/PageDetail.vue'),
      props: itemProps(),
      meta: {
        auth: true,
        permission: 'page:view',
        title: gettext.$gettext('Page')
      }
    },
    {
      path: '/elements',
      name: 'element:view',
      component: () => import('./views/ElementList.vue'),
      meta: {
        auth: true,
        title: gettext.$gettext('Shared elements')
      }
    },
    {
      path: '/elements/:id',
      name: 'element:detail',
      component: () => import('./views/ElementDetail.vue'),
      props: itemProps(),
      meta: {
        auth: true,
        permission: 'element:view',
        title: gettext.$gettext('Element')
      }
    },
    {
      path: '/files',
      name: 'file:view',
      component: () => import('./views/FileList.vue'),
      meta: {
        auth: true,
        title: gettext.$gettext('Files')
      }
    },
    {
      path: '/files/:id',
      name: 'file:detail',
      component: () => import('./views/FileDetail.vue'),
      props: itemProps(),
      meta: {
        auth: true,
        permission: 'file:view',
        title: gettext.$gettext('File')
      }
    }
  ]
})

router.beforeEach(async (to) => {
  const dirtyStore = useDirtyStore()
  const user = useUserStore()
  const message = useMessageStore()

  if (dirtyStore.dirty) {
    const allowed = await dirtyStore.confirm()
    if (!allowed) return false
    return
  }

  const authenticated = await user.isAuthenticated()

  if (to.matched.some((record) => record.meta.auth) && !authenticated) {
    user.intended(to.fullPath)
    return { name: 'login' }
  }

  const permission = to.meta.permission || to.name
  if (to.name !== 'login' && !user.can(permission)) {
    message.add(
      gettext.$gettext('You do not have permission to access %{path}', { path: to.fullPath }),
      'error'
    )
    return false
  }
})

router.afterEach((to, from) => {
  document.title = (to.meta.title || to.path) + ' — PagibleAI CMS'

  useViewStack().stack = []

  const toSection = to.name?.split(':')[0]
  const fromSection = from.name?.split(':')[0]

  if (toSection !== fromSection) {
    useClipboardStore().clear()
    apolloClient.cache.evict({ fieldName: 'pages' })
    apolloClient.cache.evict({ fieldName: 'elements' })
    apolloClient.cache.evict({ fieldName: 'files' })
    apolloClient.cache.gc()
  }
})

export default router
