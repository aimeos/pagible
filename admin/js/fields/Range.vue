/** @license MIT, https://opensource.org/license/mit */

<script>
import { fieldBase } from '../field'

export default {
  extends: fieldBase,

  props: {
    modelValue: { type: Array }
  },

  computed: {
    // the labels must show the same value as the slider position
    value() {
      return this.modelValue ?? this.config.default ?? []
    }
  }
}
</script>

<template>
  <v-range-slider
    :hint="config.hint && $pgettext('fh', config.hint)"
    :thumb-label="true"
    :step="config.step ?? 1"
    :max="config.max ?? 100"
    :min="config.min ?? 0"
    :readonly="readonly"
    :modelValue="value"
    @update:modelValue="$emit('update:modelValue', $event)"
    hide-details="auto"
  >
    <template #prepend>
      <span>{{ value[0] ?? '' }}</span>
    </template>
    <template #append>
      <span>{{ value[1] ?? '' }}</span>
    </template>
  </v-range-slider>
</template>
