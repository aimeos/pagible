<script>
  import { VDateInput } from 'vuetify/labs/VDateInput'

  export default {
    components: {
      VDateInput,
    },

    props: {
      'modelValue': {type: [Array, Date, String, null], default: null},
      'config': {type: Object, default: () => {}},
      'assets': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    emits: ['update:modelValue', 'error'],

    methods: {
      update(value) {
        this.$emit('update:modelValue', value)
        this.validate()
      },


      async validate() {
        await this.$nextTick()
        const errors = await this.$refs.field?.validate()

        this.$emit('error', errors?.length > 0)
        return !errors?.length
      }
    },

    watch: {
      modelValue: {
        immediate: true,
        handler(val) {
          this.validate()
        }
      }
    }
  }
</script>

<template>
  <v-date-input ref="field"
    :rules="[
      v => !config.required || !!v || $gettext(`Value is required`),
    ]"
    :readonly="readonly"
    :allowed-dates="config.allowed"
    :clearable="!readonly && !config.required"
    :max="config.max"
    :min="config.min"
    :multiple="config.multiple"
    :placeholder="config.placeholder || null"
    :modelValue="modelValue"
    @update:modelValue="update($event)"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
    show-adjacent-months
  ></v-date-input>
</template>
