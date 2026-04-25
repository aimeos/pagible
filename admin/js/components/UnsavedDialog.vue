/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import { mdiAlertCircleOutline, mdiClose } from '@mdi/js'
import { useDirtyStore } from '../stores'

export default {
  setup() {
    const dirtyStore = useDirtyStore()
    return { dirtyStore, mdiAlertCircleOutline, mdiClose }
  },

  watch: {
    'dirtyStore.show'(visible) {
      if (visible) {
        this.$nextTick(() => {
          this.$refs.saveBtn?.$el?.focus({ focusVisible: true })
        })
      }
    }
  }
}
</script>

<template>
  <v-dialog
    :model-value="dirtyStore.show"
    max-width="440"
    persistent
    role="alertdialog"
    :aria-label="$gettext('Unsaved changes')"
    aria-describedby="unsaved-description"
  >
    <v-card>
      <v-toolbar density="compact" class="unsaved-toolbar">
        <v-toolbar-title>
          {{ $gettext('Unsaved changes') }}
        </v-toolbar-title>
        <v-btn :icon="mdiClose" :aria-label="$gettext('Close')" @click="dirtyStore.cancel()" />
      </v-toolbar>

      <v-card-text class="unsaved-body">
        <v-icon :icon="mdiAlertCircleOutline" color="warning" size="40" aria-hidden="true" />
        <p id="unsaved-description" class="unsaved-text">
          {{ $gettext('You have unsaved changes that will be lost if you leave.') }}
        </p>
      </v-card-text>

      <v-card-actions class="unsaved-actions">
        <v-btn @click="dirtyStore.discard()" variant="tonal" color="error">
          {{ $gettext('Discard') }}
        </v-btn>
        <v-spacer />
        <v-btn @click="dirtyStore.cancel()" variant="text">
          {{ $gettext('Cancel') }}
        </v-btn>
        <v-btn ref="saveBtn" @click="dirtyStore.saveAndLeave()" variant="flat" color="blue-darken-1">
          {{ $gettext('Save & leave') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.unsaved-toolbar {
  background: rgb(var(--v-theme-warning));
  color: #000;
}

.unsaved-toolbar :deep(.v-btn) {
  color: #000;
}

.unsaved-body {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px 24px 12px;
}

.unsaved-text {
  margin: 0;
  line-height: 1.5;
}

.unsaved-actions {
  padding: 8px 16px 16px;
}
</style>
