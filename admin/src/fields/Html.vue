/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

<script>
  export default {
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
          v => !!v || this.$gettext(`Value is required`),
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
  <v-textarea
    :rules="rules"
    :readonly="readonly"
    :placeholder="config.placeholder || ''"
    :modelValue="modelValue ?? config.default ?? ''"
    @update:modelValue="$emit('update:modelValue', $event)"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
    class="ltr"
    clearable
  ></v-textarea>
</template>
