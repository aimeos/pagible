/**
 * @license MIT, https://opensource.org/license/mit
 */

import { watch } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { useClipboardStore, useDirtyStore, useSessionStore, useUserStore, useMessageStore, usePluginStore, useViewStack } from './stores'
import { urladmin } from './config'
import { connectionError, unreachable } from './graphql'
import gettext, { pluginLabel } from './i18n'

function itemProps() {
  return route => ({ initial: { id: route.params.id } })
}

const router = createRouter({
  history: createWebHistory(urladmin),
  routes: [
    {
      path: '/',
      name: 'login',
      component: () => import('./views/Login.vue'),
      meta: {
        title: 'Login'
      }
    },
    {
      path: '/pages',
      name: 'page:view',
      component: () => import('./views/PageList.vue'),
      meta: {
        auth: true,
        title: 'Pages'
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
        title: 'Page'
      }
    },
    {
      path: '/files',
      name: 'file:view',
      component: () => import('./views/FileList.vue'),
      meta: {
        auth: true,
        title: 'Media'
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
        title: 'File'
      }
    },
    {
      path: '/elements',
      name: 'element:view',
      component: () => import('./views/ElementList.vue'),
      meta: {
        auth: true,
        title: 'Shared elements'
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
        title: 'Element'
      }
    },
    {
      path: '/access',
      name: 'access:view',
      component: () => import('./views/AccessList.vue'),
      meta: {
        auth: true,
        permission: ['access:view', 'user:access', 'user:permission', 'user:create'],
        title: 'Users'
      }
    }
  ]
})

// returns the navigation result if the route can't be accessed, undefined otherwise
async function access(to, from) {
  let failed = null
  const authenticated = await useSessionStore().isAuthenticated().catch((error) => {
    failed = error
    return false
  })

  // being offline or a server error doesn't mean signed out, the user stays on the current page if there's one
  if (failed && from?.matched.length) {
    useMessageStore().add(
      unreachable(failed) ? connectionError() : gettext.$gettext('Error checking the session, please try again'),
      'error'
    )
    return false
  }

  if (!authenticated) {
    useSessionStore().intended(to.fullPath)
    return { name: 'login' }
  }

  const permission = to.meta.permission || to.name
  if (!useUserStore().can(permission)) {
    useMessageStore().add(
      gettext.$gettext('You do not have permission to access %{path}', { path: to.fullPath }),
      'error'
    )
    return false
  }
}

export async function guard(to, from) {
  // checked first, discarded changes would be lost even if the navigation is cancelled afterwards
  if (to.matched.some((record) => record.meta.auth)) {
    const result = await access(to, from)

    if (result !== undefined) {
      return result
    }
  }

  const dirty = useDirtyStore()

  // the redirect to the login page asks for the unsaved changes in its own navigation
  if (dirty.dirty && !(await dirty.confirm())) {
    return false
  }
}

router.beforeEach(guard)

function title(route) {
  const value = route.meta.title
    ? pluginLabel({ label: route.meta.title, i18n: route.meta.i18n })
    : route.path
  document.title = value + ' — PagibleAI CMS'
}

router.afterEach((to, from) => {
  title(to)

  useViewStack().stack = []

  const toSection = to.name?.split(':')[0]
  const fromSection = from.name?.split(':')[0]

  if (toSection !== fromSection) {
    useClipboardStore().$reset()
  }
})

/**
 * Registers a route for each plugin navigation panel.
 *
 * Called from main.js after the Pinia plugin is installed, so usePluginStore()
 * has an active Pinia. Each route carries a name and meta.permission so the
 * global beforeEach guard above enforces access just like the built-in routes.
 */
export function addPluginRoutes() {
  const plugin = usePluginStore()

  for (const [key, panel] of Object.entries(plugin.panels)) {
    router.addRoute({
      path: '/' + key,
      name: key,
      component: () => import('./views/PluginPanel.vue'),
      props: { panel },
      meta: {
        auth: true,
        permission: panel.permission,
        title: panel.label,
        i18n: panel.i18n
      }
    })
  }
}

watch(
  () => gettext.translations,
  () => title(router.currentRoute.value)
)

export default router
