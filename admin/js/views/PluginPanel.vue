/** @license MIT, https://opensource.org/license/mit */

<script>
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

  beforeUnmount() {
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
      this.aside = { content, defaults, filter: this.settings.filter('plugin:' + this.panel.key, defaults) }
      return this.aside.filter
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
