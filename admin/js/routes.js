/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

import { createRouter, createWebHistory } from 'vue-router'
import { useDirtyStore, useUserStore, useMessageStore } from './stores'
import { urladmin } from './config'
import gettext from './i18n'

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
      path: '/elements',
      name: 'element:view',
      component: () => import('./views/ElementList.vue'),
      meta: {
        auth: true,
        title: gettext.$gettext('Shared elements')
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
  } else if (to.name !== 'login' && !user.can(to.name)) {
    message.add(
      gettext.$gettext('You do not have permission to access %{path}', { path: to.fullPath }),
      'error'
    )
    return false
  }
})

router.afterEach((to) => {
  document.title = (to.meta.title || to.path) + ' — PagibleAI CMS'
})

export default router
