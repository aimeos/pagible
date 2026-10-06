/**
 * @license MIT, https://opensource.org/license/mit
 */

/**
 * Shared options of the simple field components, used with "extends"
 * The fields implement the "rules" computed property and override "modelValue" if its type differs
 */
export const fieldBase = {
  props: {
    modelValue: { type: String },
    config: { type: Object, default: () => ({}) },
    assets: { type: Object, default: () => ({}) },
    readonly: { type: Boolean, default: false },
    context: { type: Object }
  },

  emits: ['update:modelValue', 'error'],

  computed: {
    hasError() {
      const val = this.modelValue ?? this.config.default ?? ''
      return !this.rules.every((rule) => rule(val) === true)
    },

    rules() {
      return []
    }
  },

  watch: {
    hasError: {
      immediate: true,
      handler(value) {
        this.$emit('error', value)
      }
    }
  }
}
