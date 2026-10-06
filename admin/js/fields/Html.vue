/** @license MIT, https://opensource.org/license/mit */

<script>
import { minChars, maxChars, required } from '../rules'
import { fieldBase } from '../field'

/**
 * Configuration:
 * - `hint`: string, description shown below the field while it has focus
 * - `max`: int, maximum number of characters allowed
 * - `min`: int, minimum number of characters required if the field isn't empty
 * - `placeholder`: string, placeholder text for the input field
 * - `required`: boolean, if true, the field must not be empty
 */
export default {
  extends: fieldBase,

  computed: {
    rules() {
      return [
        required(this.$gettext, this.config.required),
        minChars(this.$ngettext, this.config.min),
        maxChars(this.$ngettext, this.config.max)
      ]
    }
  }
}
</script>

<template>
  <v-textarea
    :hint="config.hint && $pgettext('fh', config.hint)"
    :rules="rules"
    :readonly="readonly"
    :placeholder="config.placeholder || ''"
    :modelValue="modelValue ?? config.default ?? ''"
    @update:modelValue="$emit('update:modelValue', $event)"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
    class="ltr"
    clearable
  ></v-textarea>
</template>
