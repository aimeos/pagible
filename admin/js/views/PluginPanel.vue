/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiClose, mdiMenu } from '@mdi/js'
import Navigation from '../components/Navigation.vue'
import User from '../components/User.vue'
import { useDrawerStore } from '../stores'

export default {
  name: 'PluginPanel',

  components: {
    Navigation,
    User
  },

  props: {
    panel: {
      type: Object,
      required: true
    }
  },

  setup() {
    const drawer = useDrawerStore()

    return { drawer, mdiClose, mdiMenu }
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

    <v-app-bar-title><h1>{{ $gettext(panel.label) }}</h1></v-app-bar-title>

    <template #append>
      <User />
    </template>
  </v-app-bar>

  <Navigation />

  <v-main class="plugin-panel" :aria-label="$gettext(panel.label)">
    <component :is="panel.component" :panel="panel" />
  </v-main>
</template>

<style scoped>
.plugin-panel {
  overflow-y: auto;
}
</style>
