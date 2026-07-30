/** @license MIT, https://opensource.org/license/mit */

<script>
export default {
  props: {
    modelValue: { type: [String, Array] },
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

    /**
     * Returns provider-neutral option labels translated in option context.
     */
    items() {
      return (this.config.options || []).map((item) => ({
        ...item,
        label: this.$pgettext('op', item.label)
      }))
    },

    rules() {
      return [
        (v) =>
          !this.config.required ||
          (Array.isArray(v) ? v.length > 0 : !!v) ||
          this.$gettext(`Value is required`)
      ]
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
  <v-select
    :error="hasError"
    :rules="rules"
    :readonly="readonly"
    :items="items"
    :placeholder="config.placeholder || ''"
    :multiple="config.multiple"
    :chips="config.multiple"
    :modelValue="modelValue ?? config.default ?? ''"
    @update:modelValue="$emit('update:modelValue', $event)"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
    item-title="label"
  ></v-select>
</template>
