/** @license MIT, https://opensource.org/license/mit */

<script>
import { required } from '../rules'
import { fieldBase } from '../field'

/**
 * Configuration:
 * - `hint`: string, description shown below the field while it has focus
 * - `max`: number, maximum value allowed in the input field
 * - `min`: number, minimum value allowed in the input field
 * - `placeholder`: string, placeholder text for the input field
 * - `precision`: int, maximum number of fractional digits
 * - `required`: boolean, if true, the field is required
 * - `step`: number, step size for the number input
 */
export default {
  extends: fieldBase,

  props: {
    modelValue: { type: Number }
  },

  computed: {
    rules() {
      return [required(this.$gettext, this.config.required)]
    }
  }
}
</script>

<template>
  <v-number-input
    :hint="config.hint && $pgettext('fh', config.hint)"
    :error="hasError"
    :rules="rules"
    :readonly="readonly"
    :clearable="!readonly && !config.required"
    :max="config.max"
    :min="config.min"
    :precision="config.precision"
    :step="config.step ?? 1"
    :placeholder="config.placeholder || ''"
    :modelValue="modelValue ?? config.default"
    @update:modelValue="$emit('update:modelValue', $event)"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
  ></v-number-input>
</template>
