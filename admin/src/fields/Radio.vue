<script>
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
      },


      async validate() {
        return await true
      }
    }
  }
</script>

<template>
  <v-radio-group
    :rules="[
      v => !config.required || !!v || $gettext(`Selection is required`)
    ]"
    :readonly="readonly"
    :modelValue="modelValue"
    @update:modelValue="update($event)"
    hide-details="auto"
  ><v-radio v-for="option in (config.options || [])"
      :label="option.label"
      :value="option.value">
    </v-radio>
  </v-radio-group>
</template>
