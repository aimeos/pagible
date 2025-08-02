<script>
  export default {
    props: {
      'modelValue': {type: String, default: ''},
      'config': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    emits: ['update:modelValue', 'error'],

    methods: {
      check(value) {
        let lines = 0
        let columns = 0

        value.split('\n').forEach(line => {
          columns += line.split(';').length
          lines++
        })

        return lines ? Number.isInteger(columns / lines) : true
      },


      update(value) {
        this.$emit('update:modelValue', value.replace(/(\r)+/g, '').replace(/^\n+/, '').replace(/\n{2,}$/g, "\n"))
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
  <v-textarea ref="field"
    :rules="[
      v => !config.required || !!v || $gettext('Field is required'),
      v => !config.min || +v?.split('\n')[0]?.split(';')?.length >= +config.min || $gettext(`Minimum are %{num} columns`, {num: config.min}),
      v => check(v) || $gettext('The number of columns is not the same in all rows'),
    ]"
    :auto-grow="true"
    :readonly="readonly"
    :placeholder="config.placeholder || `val;val;val\nval;val;val`"
    :modelValue="modelValue"
    @update:modelValue="update($event)"
    variant="outlined"
    hide-details="auto"
    density="comfortable"
    clearable
  ></v-textarea>
</template>
