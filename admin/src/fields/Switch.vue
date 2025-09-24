<script>
  export default {
    props: {
      'modelValue': {type: Boolean},
      'config': {type: Object, default: () => {}},
      'assets': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    emits: ['update:modelValue', 'error']
  }
</script>

<template>
  <v-switch
    :false-value="config.off ?? false"
    :true-value="config.on ?? true"
    :readonly="readonly"
    :modelValue="modelValue ?? config.default ?? false"
    @update:modelValue="$emit('update:modelValue', $event)"
    hide-details="auto"
    color="primary"
    inset
  ></v-switch>
</template>
