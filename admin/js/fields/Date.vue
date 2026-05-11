/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import { VDateInput } from 'vuetify/labs/VDateInput'

export default {
  components: {
    VDateInput
  },

  props: {
    modelValue: { type: [Array, Date, String, null] },
    config: { type: Object, default: () => {} },
    assets: { type: Object, default: () => {} },
    readonly: { type: Boolean, default: false },
    context: { type: Object }
  },

  emits: ['update:modelValue', 'error'],

  data: () => ({ lastError: null }),

  computed: {
    hasError() {
      const val = this.modelValue ?? this.config.default ?? null
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
        const hasError = !this.rules.every((rule) => rule(val ?? this.config.default ?? null) === true)
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
  <v-date-input
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
