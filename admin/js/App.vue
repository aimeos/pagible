/** @license MIT, https://opensource.org/license/mit */

<script>
import UnsavedDialog from './components/UnsavedDialog.vue'
import { cleanEcho, setupEcho } from './echo'
import { invalidatePages } from './graphql'
import { useDirtyStore, useMessageStore, useUserStore, useViewStack } from './stores'

export default {
  components: { UnsavedDialog },

  data: () => ({
    destroyed: true,
    echoCleanup: null,
    echoPromise: null
  }),

  setup() {
    const dirtyStore = useDirtyStore()
    const messages = useMessageStore()
    const user = useUserStore()
    const viewStack = useViewStack()

    return { dirtyStore, messages, user, viewStack }
  },

  created() {
    window.addEventListener('beforeunload', this.beforeUnload)
  },

  beforeUnmount() {
    this.destroyed = true
    cleanEcho(this)
    window.removeEventListener('beforeunload', this.beforeUnload)
  },

  watch: {
    'user.me': {
      handler(user) {
        this.destroyed = !user
        cleanEcho(this)

        if (user && this.user.can('page:view')) {
          setupEcho(this, 'page', () => invalidatePages(this.$apollo.provider.defaultClient.cache))
        }
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
            <keep-alive :include="['PageList', 'ElementList', 'FileList']">
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

    <UnsavedDialog />
    <v-snackbar-queue v-model="messages.queue"></v-snackbar-queue>
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
  transition: transform 0.3s ease;
}

.slide-stack-enter-from {
  transform: translateX(100%);
}

.slide-stack-leave-to {
  transform: translateX(100%);
}

a:focus-visible,
button:focus-visible,
[role='button']:focus-visible,
[tabindex]:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

@media (prefers-reduced-motion: reduce) {
  .slide-stack-enter-active,
  .slide-stack-leave-active {
    transition: none;
  }
}
</style>
