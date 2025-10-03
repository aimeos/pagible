/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

<script>
  import { VColorInput } from 'vuetify/labs/VColorInput'

  export default {
    components: {
      VColorInput,
    },

    props: {
      'modelValue': {type: String},
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
          v => !v || /^#[0-9A-F]{6,8}$/i.test(v) || this.$gettext(`Value must be a hex color code`),
        ]
      }
    },

    watch: {
      modelValue: {
        immediate: true,
        handler(val) {
          this.$emit('error', !this.rules.every(rule => {
            return rule(val ?? this.config.default ?? '') === true
          }))
        }
      }
    }
  }
</script>

<template>
  <v-color-input
    :rules="rules"
    :clearable="!readonly"
    :disabled="readonly"
    :modelValue="modelValue ?? config.default ?? ''"
    @update:modelValue="$emit('update:modelValue', $event)"
  ></v-color-input>
</template>
