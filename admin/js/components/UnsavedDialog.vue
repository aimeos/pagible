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
    content-class="unsaved-body"
    max-width="440"
    persistent
    role="alertdialog"
    aria-describedby="unsaved-description"
  >
    <v-icon :icon="mdiAlertCircleOutline" color="warning" size="40" aria-hidden="true" />
    <p id="unsaved-description" class="unsaved-text">
      {{ $gettext('You have unsaved changes that will be lost if you leave.') }}
    </p>

    <template #actions-start>
      <v-btn @click="dirtyStore.discard()" variant="tonal" color="warning">
        {{ $gettext('Discard') }}
      </v-btn>
    </template>

    <template #actions>
      <v-btn @click="dirtyStore.cancel()" variant="text">
        {{ $gettext('Cancel') }}
      </v-btn>
      <v-btn ref="saveBtn" @click="dirtyStore.saveAndLeave()" variant="flat" color="primary">
        {{ $gettext('Save & leave') }}
      </v-btn>
    </template>
  </CmsDialog>
</template>

<style scoped>
:deep(.unsaved-body) {
  display: flex;
  align-items: center;
  gap: 16px;
}

.unsaved-text {
  margin: 0;
  line-height: 1.5;
}
</style>
