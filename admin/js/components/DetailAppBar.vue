/** @license MIT, https://opensource.org/license/mit */

<script>
import { useDirtyStore, useDrawerStore, useUserStore, useViewStack } from '../stores'
import { commands, hint, useShortcuts } from '../shortcuts'
import {
  mdiCheck,
  mdiChevronLeft,
  mdiChevronRight,
  mdiDatabaseArrowDown,
  mdiHistory,
  mdiKeyboardBackspace,
  mdiSwapHorizontal
} from '@mdi/js'

const allowedMinutes = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55]

export default {
  name: 'DetailAppBar',

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
    publishTime: { type: [String, null], default: null },
    saving: { type: Boolean, default: false },
    stacked: { type: Boolean, default: false },
    type: { type: String, required: true }
  },

  emits: [
    'update:publishAt',
    'update:publishTime',
    'changes',
    'history',
    'publish',
    'save',
    'schedule'
  ],

  setup() {
    const dirtyStore = useDirtyStore()
    const drawer = useDrawerStore()
    const user = useUserStore()
    const viewStack = useViewStack()

    useShortcuts((vm) => ({
      aside: () => vm.drawer.toggle('aside'),
      back: () => vm.goBack(),
      nextTab: () => vm.switchTab(1),
      prevTab: () => vm.switchTab(-1),
      ...(vm.user.can(`${vm.type}:publish`) && { publish: () => vm.openPublish() }),
      ...(vm.user.can(`${vm.type}:save`) && { save: () => !vm.saveDisabled && !vm.saving && vm.$emit('save') })
    }))

    return {
      dirtyStore,
      drawer,
      user,
      viewStack,
      mdiCheck,
      mdiChevronLeft,
      mdiChevronRight,
      mdiDatabaseArrowDown,
      mdiHistory,
      mdiKeyboardBackspace,
      mdiSwapHorizontal,
      allowedMinutes,
      commands,
      hint
    }
  },

  data: () => ({
    publishMenu: false,
    saved: false
  }),

  beforeUnmount() {
    clearTimeout(this.savedTimer)
  },

  watch: {
    publishMenu(value) {
      // focus the publish button so Enter confirms, e.g. after opening the menu by shortcut
      value && this.$nextTick(() => this.focusPublish())
    },

    saving(value, old) {
      if (old && !value && !this.dirty && !this.error) {
        clearTimeout(this.savedTimer)
        this.saved = true
        this.savedTimer = setTimeout(() => (this.saved = false), 1600)
      }
    }
  },

  methods: {
    async goBack() {
      if (this.stacked) {
        this.viewStack.closeView()
      } else if (this.dirtyStore.dirty) {
        await this.dirtyStore.confirm(() => {
          this.$router.push({ name: `${this.type}:view` })
        })
      } else {
        this.$router.push({ name: `${this.type}:view` })
      }
    },

    focusPublish() {
      this.$refs.publishNow?.$el.focus()
    },

    openPublish() {
      if (!this.pubDisabled) {
        this.publishMenu = true
      }
    },

    switchTab(dir) {
      // clicking the tab also executes the tab's own click handlers
      const tabs = [...(this.$el?.closest?.('.v-layout')?.querySelectorAll('.detail-tabs .v-tab:not([disabled])') || [])]
      const idx = tabs.findIndex((tab) => tab.classList.contains('v-tab--selected'))

      tabs[idx + dir]?.click()
    },

    publish(close = false) {
      this.publishMenu = false
      this.$emit('publish', close)
    },

    schedule(close = false) {
      this.publishMenu = false
      this.$emit('schedule', close)
    }
  },

  computed: {
    canPublish() {
      return (!this.published || this.dirty) && !this.error && this.user.can(`${this.type}:publish`)
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
        @click="goBack()"
        :title="$gettext('Back to list view') + ` (${hint('back')})`"
        :aria-keyshortcuts="commands.back.aria"
        :icon="mdiKeyboardBackspace"
        class="btn-back"
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
        class="btn-history no-rtl"
      />

      <v-btn
        v-if="changed"
        @click="$emit('changes')"
        :class="{ error: conflict }"
        :title="$gettext('View merge changes')"
        :icon="mdiSwapHorizontal"
        class="menu-changed"
      />

      <v-btn
        @click="$emit('save')"
        :loading="saving"
        :title="$gettext('Save') + ` (${hint('save')})`"
        :aria-keyshortcuts="commands.save.aria"
        :disabled="saveDisabled"
        :variant="saveDisabled ? 'plain' : 'tonal'"
        :color="error ? 'error' : conflict ? 'warning' : !saveDisabled ? 'primary' : ''"
        :icon="saved ? mdiCheck : mdiDatabaseArrowDown"
        :class="{ saved }"
        class="menu-save"
      />

      <v-menu v-model="publishMenu" :close-on-content-click="false">
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            icon
            :loading="publishing"
            :title="$gettext('Publish') + ` (${hint('publish')})`"
            :aria-keyshortcuts="commands.publish.aria"
            :disabled="pubDisabled"
            :variant="pubDisabled ? 'plain' : 'tonal'"
            :class="{ active: canPublish, error: error }"
            class="menu-publish"
          >
            <v-icon>
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
                <path d="M5,2V4H19V2H5 M5,12H9V21H15V12H19L12,5L5,12Z" />
              </svg>
            </v-icon>
          </v-btn>
        </template>
        <v-card class="menu-content publish-menu">
          <v-toolbar density="compact">
            <v-toolbar-title>{{ $gettext('Publish') }}</v-toolbar-title>
          </v-toolbar>
          <v-card-actions class="publish-menu-actions">
            <v-btn ref="publishNow" @click="publish()" variant="tonal" class="menu-publish-now" color="primary" :disabled="error" block>
              {{ $gettext('Publish') }}
            </v-btn>
            <v-btn @click="publish(true)" variant="tonal" class="menu-publish-close" color="primary" :disabled="error" block>
              {{ $gettext('Publish & Close') }}
            </v-btn>
          </v-card-actions>
          <v-divider />
          <v-card-text class="publish-menu-schedule">
            <div class="publish-menu-heading">{{ $gettext('Schedule') }}</div>
            <div class="menu-publish-pickers">
              <v-date-picker
                :model-value="publishAt"
                @update:model-value="$emit('update:publishAt', $event)"
                hide-header
                show-adjacent-months
              />
              <v-time-picker
                :model-value="publishTime"
                @update:model-value="$emit('update:publishTime', $event)"
                :allowed-minutes="allowedMinutes"
                format="24hr"
                density="compact"
                hide-title
              />
            </div>
          </v-card-text>
          <v-card-actions class="publish-menu-actions">
            <v-btn
              @click="schedule()"
              :disabled="!publishAt || error"
              :color="publishAt ? 'primary' : ''"
              variant="tonal"
              class="menu-schedule-at"
              block
              >{{ $gettext('Schedule') }}</v-btn
            >
            <v-btn
              @click="schedule(true)"
              :disabled="!publishAt || error"
              :color="publishAt ? 'primary' : ''"
              variant="tonal"
              class="menu-schedule-close"
              block
              >{{ $gettext('Schedule & Close') }}</v-btn
            >
          </v-card-actions>
        </v-card>
      </v-menu>

      <v-btn
        @click.stop="drawer.toggle('aside')"
        :title="$gettext('Toggle side menu')"
        :icon="drawer.aside ? mdiChevronRight : mdiChevronLeft"
        class="btn-sidemenu"
      />
    </template>
  </v-app-bar>
</template>

<style scoped>
.v-app-bar .v-btn.menu-save.saved {
  opacity: 1;
  animation: save-pulse 0.9s ease-out;
}

.v-app-bar .v-btn.menu-save.saved :deep(.v-icon) {
  animation: save-pop 0.35s cubic-bezier(0.2, 0.8, 0.2, 1.4);
}

@keyframes save-pulse {
  from {
    box-shadow: 0 0 0 0 rgba(var(--v-theme-success), 0.55);
  }
  to {
    box-shadow: 0 0 0 12px rgba(var(--v-theme-success), 0);
  }
}

@keyframes save-pop {
  from {
    transform: scale(0.4);
  }
  to {
    transform: scale(1);
  }
}

@media (prefers-reduced-motion: reduce) {
  .v-app-bar .v-btn.menu-save.saved,
  .v-app-bar .v-btn.menu-save.saved :deep(.v-icon) {
    animation: none;
  }
}

.publish-menu {
  padding: 0;
}

.publish-menu-actions {
  padding: 12px 16px;
}

.publish-menu-actions .v-btn :deep(.v-btn__content) {
  color: rgb(var(--v-theme-on-background));
}

/* The menu background is dark in both themes: use the lighter nav tint like the app bar */
.publish-menu-actions .v-btn.text-primary:not(.v-btn--disabled) :deep(.v-btn__underlay) {
  background-color: rgb(var(--v-theme-nav-accent, var(--v-theme-primary)));
  opacity: 0.45;
}

.publish-menu-schedule {
  padding: 16px;
}

.publish-menu-heading {
  margin-bottom: 12px;
  color: rgba(var(--v-theme-on-background), var(--v-high-emphasis-opacity));
  font-size: 1rem;
  font-weight: 500;
}

.menu-publish-pickers {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  align-items: stretch;
}

.menu-publish-pickers :deep(.v-sheet.v-picker) {
  width: 100%;
  height: 100%;
  min-width: 0;
  padding: 0;
}

.menu-publish-pickers :deep(.v-picker .v-date-picker-month__day--selected button) {
  color: rgb(var(--v-theme-on-surface-variant));
}

@media (max-width: 759px) {
  .publish-menu {
    width: min(360px, calc(100vw - 24px));
  }

  .menu-publish-pickers {
    grid-template-columns: minmax(0, 1fr);
  }

  .menu-publish-pickers :deep(.v-sheet.v-picker) {
    height: auto;
  }
}
</style>
