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
          v => !this.config.required || !!v || this.$gettext(`Selection is required`)
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
  <v-radio-group
    :rules="rules"
    :readonly="readonly"
    :modelValue="modelValue"
    @update:modelValue="$emit('update:modelValue', $event)"
    hide-details="auto"
  ><v-radio v-for="option in (config.options || [])"
      :label="option.label"
      :value="option.value">
    </v-radio>
  </v-radio-group>
</template>
