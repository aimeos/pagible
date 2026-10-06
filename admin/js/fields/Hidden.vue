/** @license MIT, https://opensource.org/license/mit */

<script>
import { fieldBase } from '../field'

export default {
  extends: fieldBase,

  props: {
    modelValue: { type: String, default: '' }
  },

  created() {
    if (this.modelValue !== this.config?.value) {
      this.$emit('update:modelValue', this.config?.value)
    }
  }
}
</script>

<template>
  <span v-if="false" />
</template>
