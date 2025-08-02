<script>
  import { VColorInput } from 'vuetify/labs/VColorInput'

  export default {
    components: {
      VColorInput,
    },

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
      },


      async validate() {
        return await true
      }
    }
  }
</script>

<template>
  <v-color-input
    :rules="[
      v => !config.required || !!v || $gettext(`Value is required`),
      v => !v || /^#[0-9A-F]{6,8}$/i.test(v) || $gettext(`Value must be a hex color code`),
    ]"
    :clearable="!readonly"
    :disabled="readonly"
    :modelValue="modelValue"
    @update:modelValue="update($event)"
  ></v-color-input>
</template>
