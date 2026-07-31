/** @license MIT, https://opensource.org/license/mit */

<script>
/**
 * Configuration:
 * - `max`: number, maximum value allowed in the input field
 * - `min`: number, minimum value allowed in the input field
 * - `placeholder`: string, placeholder text for the input field
 * - `precision`: int, maximum number of fractional digits
 * - `required`: boolean, if true, the field is required
 * - `step`: number, step size for the number input
 */
export default {
  props: {
    modelValue: { type: Number },
    config: { type: Object, default: () => {} },
    assets: { type: Object, default: () => {} },
    readonly: { type: Boolean, default: false },
    context: { type: Object }
  },

  emits: ['update:modelValue', 'error'],

  data: () => ({ lastError: null }),

  computed: {
    hasError() {
      const val = this.modelValue ?? this.config.default
      return !this.rules.every((rule) => rule(val) === true)
    },

    rules() {
      return [(v) => !this.config.required || !!v || this.$gettext(`Value is required`)]
    }
  },

  watch: {
    modelValue: {
      immediate: true,
      handler(val) {
        const hasError = !this.rules.every((rule) => rule(val ?? this.config.default) === true)
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
  <v-number-input
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
