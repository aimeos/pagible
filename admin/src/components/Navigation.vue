/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import { useDisplay } from 'vuetify'
import { useUserStore, useDrawerStore } from '../stores'
import { mdiFileTree, mdiShareVariant, mdiFolderMultipleImage } from '@mdi/js'

export default {
  setup() {
    const { mobile } = useDisplay()
    const drawer = useDrawerStore()
    const user = useUserStore()

    return { user, drawer, mobile, mdiFileTree, mdiShareVariant, mdiFolderMultipleImage }
  },

  methods: {
    toggle() {
      if (this.mobile) {
        this.drawer.nav = !this.drawer.nav
      }
    }
  }
}
</script>

<template>
  <v-navigation-drawer v-model="drawer.nav" location="start" mobile-breakpoint="lg">
    <v-list>
      <v-list-item v-if="user.can('page:view')" rounded="lg">
        <v-icon :icon="mdiFileTree" class="icon" />
        <router-link to="/pages" class="router-link" @click="toggle()">
          {{ $gettext('Pages') }}
        </router-link>
      </v-list-item>
      <v-list-item v-if="user.can('element:view')" rounded="lg">
        <v-icon :icon="mdiShareVariant" class="icon" />
        <router-link to="/elements" class="router-link" @click="toggle()">
          {{ $gettext('Shared elements') }}
        </router-link>
      </v-list-item>
      <v-list-item v-if="user.can('file:view')" rounded="lg">
        <v-icon :icon="mdiFolderMultipleImage" class="icon" />
        <router-link to="/files" class="router-link" @click="toggle()">
          {{ $gettext('Files') }}
        </router-link>
      </v-list-item>
    </v-list>
  </v-navigation-drawer>
</template>

<style scoped>
.v-navigation-drawer {
  border-top-right-radius: 8px;
}

.v-locale--is-rtl .v-navigation-drawer {
  border-top-right-radius: 0;
  border-top-left-radius: 8px;
}

:deep(.v-list-item__content) {
  align-items: center;
  display: flex;
}

a.router-link,
a.router-link:focus,
a.router-link:visited {
  color: rgb(var(--v-theme-on-surface-light));
  display: block;
  width: 100%;
  padding: 8px;
}

.v-list-item:has(.router-link-active),
.v-list-item:has(.router-link-active) a {
  background-color: rgb(var(--v-theme-surface-light));
}

.v-list-item .icon {
  font-size: 100%;
}
</style>
