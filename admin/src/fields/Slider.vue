<script>
  export default {
    props: {
      'modelValue': {type: Number, default: 0},
      'config': {type: Object, default: () => {}},
      'assets': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    emits: ['update:modelValue', 'error']
  }
</script>

<template>
  <v-slider
    :thumb-label="true"
    :step="config.step ?? 1"
    :max="config.max ?? 100"
    :min="config.min ?? 0"
    :readonly="readonly"
    :modelValue="modelValue"
    @update:modelValue="$emit('update:modelValue', $event)"
    hide-details="auto"
  >
    <template #prepend>
      <span></span>
    </template>
    <template #append>
      <span>{{ modelValue }}</span>
    </template>
  </v-slider>
</template>
