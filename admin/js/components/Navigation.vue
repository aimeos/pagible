/** @license MIT, https://opensource.org/license/mit */

<script>
import { useDisplay } from 'vuetify'
import { useUserStore, useDrawerStore, usePluginStore } from '../stores'
import { mdiFileTree, mdiShareVariant, mdiFolderMultipleImage, mdiKeyVariant } from '@mdi/js'

export default {
  setup() {
    const { mobile } = useDisplay()
    const drawer = useDrawerStore()
    const user = useUserStore()
    const plugin = usePluginStore()

    return { user, drawer, plugin, mobile }
  },

  computed: {
    builtins() {
      return [
        { permission: 'page:view', path: '/pages', icon: mdiFileTree, label: this.$gettext('Pages') },
        {
          permission: 'element:view',
          path: '/elements',
          icon: mdiShareVariant,
          label: this.$gettext('Shared elements')
        },
        { permission: 'file:view', path: '/files', icon: mdiFolderMultipleImage, label: this.$gettext('Files') },
        { permission: 'access:view', path: '/access', icon: mdiKeyVariant, label: this.$gettext('Access') }
      ]
    }
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
  <v-navigation-drawer v-model="drawer.nav" location="start" mobile-breakpoint="lg" :aria-label="$gettext('Panels')">
    <v-list>
      <template v-for="panel in builtins" :key="panel.permission">
        <v-list-item v-if="user.can(panel.permission)" rounded="lg">
          <router-link :to="panel.path" class="router-link" @click="toggle()">
            <v-icon :icon="panel.icon" class="icon" />
            {{ panel.label }}
          </router-link>
        </v-list-item>
      </template>
      <template v-for="(panel, key) in plugin.panels" :key="key">
        <v-list-item v-if="user.can(panel.permission)" rounded="lg">
          <router-link :to="'/' + key" class="router-link" @click="toggle()">
            <span v-if="panel.icon" class="icon" v-safe-svg="panel.icon"></span>
            {{ panel.label }}
          </router-link>
        </v-list-item>
      </template>
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

a.router-link:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
  border-radius: 4px;
}

a.router-link,
a.router-link:focus,
a.router-link:visited {
  color: rgb(var(--v-theme-on-surface-light));
  align-items: center;
  display: flex;
  gap: 8px;
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
