/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

<script>
  import { VDateInput } from 'vuetify/labs/VDateInput'

  export default {
    components: {
      VDateInput,
    },

    props: {
      'modelValue': {type: [Array, Date, String, null]},
      'config': {type: Object, default: () => {}},
      'assets': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    emits: ['update:modelValue', 'error'],

    computed: {
      rules() {
        return [
          v => !this.config.required || !!v || this.$gettext(`Value is required`),
        ]
      }
    },

    watch: {
      modelValue: {
        immediate: true,
        handler(val) {
          this.$emit('error', !this.rules.every(rule => {
            return rule(val ?? this.config.default ?? null) === true
          }))
        }
      }
    }
  }
</script>

<template>
  <v-date-input
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
