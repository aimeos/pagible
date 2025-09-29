<script>
  export default {
    props: {
      'modelValue': {type: Array, default: () => []},
      'config': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    emits: ['update:modelValue', 'error'],

    inject: ['debounce'],

    created() {
      this.validated = this.debounce(this.validate, 500)
    },

    computed: {
      rules() {
        return [
            v => !this.config.min || +v?.length >= +this.config.min || this.$gettext(`Minimum are %{num} columns`, {num: this.config.min}),
            v => !this.config.max || +v?.length <= +this.config.max || this.$gettext(`Maximum are %{num} columns`, {num: this.config.max}),
        ]
      }
    },

    methods: {
      addCol(idx) {
        for(let row of this.modelValue) {
          row.splice(idx, 0, '')
        }

        this.$emit('update:modelValue', this.modelValue)
      },


      addRow(idx) {
        this.modelValue.splice(idx, 0, new Array((this.modelValue[0] ?? []).length).fill(''))
        this.$emit('update:modelValue', this.modelValue)
      },


      rmCol(idx) {
        for(let row of this.modelValue) {
          row.splice(idx, 1)
        }

        this.$emit('update:modelValue', this.modelValue)
      },


      rmRow(idx) {
        this.modelValue.splice(idx, 1)
        this.$emit('update:modelValue', this.modelValue)
      },


      validate(val) {
        this.$emit('error', !this.rules.every(rule => {
          return rule(val) === true
        }))

        this.$emit('update:modelValue', val)
      }
    },

    watch: {
      modelValue: {
        deep: true,
        immediate: true,
        handler(val) {
          if(!val.length) {
            return this.$emit('update:modelValue', this.config.default ?? [['']])
          }

          this.validated ? this.validated(val) : this.validate(val);
        }
      }
    }
  }
</script>

<template>
  <v-table>
    <tbody>
      <tr>
        <td></td>
        <td v-for="(col, idx) in modelValue[0] || []">
          <div class="buttons">
            <v-btn icon="mdi-plus" @click="addCol(idx)" variant="flat"></v-btn>
            <v-btn icon="mdi-minus" @click="rmCol(idx)" variant="flat"></v-btn>
            <div></div>
          </div>
        </td>
        <td>
          <v-btn icon="mdi-plus" @click="addCol((modelValue[0] || []).length)" variant="flat"></v-btn>
        </td>
      </tr>
      <tr v-for="(row, i) in modelValue">
        <td>
          <v-btn icon="mdi-plus" @click="addRow(i)" variant="flat"></v-btn>
        </td>
        <td v-for="(col, j) in row">
          <v-textarea
            v-model="modelValue[i][j]"
            :placeholder="config.placeholder || ''"
            :readonly="readonly"
            :auto-grow="true"
            rows="1"
            variant="plain"
            hide-details="auto"
            density="comfortable"
          ></v-textarea>
        </td>
        <td>
          <v-btn icon="mdi-minus" @click="rmRow(i)" variant="flat"></v-btn>
        </td>
      </tr>
      <tr>
        <td>
          <v-btn icon="mdi-plus" @click="addRow((modelValue[0] || []).length)" variant="flat"></v-btn>
        </td>
        <td :colspan="(modelValue[0] || []).length"></td>
        <td></td>
      </tr>
    </tbody>
  </v-table>
</template>

<style scoped>
  .v-table .v-table__wrapper > table > tbody > tr > td {
    border: none;
  }

  .v-table td:first-child,
  .v-table td:last-child {
    vertical-align: top;
    width: 48px;
  }

  .v-table .v-table__wrapper > table > tbody > tr:not(:first-child):not(:last-child) > td:not(:first-child):not(:last-child) {
    border: thin solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  }

  .v-table .v-table__wrapper > table > tbody > :where(tr:first-child, tr:last-child) > td,
  .v-table .v-table__wrapper > table > tbody > tr > :where(td:first-child, td:last-child) {
    padding: 0;
  }

  .v-table .buttons {
    display: flex;
    justify-content: space-between;
  }

  .v-table tr td:first-child .buttons {
    flex-direction: column;
    height: 100%;
  }

  .v-textarea {
    height: 100%;
  }
</style>