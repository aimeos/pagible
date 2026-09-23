/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiChevronLeft, mdiChevronRight, mdiClose, mdiMenu } from '@mdi/js'
import AsideList from '../components/AsideList.vue'
import Navigation from '../components/Navigation.vue'
import User from '../components/User.vue'
import { pluginLabel } from '../i18n'
import { useDrawerStore, useUserStore } from '../stores'

export default {
  name: 'PluginPanel',

  components: {
    AsideList,
    Navigation,
    User
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
    const drawer = useDrawerStore()
    const user = useUserStore()

    return { drawer, user, mdiChevronLeft, mdiChevronRight, mdiClose, mdiMenu }
  },

  beforeUnmount() {
    this.user.flush()
  },

  methods: {
    /**
     * Shows the filter sidebar of the plugin and returns its reactive filter
     *
     * @param {Function} content Returns the filter groups like in the core list views
     * @param {Object} defaults Filter values used initially and on reset
     */
    register(content, defaults) {
      this.aside = { content, defaults, filter: this.user.filter('plugin:' + this.panel.key, defaults) }
      return this.aside.filter
    },

    label(panel) {
      return pluginLabel(panel, this)
    }
  }
}
</script>

<template>
  <v-app-bar :elevation="0" density="compact" role="sectionheader" :aria-label="$gettext('Menu')">
    <template #prepend>
      <v-btn
        @click="drawer.toggle('nav')"
        :title="drawer.nav ? $gettext('Close navigation') : $gettext('Open navigation')"
        :icon="drawer.nav ? mdiClose : mdiMenu"
      />
    </template>

    <v-app-bar-title><h1>{{ label(panel) }}</h1></v-app-bar-title>

    <template #append>
      <User />

      <v-btn
        v-if="aside"
        @click="drawer.toggle('aside')"
        :title="$gettext('Toggle side menu')"
        :icon="drawer.aside ? mdiChevronRight : mdiChevronLeft"
        class="btn-sidemenu"
      />
    </template>
  </v-app-bar>

  <Navigation />

  <v-main class="plugin-panel" :aria-label="label(panel)">
    <component :is="panel.component" :panel="panel" />
  </v-main>

  <AsideList
    v-if="aside"
    :filter="aside.filter"
    :defaults="aside.defaults"
    :content="aside.content()"
  />
</template>

<style scoped>
.plugin-panel {
  overflow-y: auto;
}
</style>
