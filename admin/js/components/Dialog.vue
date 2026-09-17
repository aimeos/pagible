/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiClose } from '@mdi/js'

export default {
  inheritAttrs: false,

  props: {
    cardLoading: { type: [Boolean, String], default: false },
    closeLabel: { type: String, default: '' },
    contentClass: { type: [String, Array, Object], default: '' },
    maxWidth: { type: [Number, String], default: 640 },
    modelValue: { type: Boolean, required: true },
    title: { type: String, required: true },
    toolbarColor: { type: String, default: undefined }
  },

  emits: ['update:modelValue'],

  setup() {
    return { mdiClose }
  },

  methods: {
    close() {
      this.$emit('update:modelValue', false)
    }
  }
}
</script>

<template>
  <v-dialog
    v-bind="$attrs"
    :model-value="modelValue"
    :aria-label="title"
    :max-width="maxWidth"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-card :loading="cardLoading">
      <v-toolbar density="compact" :color="toolbarColor">
        <v-toolbar-title>{{ title }}</v-toolbar-title>
        <slot name="toolbar-actions" :close="close" />
        <v-btn :icon="mdiClose" :aria-label="closeLabel || $gettext('Close')" @click="close" />
      </v-toolbar>

      <v-card-text :class="contentClass">
        <slot />
      </v-card-text>

      <v-card-actions v-if="$slots.actions">
        <v-spacer />
        <slot name="actions" :close="close" />
      </v-card-actions>

      <slot name="footer" />
    </v-card>
  </v-dialog>
</template>
