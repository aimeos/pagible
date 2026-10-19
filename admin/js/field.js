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

/** Field types containing text which can be translated, generated and dictated */
export const TEXT_TYPES = Object.freeze(['markdown', 'plaintext', 'string', 'text'])

/** Translated label of the schema field with the given code */
export const fieldLabel = (vm, field, code) => vm.$pgettext('fn', field?.label || code).replace(/-|_/g, ' ')

/** Field definitions of the schema type in the group (e.g. "content"), an empty list if there are none */
export function schemaFields(vm, schemas, group, type) {
  if (!type) {
    return []
  }

  const fields = schemas?.[group]?.[type]?.fields

  if (!fields) {
    vm.$log(`No definition of fields for "${type}" (${group}) schemas`)
    return []
  }

  return fields
}
