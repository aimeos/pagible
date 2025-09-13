<script>
  export default {
    props: {
      'modelValue': {type: Boolean, default: false},
      'config': {type: Object, default: () => {}},
      'assets': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    emits: ['update:modelValue', 'error'],
  }
</script>

<template>
  <v-checkbox
    :false-value="config.off ?? false"
    :true-value="config.on ?? true"
    :readonly="readonly"
    :modelValue="modelValue"
    @update:modelValue="$emit('update:modelValue', $event)"
    hide-details="auto"
  ></v-checkbox>
</template>
