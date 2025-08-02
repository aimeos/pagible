<script>
  /**
   * Configuration:
   * - `max`: int, maximum number of characters allowed in the input field
   * - `min`: int, minimum number of characters required in the input field
   * - `placeholder`: string, placeholder text for the input field
   * - `required`: boolean, if true, the field is required
   * - `step`: int, step size for the number input
   */
   export default {
    props: {
      'modelValue': {type: Number, default: 0},
      'config': {type: Object, default: () => {}},
      'assets': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    emits: ['update:modelValue', 'error'],

    methods: {
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
  <v-number-input ref="field"
    :rules="[
      v => !config.required || !!v || $gettext(`Value is required`)
    ]"
    :readonly="readonly"
    :clearable="!readonly && !config.required"
    :max="config.max"
    :min="config.min"
    :placeholder="config.placeholder || ''"
    :step="config.step || 1"
    :modelValue="modelValue || config.default || 0"
    @update:modelValue="update($event)"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
  ></v-number-input>
</template>
