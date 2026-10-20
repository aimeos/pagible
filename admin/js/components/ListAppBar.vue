/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiChevronLeft, mdiChevronRight, mdiClose, mdiMenu } from '@mdi/js'
import { useDrawerStore } from '../stores'
import User from './User.vue'

/**
 * App bar of the list views with the navigation and the optional side menu toggle
 */
export default {
  name: 'ListAppBar',

  components: {
    User
  },

  props: {
    title: { type: String, required: true },
    aside: { type: Boolean, default: true }
  },

  setup() {
    return { drawer: useDrawerStore(), mdiChevronLeft, mdiChevronRight, mdiClose, mdiMenu }
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

    <v-app-bar-title
      ><h1>{{ title }}</h1></v-app-bar-title
    >

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
</template>
