/** @license MIT, https://opensource.org/license/mit */

<script>
import { minChars, maxChars, required } from '../rules'

/**
 * Configuration:
 * - `hint`: string, description shown below the field while it has focus
 * - `max`: int, maximum number of characters allowed in the input field
 * - `min`: int, minimum number of characters required if the field isn't empty
 * - `placeholder`: string, placeholder text for the input field
 * - `class`: string, CSS class to apply to the input field
 * - `pattern`: string, regular expression the value must match
 * - `required`: boolean, if true, the field must not be empty
 * - `uppercase`: boolean, normalize input to uppercase
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
        maxChars(this.$ngettext, this.config.max),
        (v) =>
          !this.config.pattern ||
          !v ||
          new RegExp(this.config.pattern).test(v) ||
          this.$gettext(`Value has invalid format`)
      ]
    }
  },

  methods: {
    update(value) {
      this.$emit('update:modelValue', this.config.uppercase ? value?.toUpperCase() : value)
    }
  },

  watch: {
    modelValue: {
      immediate: true,
      handler(val) {
        const hasError = !this.rules.every(
          (rule) => rule(val ?? this.config.default ?? '') === true
        )
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
    :hint="config.hint && $pgettext('fh', config.hint)"
    :error="hasError"
    :rules="rules"
    :readonly="readonly"
    :class="config.class"
    :counter="config.max"
    :persistent-counter="!!config.max"
    :clearable="!readonly"
    :placeholder="config.placeholder || ''"
    :modelValue="modelValue ?? config.default ?? ''"
    @update:modelValue="update"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
    auto-grow
    rows="1"
  ></v-textarea>
</template>
