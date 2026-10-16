/** @license MIT, https://opensource.org/license/mit */

<script>
import CommandPalette from './components/CommandPalette.vue'
import ConfirmDialog from './components/ConfirmDialog.vue'
import ShortcutDialog from './components/ShortcutDialog.vue'
import ReloginDialog from './components/ReloginDialog.vue'
import UnsavedDialog from './components/UnsavedDialog.vue'
import { setupEcho } from './echo'
import { invalidateList } from './graphql'
import { keydown, setNavigate } from './shortcuts'
import { useDirtyStore, useMessageStore, useTranslationStore, useUserStore, useViewStack } from './stores'

const CONTENT_TYPES = ['page', 'element', 'file']

export default {
  components: { CommandPalette, ConfirmDialog, ReloginDialog, ShortcutDialog, UnsavedDialog },

  setup() {
    const dirtyStore = useDirtyStore()
    const messages = useMessageStore()
    const user = useUserStore()
    const viewStack = useViewStack()

    return { dirtyStore, messages, user, viewStack }
  },

  created() {
    window.addEventListener('beforeunload', this.beforeUnload)
    window.addEventListener('keydown', keydown, true)
    setNavigate((name) => this.$router.push({ name }))
  },

  beforeUnmount() {
    this.unsubscribe?.()
    window.removeEventListener('beforeunload', this.beforeUnload)
    window.removeEventListener('keydown', keydown, true)
    setNavigate(null)
  },

  watch: {
    'user.me': {
      handler(user) {
        this.unsubscribe?.()

        const types = user ? CONTENT_TYPES.filter((type) => this.user.can(`${type}:view`)) : []

        this.unsubscribe = types.length ? setupEcho(types, (_event, _name, type) => {
          invalidateList(`${type}s`)
        }) : null

        // shows the progress of translations queued before the tab was reloaded
        user && useTranslationStore().resume()
      },
      immediate: true
    }
  },

  methods: {
    beforeUnload(e) {
      if (this.dirtyStore.dirty) {
        e.preventDefault()
      }
    }
  }
}
</script>

<template>
  <v-app>
    <main>
      <transition-group name="slide-stack">
        <v-layout ref="baseview" key="list" class="view" style="z-index: 10">
          <router-view v-slot="{ Component, route }">
            <keep-alive :key="route.meta.auth ? user.session : 0" :include="['PageList', 'ElementList', 'FileList']">
              <component :is="Component" :key="route.path" />
            </keep-alive>
          </router-view>
        </v-layout>

        <v-layout
          ref="view"
          v-for="(view, i) in viewStack.stack"
          :key="i"
          class="view"
          :style="{ zIndex: 11 + i }"
        >
          <component :is="view.component" v-bind="view.props" />
        </v-layout>
      </transition-group>
    </main>

    <CommandPalette v-if="user.me" />
    <ConfirmDialog />
    <ShortcutDialog />
    <UnsavedDialog />
    <ReloginDialog v-if="user.me" />
    <v-snackbar-queue v-model="messages.queue">
      <template #actions="{ item, props }">
        <v-btn
          v-if="messages.action(item['data-action'])"
          @click="messages.run(item['data-action']); props.onClick()"
          class="btn-message-action"
          variant="text"
          >{{ messages.action(item['data-action']).label }}</v-btn
        >
      </template>
    </v-snackbar-queue>
    <div role="status" aria-live="polite" aria-atomic="true" class="v-sr-only">
      {{ messages.queue[messages.queue.length - 1]?.text }}
    </div>
  </v-app>
</template>

<style>
html,
body {
  position: absolute;
  overflow: hidden;
  height: 100%;
  width: 100%;
  left: 0;
  top: 0;
}

.view {
  background: rgb(var(--v-theme-background));
  position: absolute !important;
  min-height: 100%;
  width: 100%;
}

@media (min-width: 960px) {
  .v-navigation-drawer,
  .v-main {
    transition: none !important;
  }
}

/* Slide animation */
.slide-stack-enter-active,
.slide-stack-leave-active {
  box-shadow: -24px 0 48px -16px rgba(var(--v-shadow-color), 0.45);
}

.slide-stack-enter-active {
  transition:
    transform 0.34s cubic-bezier(0.2, 0.8, 0.2, 1),
    opacity 0.24s ease-out;
}

.slide-stack-leave-active {
  transition:
    transform 0.24s cubic-bezier(0.4, 0, 1, 1),
    opacity 0.24s ease-in;
}

.slide-stack-enter-from {
  transform: translateX(100%);
  opacity: 0.6;
}

.slide-stack-leave-to {
  transform: translateX(100%);
  opacity: 0.6;
}

a:focus-visible,
button:focus-visible,
[role='button']:focus-visible,
[tabindex]:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

/* Primary lacks contrast on the dark background, use the lighter nav accent there */
.v-app-bar :focus-visible,
.v-navigation-drawer.nav :focus-visible,
.detail-tabs :focus-visible,
.menu-content :focus-visible,
.toolbar :focus-visible {
  outline-color: rgb(var(--v-theme-nav-accent, var(--v-theme-primary)));
}
</style>
