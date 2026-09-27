/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiClose } from '@mdi/js'

export default {
  props: {
    header: { type: Boolean, default: true },
    listClass: { type: [String, Array, Object], default: '' },
    listProps: { type: Object, default: () => ({}) },
    location: { type: String, default: 'end center' },
    maxWidth: { type: [Number, String], default: 300 },
    title: { type: String, default: '' }
  },

  data: () => ({
    open: false
  }),

  setup() {
    return { mdiClose }
  },

  computed: {
    label() {
      return this.title || this.$gettext('Actions')
    },

    overlayProps() {
      return this.$vuetify.display.xs
        ? {
            contentProps: {
              style: {
                left: '50%',
                top: '50%',
                transform: 'translate(-50%, -50%)'
              }
            }
          }
        : { location: this.location, transition: 'scale-transition' }
    }
  }
}
</script>

<template>
  <component
    :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
    v-model="open"
    v-bind="overlayProps"
    :aria-label="label"
    :max-width="maxWidth"
  >
    <template #activator="{ props }">
      <slot name="activator" :props="props" :label="label" />
    </template>

    <v-card class="action-menu-card d-flex flex-column">
      <v-toolbar v-if="header" density="compact">
        <v-toolbar-title>{{ label }}</v-toolbar-title>
        <v-btn :icon="mdiClose" :aria-label="$gettext('Close')" @click="open = false" />
      </v-toolbar>

      <v-list
        v-bind="listProps"
        :class="['action-menu-list', listClass]"
        @click="open = false"
      >
        <slot />
      </v-list>
    </v-card>
  </component>
</template>

<style scoped>
.action-menu-card {
  max-height: calc(100dvh - 48px);
}

/* Dark mode: same colors as the tab navigation, light mode keeps the surface colors */
.action-menu-card.v-theme--dark,
.v-theme--dark .action-menu-card {
  --v-border-color: var(--v-theme-on-background);
  background-color: rgb(var(--v-theme-background)) !important;
  color: rgb(var(--v-theme-on-background)) !important;
}

.action-menu-list {
  background-color: transparent;
  color: inherit;
  overflow-y: auto;
}

.action-menu-list :deep(.v-list-item),
.action-menu-list :deep(.v-list-group__header) {
  color: inherit;
  transition: background-color 0.15s ease;
}

.action-menu-list :deep(.v-list-item:hover),
.action-menu-list :deep(.v-list-item:focus-within) {
  background-color: rgba(var(--v-theme-nav-accent, var(--v-theme-primary)), 0.16);
}

.action-menu-list :deep(.v-list-item .v-btn:hover > .v-btn__overlay) {
  opacity: 0;
}
</style>
