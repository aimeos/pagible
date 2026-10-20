/** @license MIT, https://opensource.org/license/mit */

<script>
import { required } from '../rules'
import { fieldBase } from '../field'

const pad = (num) => String(num).padStart(2, '0')

// stored dates are local "YYYY-MM-DD" strings as required by the JSON schema format "date"
function toDate(value) {
  if (value instanceof Date || !value) {
    return value || null
  }

  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value))
  // older values may be timestamps, their local date is used then
  const date = match ? new Date(+match[1], +match[2] - 1, +match[3]) : new Date(value)

  return isNaN(date) ? null : date
}

function toString(value) {
  const date = toDate(value)
  return date ? `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` : null
}

export default {
  extends: fieldBase,

  props: {
    modelValue: { type: [Array, Date, String, null] }
  },

  computed: {
    dates() {
      const value = this.modelValue ?? this.config.default ?? null
      return Array.isArray(value) ? value.map(toDate).filter(Boolean) : toDate(value)
    },

    rules() {
      return [required(this.$gettext, this.config.required)]
    }
  },

  methods: {
    update(value) {
      // clearing emits an empty string so the default value isn't shown again
      const result = Array.isArray(value) ? value.map(toString).filter(Boolean) : toString(value) ?? ''
      this.$emit('update:modelValue', result)
    }
  }
}
</script>

<template>
  <v-date-input
    :hint="config.hint && $pgettext('fh', config.hint)"
    :error="hasError"
    :rules="rules"
    :readonly="readonly"
    :allowed-dates="config.allowed"
    :clearable="!readonly && !config.required"
    :max="config.max"
    :min="config.min"
    :multiple="config.multiple"
    :placeholder="config.placeholder || null"
    :modelValue="dates"
    @update:modelValue="update($event)"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
    show-adjacent-months
  ></v-date-input>
</template>
