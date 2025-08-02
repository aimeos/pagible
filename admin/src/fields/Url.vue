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

    methods: {
      check(v) {
        const allowed = this.config.allowed || ['http', 'https']

        if(!allowed.every(s => /^[a-z]+/.test(s))) {
          return this.$gettext('Invalid URL schema configuration')
        }

        return v
          ? (new RegExp(`^((${allowed.join('|')}:\/\/)?([^\/\s@:]+(:[^\/\s@:]+)?@)?([0-9a-z]+[.-])*[0-9a-z]+\.[a-z]{2,}(:[0-9]{1,5})?)?(\/.*)?$`)).test(v)
          : true
      },

      update(value) {
        this.$emit('update:modelValue', value)
        this.validate()
      },


      async validate() {
        await this.$nextTick()
        const errors = await this.$refs.field?.validate()

        this.$emit('error', errors?.length > 0)
        return !errors?.length
      }
    },

    watch: {
      modelValue: {
        immediate: true,
        handler(val) {
          this.validate()
        }
      }
    }
  }
</script>

<template>
  <v-text-field ref="field"
    :placeholder="config.placeholder || ''"
    :rules="[
      v => !config.required || !!v || $gettext(`Value is required`),
      v => check(v) || $gettext(`Not a valid URL`),
    ]"
    :readonly="readonly"
    :modelValue="modelValue"
    @update:modelValue="update($event)"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
    class="ltr"
    clearable
  ></v-text-field>
</template>
