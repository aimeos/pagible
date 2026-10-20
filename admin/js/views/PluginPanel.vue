/** @license MIT, https://opensource.org/license/mit */

<script>
import { effectScope } from 'vue'
import AsideList from '../components/AsideList.vue'
import ListAppBar from '../components/ListAppBar.vue'
import Navigation from '../components/Navigation.vue'
import { pluginLabel } from '../i18n'
import { useSettingStore } from '../stores'

export default {
  name: 'PluginPanel',

  components: {
    AsideList,
    ListAppBar,
    Navigation
  },

  props: {
    panel: {
      type: Object,
      required: true
    }
  },

  data: () => ({
    aside: null
  }),

  provide() {
    return { pluginAside: this.register }
  },

  setup() {
    return { settings: useSettingStore() }
  },

  created() {
    // owns the filter watcher, plugins may register their sidebar after they are set up
    this.filterScope = null
  },

  beforeUnmount() {
    this.filterScope?.stop()
    this.settings.flush()
  },

  methods: {
    /**
     * Shows the filter sidebar of the plugin and returns its reactive filter
     *
     * @param {Function} content Returns the filter groups like in the core list views
     * @param {Object} defaults Filter values used initially and on reset
     */
    register(content, defaults) {
      // detached, so the watcher isn't bound to the calling component and stops with the panel
      this.filterScope?.stop()
      this.filterScope = effectScope(true)

      const filter = this.filterScope.run(() => this.settings.filter('plugin:' + this.panel.key, defaults))

      this.aside = { content, defaults, filter }
      return filter
    },

    label(panel) {
      return pluginLabel(panel, this)
    }
  }
}
</script>

<template>
  <ListAppBar :title="label(panel)" :aside="!!aside" />

  <Navigation />

  <v-main class="plugin-panel" :aria-label="label(panel)">
    <component :is="panel.component" :panel="panel" />
  </v-main>

  <AsideList
    v-if="aside"
    :filter="aside.filter"
    @update:filter="Object.assign(aside.filter, $event)"
    :defaults="aside.defaults"
    :content="aside.content()"
  />
</template>

<style scoped>
.plugin-panel {
  overflow-y: auto;
}
</style>
