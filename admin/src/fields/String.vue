<script>
  /**
   * Configuration:
   * - `max`: int, maximum number of characters allowed in the input field
   * - `min`: int, minimum number of characters required in the input field
   * - `placeholder`: string, placeholder text for the input field
   * - `class`: string, CSS class to apply to the input field
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
    :readonly="readonly"
    :class="config.class"
    :counter="config.max"
    :clearable="!readonly"
    :placeholder="config.placeholder || ''"
    :rules="[
      v => !config.min || +v?.length >= +config.min || $gettext(`Minimum length is %{num} characters`, {num: config.min}),
      v => !config.max || +v?.length <= +config.max || $gettext(`Maximum length is %{num} characters`, {num: config.max})
    ]"
    :modelValue="modelValue"
    @update:modelValue="update($event)"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
  ></v-text-field>
</template>
