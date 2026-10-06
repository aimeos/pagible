/** @license MIT, https://opensource.org/license/mit */

<script>
import { fieldBase } from '../field'

export default {
  extends: fieldBase,

  props: {
    modelValue: { type: Boolean }
  }
}
</script>

<template>
  <v-switch
    :hint="config.hint && $pgettext('fh', config.hint)"
    :false-value="config.off ?? false"
    :true-value="config.on ?? true"
    :readonly="readonly"
    :modelValue="modelValue ?? config.default ?? false"
    @update:modelValue="$emit('update:modelValue', $event)"
    hide-details="auto"
    color="primary"
    inset
  ></v-switch>
</template>
