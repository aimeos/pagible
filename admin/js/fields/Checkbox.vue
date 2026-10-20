/** @license MIT, https://opensource.org/license/mit */

<script>
import { fieldBase } from '../field'

export default {
  extends: fieldBase,

  props: {
    // custom on/off values may be strings or numbers, undefined uses the configured default
    modelValue: { type: [Boolean, String, Number], default: undefined }
  }
}
</script>

<template>
  <v-checkbox
    :hint="config.hint && $pgettext('fh', config.hint)"
    :false-value="config.off ?? false"
    :true-value="config.on ?? true"
    :readonly="readonly"
    :modelValue="modelValue ?? config.default ?? false"
    @update:modelValue="$emit('update:modelValue', $event)"
    hide-details="auto"
  ></v-checkbox>
</template>
