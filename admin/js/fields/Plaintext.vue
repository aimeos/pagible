/** @license MIT, https://opensource.org/license/mit */

<script>
import { minChars, maxChars, required } from '../rules'

/**
 * Configuration:
 * - `max`: int, maximum number of characters allowed in the input field
 * - `min`: int, minimum number of characters required if the field isn't empty
 * - `placeholder`: string, placeholder text for the input field
 * - `class`: string, CSS class to apply to the input field
 * - `required`: boolean, if true, the field must not be empty
 */
export default {
  props: {
    modelValue: { type: String },
    config: { type: Object, default: () => {} },
    assets: { type: Object, default: () => {} },
    readonly: { type: Boolean, default: false },
    context: { type: Object }
  },

  emits: ['update:modelValue', 'error'],

  data: () => ({ lastError: null }),

  computed: {
    hasError() {
      const val = this.modelValue ?? this.config.default ?? ''
      return !this.rules.every((rule) => rule(val) === true)
    },

    rules() {
      return [
        required(this.$gettext, this.config.required),
        minChars(this.$ngettext, this.config.min),
        maxChars(this.$ngettext, this.config.max)
      ]
    }
  },

  watch: {
    modelValue: {
      immediate: true,
      handler(val) {
        const hasError = !this.rules.every((rule) => rule(val ?? this.config.default ?? '') === true)
        if (hasError !== this.lastError) {
          this.lastError = hasError
          this.$emit('error', hasError)
        }
      }
    }
  }
}
</script>

<template>
  <v-textarea
    :error="hasError"
    :rules="rules"
    :class="config.class"
    :readonly="readonly"
    :counter="config.max"
    :persistent-counter="!!config.max"
    :placeholder="config.placeholder || ''"
    :modelValue="modelValue ?? config.default ?? ''"
    @update:modelValue="$emit('update:modelValue', $event)"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
    clearable
  ></v-textarea>
</template>
