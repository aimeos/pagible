/** @license MIT, https://opensource.org/license/mit */

<script>
import { required } from '../rules'
import { fieldBase } from '../field'

export default {
  extends: fieldBase,

  props: {
    modelValue: { type: [String, Array] }
  },

  computed: {
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
      return [required(this.$gettext, this.config.required)]
    }
  }
}
</script>

<template>
  <v-select
    :hint="config.hint && $pgettext('fh', config.hint)"
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
