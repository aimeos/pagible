/** @license MIT, https://opensource.org/license/mit */

<script>
import { useDisplay } from 'vuetify'
import { pluginLabel } from '../i18n'
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
        { permission: 'file:view', path: '/files', icon: mdiFolderMultipleImage, label: this.$gettext('Media') },
        { permission: 'element:view', path: '/elements', icon: mdiShareVariant, label: this.$gettext('Shared elements') },
        { permission: 'access:view', path: '/access', icon: mdiKeyVariant, label: this.$gettext('Users') }
      ]
    }
  },

  methods: {
    label(panel) {
      return pluginLabel(panel, this)
    },

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
            {{ label(panel) }}
          </router-link>
        </v-list-item>
      </template>
    </v-list>
  </v-navigation-drawer>
</template>

<style scoped>
.v-navigation-drawer--left {
  background-color: rgb(var(--v-theme-background));
  border: none;
  color: rgb(var(--v-theme-on-background));
}

.v-navigation-drawer--left .v-list {
  background-color: transparent;
  color: rgb(var(--v-theme-on-background));
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
  color: rgb(var(--v-theme-on-background));
  align-items: center;
  display: flex;
  gap: 8px;
  width: 100%;
  padding: 8px;
}

.v-navigation-drawer--left .v-list-item {
  position: relative;
  transition: background-color 0.15s ease;
}

.v-navigation-drawer--left .v-list-item:hover {
  background-color: rgba(var(--v-theme-on-background), 0.06);
}

.v-list-item:has(.router-link-active) {
  background-color: rgba(var(--v-theme-nav-accent, var(--v-theme-primary)), 0.16);
}

.v-list-item:has(.router-link-active)::before {
  content: '';
  position: absolute;
  inset-block: 8px;
  inset-inline-start: 0;
  width: 3px;
  border-radius: 3px;
  /* secondary is lightened towards the text color so it stays visible on the dark navigation */
  background: linear-gradient(
    180deg,
    rgb(var(--v-theme-nav-accent, var(--v-theme-primary))),
    color-mix(in srgb, rgb(var(--v-theme-secondary)) 60%, rgb(var(--v-theme-on-background)))
  );
}

.v-list-item:has(.router-link-active) .icon {
  color: rgb(var(--v-theme-nav-accent, var(--v-theme-primary)));
}

.v-list-item .icon {
  font-size: 100%;
}
</style>
