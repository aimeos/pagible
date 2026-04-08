/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import { useMessageStore, useViewStack } from './stores'

export default {
  setup() {
    const messages = useMessageStore()
    const viewStack = useViewStack()

    return { messages, viewStack }
  }
}
</script>

<template>
  <v-app>
    <main>
      <transition-group name="slide-stack">
        <v-layout ref="baseview" key="list" class="view" style="z-index: 10">
          <router-view />
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
