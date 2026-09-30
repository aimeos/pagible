/** @license MIT, https://opensource.org/license/mit */

<script>
import { useDisplay } from 'vuetify'
import { pluginLabel } from '../i18n'
import { useUserStore, useDrawerStore, usePluginStore } from '../stores'
import { commands, hint, shortcuts } from '../shortcuts'
import {
  mdiConsoleLine,
  mdiFileTree,
  mdiShareVariant,
  mdiFolderMultipleImage,
  mdiKeyboardOutline,
  mdiKeyVariant
} from '@mdi/js'

export default {
  setup() {
    const { mobile } = useDisplay()
    const drawer = useDrawerStore()
    const user = useUserStore()
    const plugin = usePluginStore()

    return { user, drawer, plugin, mobile, commands, hint, shortcuts, mdiConsoleLine, mdiKeyboardOutline }
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
  <v-navigation-drawer v-model="drawer.nav" class="nav" location="start" mobile-breakpoint="lg" :aria-label="$gettext('Panels')">
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

    <template #append>
      <v-list>
        <v-list-item rounded="lg">
          <button
            type="button"
            class="router-link btn-palette"
            :aria-keyshortcuts="commands.palette.aria"
            @click="shortcuts.palette = true"
          >
            <v-icon :icon="mdiConsoleLine" class="icon" />
            {{ $gettext('Commands') }}
            <kbd class="hint" aria-hidden="true">{{ hint('palette') }}</kbd>
          </button>
        </v-list-item>
        <v-list-item rounded="lg">
          <button
            type="button"
            class="router-link btn-shortcuts"
            :aria-keyshortcuts="commands.sheet.aria"
            @click="shortcuts.sheet = true"
          >
            <v-icon :icon="mdiKeyboardOutline" class="icon" />
            {{ $gettext('Keyboard shortcuts') }}
            <kbd class="hint" aria-hidden="true">{{ hint('sheet') }}</kbd>
          </button>
        </v-list-item>
      </v-list>
    </template>
  </v-navigation-drawer>
</template>

<style scoped>
.v-navigation-drawer.nav {
  --v-border-color: var(--v-theme-on-background);
  background-color: rgb(var(--v-theme-background));
  border: none;
  color: rgb(var(--v-theme-on-background));
}

.v-navigation-drawer.nav .v-list {
  background-color: transparent;
  color: rgb(var(--v-theme-on-background));
}

.v-locale--is-rtl .v-navigation-drawer {
  border-top-right-radius: 0;
  border-top-left-radius: 8px;
}

button.router-link {
  background: transparent;
  border: none;
  cursor: pointer;
  font: inherit;
  text-align: start;
}

.btn-palette .hint,
.btn-shortcuts .hint {
  margin-inline-start: auto;
  padding: 0 6px;
  border: 1px solid rgba(var(--v-border-color), var(--v-medium-emphasis-opacity));
  border-radius: 4px;
  background: transparent;
  font-family: inherit;
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
}

button.router-link:focus-visible,
a.router-link:focus-visible {
  outline: 2px solid rgb(var(--v-theme-nav-accent, var(--v-theme-primary)));
  outline-offset: -2px;
  border-radius: 4px;
}

button.router-link,
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

.v-navigation-drawer.nav .v-list-item {
  position: relative;
  transition: background-color 0.15s ease;
}

.v-navigation-drawer.nav .v-list-item:hover {
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
  background: rgb(var(--v-theme-nav-accent, var(--v-theme-primary)));
}

.v-list-item:has(.router-link-active) .icon {
  color: rgb(var(--v-theme-nav-accent, var(--v-theme-primary)));
}

.v-list-item .icon {
  font-size: 100%;
}
</style>
