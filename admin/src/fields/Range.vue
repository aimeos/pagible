<script>
  export default {
    props: {
      'modelValue': {type: Array},
      'config': {type: Object, default: () => {}},
      'assets': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    emits: ['update:modelValue', 'error']
  }
</script>

<template>
  <v-range-slider
    :thumb-label="true"
    :step="config.step ?? 1"
    :max="config.max ?? 100"
    :min="config.min ?? 0"
    :readonly="readonly"
    :modelValue="modelValue ?? config.default ?? []"
    @update:modelValue="$emit('update:modelValue', $event)"
    hide-details="auto"
  >
    <template #prepend>
      <span>{{ modelValue?.[0] ?? '' }}</span>
    </template>
    <template #append>
      <span>{{ modelValue?.[1] ?? '' }}</span>
    </template>
  </v-range-slider>
</template>
