/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import FileListItems from './FileListItems.vue'
import { mdiClose } from '@mdi/js'

export default {
  components: {
    FileListItems
  },

  props: {
    modelValue: { type: Boolean, required: true },
    filter: { type: Object, default: () => ({}) },
    grid: { type: Boolean, default: false }
  },

  emits: ['update:modelValue', 'add'],

  setup() {
    return { mdiClose }
  }
}
</script>

<template>
  <v-dialog
    :aria-label="$gettext('Files')"
    :modelValue="modelValue"
    @afterLeave="$emit('update:modelValue', false)"
    max-width="1200"
    scrollable
  >
    <v-card>
      <v-toolbar density="compact">
        <v-toolbar-title>{{ $gettext('Files') }}</v-toolbar-title>
        <v-btn :icon="mdiClose" :aria-label="$gettext('Close')" @click="$emit('update:modelValue', false)" />
      </v-toolbar>
      <v-card-text>
        <FileListItems :filter="filter" :grid="grid" @select="$emit('add', $event)" embed />
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped></style>
