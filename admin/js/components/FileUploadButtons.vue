/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiPlus, mdiPlusLock } from '@mdi/js'
import { useUserStore } from '../stores'

/**
 * Buttons for adding public and, if the user may relocate files, access protected files
 */
export default {
  name: 'FileUploadButtons',

  props: {
    disabled: { type: Boolean, default: false }
  },

  emits: ['upload'],

  setup() {
    return { user: useUserStore(), mdiPlus, mdiPlusLock }
  }
}
</script>

<template>
  <div class="upload-actions">
    <v-btn
      @click="$emit('upload', 'public')"
      :title="$gettext('Add files')"
      :disabled="disabled"
      :icon="mdiPlus"
      class="btn-add"
      color="primary"
      variant="tonal"
    />
    <v-btn
      v-if="user.can('file:relocate')"
      @click="$emit('upload', 'private')"
      :title="$gettext('Add files') + ': ' + $gettext('Protect access')"
      :disabled="disabled"
      :icon="mdiPlusLock"
      class="btn-add-private"
      color="primary"
      variant="tonal"
    />
  </div>
</template>

<style scoped>
.upload-actions {
  display: flex;
  gap: 8px;
}
</style>
