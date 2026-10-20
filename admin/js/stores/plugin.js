/**
 * @license MIT, https://opensource.org/license/mit
 */

import { defineAsyncComponent, h, markRaw } from 'vue'
import { defineStore } from 'pinia'
import gettext from '../i18n'
import { plugins } from '../config'

/**
 * Admin panel extensions registered by composer plugins.
 *
 * The definitions arrive as JSON in the #app element's data-plugins attribute.
 * Each plugin's `component` is a URL to a Vite-built ES module with a default
 * export, loaded lazily via dynamic import() and wrapped so a load failure shows
 * a fallback instead of breaking the host. Components are markRaw so Pinia does
 * not make them reactive.
 */
const PluginError = markRaw({
  render: () => h('div', { class: 'pa-4 plugin-error' }, gettext.$gettext('Failed to load plugin'))
})

export function pluginComponent(def) {
  return {
    ...def,
    component: markRaw(defineAsyncComponent({
      loader: () => Promise.all([
        import(/* @vite-ignore */ def.component),
        import('../plugin')
      ]).then(([mod, host]) => host.pluginUi(mod.default)),
      errorComponent: PluginError
    }))
  }
}

export const usePluginStore = defineStore('plugin', {
  state: () => {
    const panels = {}
    const subpanels = {}

    for (const [key, def] of Object.entries(plugins.panels || {})) {
      panels[key] = pluginComponent({ ...def, key })
    }

    for (const [host, group] of Object.entries(plugins.subpanels || {})) {
      subpanels[host] = {}
      for (const [key, def] of Object.entries(group)) {
        subpanels[host][key] = pluginComponent(def)
      }
    }

    return { panels, subpanels }
  }
})
