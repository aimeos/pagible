<script>
  export default {
    props: {
      'modelValue': {type: Array, default: () => []},
      'config': {type: Object, default: () => {}},
      'assets': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    emits: ['update:modelValue', 'error'],

    methods: {
      update(value) {
        this.$emit('update:modelValue', value)
      },


      async validate() {
        return await true
      }
    }
  }
</script>

<template>
  <v-range-slider
    :thumb-label="true"
    :step="config.step ?? 1"
    :max="config.max ?? 100"
    :min="config.min ?? 0"
    :readonly="readonly"
    :modelValue="modelValue"
    @update:modelValue="update($event)"
    hide-details="auto"
  >
    <template #prepend>
      <span>{{ modelValue[0] }}</span>
    </template>
    <template #append>
      <span>{{ modelValue[1] }}</span>
    </template>
  </v-range-slider>
</template>
