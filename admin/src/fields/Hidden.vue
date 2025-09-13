<script>
  export default {
    props: {
      'modelValue': {type: String, default: ''},
      'config': {type: Object, default: () => {}},
      'assets': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    created() {
      if(this.modelValue !== this.config?.value) {
        this.$emit('update:modelValue', this.config?.value)
      }
    }
  }
</script>

<template>
</template>
