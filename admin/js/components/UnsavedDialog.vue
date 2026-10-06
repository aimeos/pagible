/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiAlertCircleOutline } from '@mdi/js'
import CmsDialog from './Dialog.vue'
import { useDirtyStore } from '../stores'

export default {
  components: {
    CmsDialog
  },

  setup() {
    const dirtyStore = useDirtyStore()
    return { dirtyStore, mdiAlertCircleOutline }
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
  <CmsDialog
    :model-value="dirtyStore.show"
    :title="$gettext('Unsaved changes')"
    @update:model-value="!$event && dirtyStore.cancel()"
    toolbar-color="warning"
    max-width="440"
    persistent
    role="alertdialog"
    aria-describedby="unsaved-description"
  >
    <div class="unsaved-body">
      <v-icon :icon="mdiAlertCircleOutline" color="warning" size="40" aria-hidden="true" />
      <p id="unsaved-description" class="unsaved-text">
        {{ $gettext('You have unsaved changes that will be lost if you leave.') }}
      </p>
    </div>

    <template #actions-start>
      <v-btn @click="dirtyStore.finalize()" variant="tonal" color="error">
        {{ $gettext('Discard') }}
      </v-btn>
    </template>

    <template #actions>
      <v-btn @click="dirtyStore.cancel()" variant="text">
        {{ $gettext('Cancel') }}
      </v-btn>
      <v-btn ref="saveBtn" @click="dirtyStore.saveAndLeave()" variant="tonal" color="primary">
        {{ $gettext('Save & leave') }}
      </v-btn>
    </template>
  </CmsDialog>
</template>

<style scoped>
.unsaved-body {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 16px;
}

/* the text wraps below the icon only if the dialog is too narrow */
.unsaved-text {
  flex: 1 1 12rem;
  margin: 0;
  line-height: 1.5;
}
</style>
