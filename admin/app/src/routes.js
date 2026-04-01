/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore, useMessageStore } from './stores'
import gettext from './i18n'


const router = createRouter({
  history: createWebHistory(document.querySelector('#app')?.dataset?.urladmin || ''),
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

router.beforeEach(async (to, from, next) => {
  const user = useUserStore()
  const message = useMessageStore()
  const authenticated = await user.isAuthenticated()

  if (to.matched.some((record) => record.meta.auth) && !authenticated) {
    user.intended(to.fullPath)
    next({ name: 'login' })
  } else if (to.name !== 'login' && !user.can(to.name)) {
    message.add(
      gettext.$gettext('You do not have permission to access %{path}', { path: to.fullPath }),
      'error'
    )
    return next(false)
  } else {
    next()
  }
})

router.afterEach((to) => {
  document.title = (to.meta.title || to.path) + ' — PagibleAI CMS'
})


export default router
