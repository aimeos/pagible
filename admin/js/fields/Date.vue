/** @license MIT, https://opensource.org/license/mit */

<script>
import { required } from '../rules'
import { fieldBase } from '../field'

export default {
  extends: fieldBase,

  props: {
    modelValue: { type: [Array, Date, String, null] }
  },

  computed: {
    rules() {
      return [required(this.$gettext, this.config.required)]
    }
  }
}
</script>

<template>
  <v-date-input
    :hint="config.hint && $pgettext('fh', config.hint)"
    :error="hasError"
    :rules="rules"
    :readonly="readonly"
    :allowed-dates="config.allowed"
    :clearable="!readonly && !config.required"
    :max="config.max"
    :min="config.min"
    :multiple="config.multiple"
    :placeholder="config.placeholder || null"
    :modelValue="modelValue ?? config.default ?? null"
    @update:modelValue="$emit('update:modelValue', $event)"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
    show-adjacent-months
  ></v-date-input>
</template>
