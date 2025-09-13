<script>
  export default {
    props: {
      'modelValue': {type: String, default: ''},
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
            return rule(this.modelValue) === true
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
    :modelValue="modelValue"
    :placeholder="config.placeholder || ''"
    @update:modelValue="$emit('update:modelValue', $event)"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
    class="ltr"
    clearable
  ></v-textarea>
</template>
