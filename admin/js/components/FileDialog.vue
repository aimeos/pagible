/** @license MIT, https://opensource.org/license/mit */

<script>
import CmsDialog from './Dialog.vue'
import FileListItems from './FileListItems.vue'

export default {
  components: {
    CmsDialog,
    FileListItems
  },

  props: {
    modelValue: { type: Boolean, required: true },
    filter: { type: Object, default: () => ({}) },
    grid: { type: Boolean, default: false }
  },
  emits: ['update:modelValue', 'add']
}
</script>

<template>
  <CmsDialog
    :model-value="modelValue"
    :title="$gettext('Media')"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="1200"
  >
    <FileListItems :filter="filter" :grid="grid" @select="$emit('add', $event)" embed />
  </CmsDialog>
</template>

<style scoped></style>
