/** @license MIT, https://opensource.org/license/mit */

<script>
import { useDirtyStore, useDrawerStore, useUserStore, useViewStack } from '../stores'
import {
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

    return {
      dirtyStore,
      drawer,
      user,
      viewStack,
      mdiChevronLeft,
      mdiChevronRight,
      mdiDatabaseArrowDown,
      mdiHistory,
      mdiKeyboardBackspace,
      mdiSwapHorizontal,
      allowedMinutes
    }
  },

  data: () => ({
    publishMenu: false
  }),

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

    publish() {
      this.publishMenu = false
      this.$emit('publish')
    },

    schedule() {
      this.publishMenu = false
      this.$emit('schedule')
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
        @click="goBack()"
        :title="$gettext('Back to list view')"
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
        :title="$gettext('Save')"
        :disabled="saveDisabled"
        :variant="saveDisabled ? 'plain' : 'flat'"
        :class="{ active: canSave, error: error, warning: conflict }"
        :icon="mdiDatabaseArrowDown"
        class="menu-save"
      />

      <v-menu v-model="publishMenu" :close-on-content-click="false">
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            icon
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
        </template>
        <v-card class="menu-content publish-menu">
          <v-card-actions class="publish-menu-actions">
            <v-btn @click="publish()" variant="flat" class="menu-publish-now" color="primary" :disabled="error" block>
              {{ $gettext('Publish') }}
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
              variant="flat"
              class="menu-publish-at"
              block
              >{{ $gettext('Schedule') }}</v-btn
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

.v-app-bar .v-btn.menu-publish.active {
  background-color: rgba(var(--v-theme-primary), 1);
  color: rgb(var(--v-theme-on-primary));
}

.publish-menu {
  width: min(736px, calc(100vw - 24px));
  padding: 0;
}

.publish-menu-actions {
  padding: 12px 16px;
}

.publish-menu-schedule {
  padding: 16px;
}

.publish-menu-heading {
  margin-bottom: 12px;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 0.875rem;
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
  color: rgb(var(--v-theme-surface));
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
