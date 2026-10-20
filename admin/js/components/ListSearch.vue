/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiMagnify } from '@mdi/js'

/**
 * Search field of the lists, the default slot contains additional filters shown before
 */
export default {
  props: {
    modelValue: { type: String, default: '' }
  },

  emits: ['update:modelValue'],

  setup() {
    return { mdiMagnify }
  },

  methods: {
    focus() {
      this.$refs.field?.focus()
    }
  }
}
</script>

<template>
  <div class="search">
    <slot />
    <v-text-field
      ref="field"
      :model-value="modelValue"
      @update:model-value="$emit('update:modelValue', $event)"
      :label="$gettext('Search for')"
      :prepend-inner-icon="mdiMagnify"
      variant="underlined"
      hide-details
      clearable
    ></v-text-field>
  </div>
</template>
