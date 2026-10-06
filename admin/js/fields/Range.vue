/** @license MIT, https://opensource.org/license/mit */

<script>
import { fieldBase } from '../field'

export default {
  extends: fieldBase,

  props: {
    modelValue: { type: Array }
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
    :modelValue="modelValue ?? config.default ?? []"
    @update:modelValue="$emit('update:modelValue', $event)"
    hide-details="auto"
  >
    <template #prepend>
      <span>{{ modelValue?.[0] ?? '' }}</span>
    </template>
    <template #append>
      <span>{{ modelValue?.[1] ?? '' }}</span>
    </template>
  </v-range-slider>
</template>
