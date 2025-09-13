<script>
  export default {
    props: {
      'modelValue': {type: String, default: ''},
      'config': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    emits: ['update:modelValue', 'error'],

    computed: {
      rules() {
        return [
            v => !this.config.required || !!v || this.$gettext('Field is required'),
            v => !this.config.min || +v?.split('\n')[0]?.split(';')?.length >= +this.config.min || this.$gettext(`Minimum are %{num} columns`, {num: this.config.min}),
            v => !this.config.max || +v?.split('\n')[0]?.split(';')?.length <= +this.config.max || this.$gettext(`Maximum are %{num} columns`, {num: this.config.max}),
            v => this.check(v) || this.$gettext('The number of columns is not the same in all rows'),
        ]
      }
    },

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
        this.$emit('update:modelValue', value?.replace(/(\r)+/g, '')?.replace(/^\n+/, '')?.replace(/\n{2,}$/g, "\n"))
      }
    },

    watch: {
      modelValue: {
        immediate: true,
        handler(val) {
          this.$emit('error', !this.rules.every(rule => {
            return rule(this.modelValue) === true
          }))
        }
      }
    }
  }
</script>

<template>
  <v-textarea
    :rules="rules"
    :auto-grow="true"
    :readonly="readonly"
    :modelValue="modelValue"
    :placeholder="config.placeholder || `val;val;val\nval;val;val`"
    @update:modelValue="update($event)"
    variant="outlined"
    hide-details="auto"
    density="comfortable"
    clearable
  ></v-textarea>
</template>
