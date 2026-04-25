/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import { useDrawerStore, useUserStore, useViewStack } from '../stores'
import {
  mdiChevronLeft,
  mdiChevronRight,
  mdiDatabaseArrowDown,
  mdiHistory,
  mdiKeyboardBackspace,
  mdiSwapHorizontal
} from '@mdi/js'

export default {
  props: {
    changed: { type: Object, default: null },
    conflict: { type: Boolean, default: false },
    dirty: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
    hasLatest: { type: Boolean, default: false },
    label: { type: String, required: true },
    name: { type: String, default: '' },
    published: { type: Boolean, default: false },
    publishing: { type: Boolean, default: false },
    publishAt: { type: [Date, null], default: null },
    saving: { type: Boolean, default: false },
    type: { type: String, required: true }
  },

  emits: ['update:publishAt', 'changes', 'history', 'publish', 'save', 'schedule'],

  setup() {
    const drawer = useDrawerStore()
    const user = useUserStore()
    const viewStack = useViewStack()

    return {
      drawer,
      user,
      viewStack,
      mdiChevronLeft,
      mdiChevronRight,
      mdiDatabaseArrowDown,
      mdiHistory,
      mdiKeyboardBackspace,
      mdiSwapHorizontal
    }
  },

  computed: {
    canPublish() {
      return (!this.published || this.dirty) && !this.error && this.user.can(`${this.type}:publish`)
    },

    canSave() {
      return this.dirty && !this.error && this.user.can(`${this.type}:save`)
    },

    pubDisabled() {
      return (this.published && !this.dirty) || this.error || !this.user.can(`${this.type}:publish`)
    },

    saveDisabled() {
      return !this.dirty || this.error || !this.user.can(`${this.type}:save`)
    }
  }
}
</script>

<template>
  <v-app-bar :elevation="0" density="compact" role="sectionheader" :aria-label="$gettext('Menu')">
    <template v-slot:prepend>
      <v-btn
        @click="viewStack.closeView()"
        :title="$gettext('Back to list view')"
        :icon="mdiKeyboardBackspace"
      />
    </template>

    <v-app-bar-title>
      <h1 class="app-title">{{ label }}: {{ name }}</h1>
    </v-app-bar-title>

    <template v-slot:append>
      <slot name="actions" />

      <v-btn
        @click="$emit('history')"
        :class="{ hidden: published && !dirty && !hasLatest }"
        :title="$gettext('View history')"
        :icon="mdiHistory"
        class="no-rtl"
      />

      <v-btn v-if="changed"
        @click="$emit('changes')"
        :class="{ error: conflict }"
        :title="$gettext('View merge changes')"
        :icon="mdiSwapHorizontal"
        class="menu-changed"
      />

      <v-btn
        @click="$emit('save')"
        :loading="saving"
        :title="$gettext('Save')"
        :disabled="saveDisabled"
        :variant="saveDisabled ? 'plain' : 'flat'"
        :class="{ active: canSave, error: error, warning: conflict }"
        :icon="mdiDatabaseArrowDown"
        class="menu-save"
      />

      <v-menu :close-on-content-click="false">
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            icon
            :loading="publishing"
            :title="$gettext('Schedule publishing')"
            :disabled="pubDisabled"
            :variant="pubDisabled ? 'plain' : 'flat'"
            :class="{ active: canPublish, error: error }"
            class="menu-publishat"
          >
            <v-icon>
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
                <path d="M2,1V3H16V1H2 M2,10H6V19H12V10H16L9,3L2,10Z" />
                <path d="M16.7 11.4C16.7 11.4 16.61 11.4 16.7 11.4C13.19 11.49 10.4 14.28 10.4 17.7C10.4 21.21 13.19 24 16.7 24S23 21.21 23 17.7 20.21 11.4 16.7 11.4M16.7 22.2C14.18 22.2 12.2 20.22 12.2 17.7S14.18 13.2 16.7 13.2 21.2 15.18 21.2 17.7 19.22 22.2 16.7 22.2M15.6 13.1V17.6L18.84 19.58L19.56 18.5L16.95 16.97V13.1H15.6Z" />
              </svg>
            </v-icon>
          </v-btn>
        </template>
        <div class="menu-content">
          <v-date-picker :model-value="publishAt" @update:model-value="$emit('update:publishAt', $event)" hide-header show-adjacent-months />
          <v-btn
            @click="$emit('schedule')"
            :disabled="!publishAt || error"
            :color="publishAt ? 'primary' : ''"
            variant="text"
          >{{ $gettext('Publish') }}</v-btn>
        </div>
      </v-menu>

      <v-btn
        icon
        @click="$emit('publish')"
        :loading="publishing"
        :title="$gettext('Publish')"
        :disabled="pubDisabled"
        :variant="pubDisabled ? 'plain' : 'flat'"
        :class="{ active: canPublish, error: error }"
        class="menu-publish"
      >
        <v-icon>
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
            <path d="M5,2V4H19V2H5 M5,12H9V21H15V12H19L12,5L5,12Z" />
          </svg>
        </v-icon>
      </v-btn>

      <v-btn
        @click.stop="drawer.toggle('aside')"
        :title="$gettext('Toggle side menu')"
        :icon="drawer.aside ? mdiChevronRight : mdiChevronLeft"
      />
    </template>
  </v-app-bar>
</template>

<style scoped>
.v-toolbar-title {
  margin-inline-start: 0;
}

.v-app-bar .v-btn.menu-save.active {
  background-color: rgba(var(--v-theme-primary), 0.75);
  color: rgb(var(--v-theme-on-primary));
}

.v-app-bar .v-btn.menu-save.warning {
  background-color: rgba(var(--v-theme-warning), 0.75);
  color: rgb(var(--v-theme-on-warning));
}

.v-app-bar .v-btn.menu-publishat.active {
  background-color: rgba(var(--v-theme-primary), 0.875);
  color: rgb(var(--v-theme-on-primary));
}

.v-app-bar .v-btn.menu-publish.active {
  background-color: rgba(var(--v-theme-primary), 1);
  color: rgb(var(--v-theme-on-primary));
}
</style>
