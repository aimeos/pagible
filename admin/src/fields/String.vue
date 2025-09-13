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

    computed: {
      rules() {
        return [
          v => !this.config.min || +v?.length >= +this.config.min || this.$gettext(`Minimum length is %{num} characters`, {num: this.config.min}),
          v => !this.config.max || +v?.length <= +this.config.max || this.$gettext(`Maximum length is %{num} characters`, {num: this.config.max})
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
    :class="config.class"
    :counter="config.max"
    :clearable="!readonly"
    :modelValue="modelValue"
    :placeholder="config.placeholder || ''"
    @update:modelValue="$emit('update:modelValue', $event)"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
    auto-grow
    rows="1"
  ></v-textarea>
</template>
