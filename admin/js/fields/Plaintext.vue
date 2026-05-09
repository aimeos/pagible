/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
/**
 * Configuration:
 * - `max`: int, maximum number of characters allowed in the input field
 * - `min`: int, minimum number of characters required in the input field
 * - `placeholder`: string, placeholder text for the input field
 * - `class`: string, CSS class to apply to the input field
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
        (v) =>
          !this.config.min ||
          +v?.length >= +this.config.min ||
          this.$gettext(`Minimum length is %{num} characters`, { num: this.config.min }),
        (v) =>
          !this.config.max ||
          +v?.length <= +this.config.max ||
          this.$gettext(`Maximum length is %{num} characters`, { num: this.config.max })
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
    :placeholder="config.placeholder || ''"
    :modelValue="modelValue ?? config.default ?? ''"
    @update:modelValue="$emit('update:modelValue', $event)"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
    clearable
  ></v-textarea>
</template>
