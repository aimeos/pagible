/** @license MIT, https://opensource.org/license/mit */

<script>
import { fieldBase } from '../field'

export default {
  extends: fieldBase,

  props: {
    modelValue: { type: Number }
  }
}
</script>

<template>
  <v-slider
    :hint="config.hint && $pgettext('fh', config.hint)"
    :thumb-label="true"
    :step="config.step ?? 1"
    :max="config.max ?? 100"
    :min="config.min ?? 0"
    :readonly="readonly"
    :modelValue="modelValue ?? config.default ?? 0"
    @update:modelValue="$emit('update:modelValue', $event)"
    hide-details="auto"
  >
    <template #prepend>
      <span></span>
    </template>
    <template #append>
      <span>{{ modelValue }}</span>
    </template>
  </v-slider>
</template>
