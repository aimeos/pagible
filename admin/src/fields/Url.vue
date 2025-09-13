<script>
  /**
   * Configuration:
   * - `allowed`: array of strings, allowed URL schemas (e.g., ['http', 'https'])
   * - `placeholder`: string, placeholder text for the input field
   * - `required`: boolean, if true, the field is required
   */


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
          v => !this.config.required || !!v || this.$gettext(`Value is required`),
          v => this.check(v) || this.$gettext(`Not a valid URL`),
        ]
      }
    },

    methods: {
      check(v) {
        const allowed = this.config.allowed || ['http', 'https']

        if(!allowed.every(s => /^[a-z]+/.test(s))) {
          return this.$gettext('Invalid URL schema configuration')
        }

        return v
          ? (new RegExp(`^((${allowed.join('|')}:\/\/)?([^\/\s@:]+(:[^\/\s@:]+)?@)?([0-9a-z]+[.-])*[0-9a-z]+\.[a-z]{2,}(:[0-9]{1,5})?)?(\/.*)?$`)).test(v)
          : true
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
  <v-text-field
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
  ></v-text-field>
</template>
