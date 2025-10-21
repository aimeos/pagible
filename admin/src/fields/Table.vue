/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

<script>
  import { vDraggable } from 'vue-draggable-plus'

  export default {
    directives: { draggable: vDraggable },

    props: {
      modelValue: { type: Array, default: () => [] },
      config: { type: Object, default: () => ({}) },
      readonly: { type: Boolean, default: false },
      context: { type: Object },
    },

    emits: ['update:modelValue', 'error'],

    inject: ['debounce'],

    data() {
      return {
        columns: this.header(),
        table: this.modelValue,
        validated: null,
        updated: null,
        menu: {},
      }
    },

    created() {
      if (!this.table.length) {
        return this.$emit('update:modelValue', this.config.default ?? [['']])
      }

      this.validated = this.debounce(this.validate, 500)
      this.updated = this.debounce(this.update, 500)
    },

    computed: {
      cols() {
        return this.columns.filter(c => !!c)
      },


      rules() {
        return [
          v => !this.config.min || +v?.length >= +this.config.min || this.$gettext(`Minimum are %{num} columns`, { num: this.config.min }),
          v => !this.config.max || +v?.length <= +this.config.max || this.$gettext(`Maximum are %{num} columns`, { num: this.config.max }),
        ]
      },
    },

    methods: {
      addCol(index) {
        this.columns.splice(index + 1, 0, true)
        this.table.forEach(row => row.splice(index, 0, ''))
        this.$emit('update:modelValue', this.table)
      },


      addRow(index) {
        this.table.splice(index, 0, this.cols.map(() => ''))
        this.$emit('update:modelValue', this.table)
      },


      header() {
        const cols = (this.modelValue[0] || []).map((_, i) => true)

        cols.unshift(null)
        cols.push(null)

        return cols
      },


      move(ev) {
        if(ev.oldIndex === ev.newIndex) return

        this.table.forEach(row => {
          row.splice(ev.newIndex - 1, 0, row.splice(ev.oldIndex - 1, 1)[0])
        })

        this.$emit('update:modelValue', this.table)
      },


      rmCol(index) {
        if (this.cols.length <= 1) return

        this.columns.splice(index, 1)
        this.table.forEach(row => row.splice(index, 1))

        this.$emit('update:modelValue', this.table)
      },


      rmRow(index) {
        if (this.table.length <= 1) return

        this.table.splice(index, 1)
        this.$emit('update:modelValue', this.table)
      },


      update() {
        this.$emit('update:modelValue', this.table)
      },


      validate(val) {
        this.$emit('error', !this.rules.every(rule => rule(val) === true))
      },
    },

    watch: {
      modelValue: {
        handler(val) {
          this.table = val
          this.validated ? this.validated(val) : this.validate(val)
          this.columns = this.header()
        },
      },
    },
  }
</script>

<template>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr v-draggable="[columns, { animation: 300, handle: '.col-handle', onUpdate: move }]" class="col-header">
          <td></td>

          <td v-for="(col, idx) in cols" :key="idx">
            <v-btn variant="text" class="col-handle cursor-move" icon>
              <svg xmlns="http://www.w3.org/2000/svg" height="24" width="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3,15V13H5V15H3M3,11V9H5V11H3M7,15V13H9V15H7M7,11V9H9V11H7M11,15V13H13V15H11M11,11V9H13V11H11M15,15V13H17V15H15M15,11V9H17V11H15M19,15V13H21V15H19M19,11V9H21V11H19Z" />
              </svg>
            </v-btn>

            <component :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
              v-if="!readonly"
              v-model="menu['col-' + idx]"
              transition="scale-transition"
              location="start center"
              max-width="300">

              <template #activator="{ props }">
                <v-btn
                  v-bind="props"
                  :title="$gettext('Actions')"
                  icon="mdi-dots-vertical"
                  variant="text"
                />
              </template>

              <v-card>
                <v-toolbar density="compact">
                  <v-toolbar-title>{{ $gettext('Actions') }}</v-toolbar-title>
                  <v-btn icon="mdi-close" @click="menu['col-' + idx] = false" />
                </v-toolbar>

                <v-list @click="menu['col-' + idx] = false">
                  <v-list-item>
                    <v-btn prepend-icon="mdi-table-column-plus-before" variant="text" @click="addCol(idx)">{{ $gettext('Insert before') }}</v-btn>
                  </v-list-item>
                  <v-list-item>
                    <v-btn prepend-icon="mdi-table-column-plus-after" variant="text" @click="addCol(idx+1)">{{ $gettext('Insert after') }}</v-btn>
                  </v-list-item>
                  <v-list-item v-if="cols.length > 1">
                    <v-btn prepend-icon="mdi-delete" variant="text" @click="rmCol(idx)">{{ $gettext('Delete') }}</v-btn>
                  </v-list-item>
                </v-list>
              </v-card>
            </component>
          </td>

          <td></td>
        </tr>
      </thead>

      <tbody v-draggable="[table, { animation: 300, handle: '.row-handle' }]">
        <tr v-for="(row, rowidx) in table" :key="rowidx">
          <td>
            <v-btn icon="mdi-drag-horizontal" variant="text" class="row-handle cursor-move">
              <svg xmlns="http://www.w3.org/2000/svg" height="24" width="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M9,3H11V5H9V3M13,3H15V5H13V3M9,7H11V9H9V7M13,7H15V9H13V7M9,11H11V13H9V11M13,11H15V13H13V11M9,15H11V17H9V15M13,15H15V17H13V15M9,19H11V21H9V19M13,19H15V21H13V19Z" />
              </svg>
            </v-btn>
          </td>

          <td v-for="(col, colidx) in cols" :key="colidx">
            <v-textarea
              v-model="table[rowidx][colidx]"
              @input="updated()"
              variant="plain"
              rows="1"
              auto-grow
              hide-details
            />
          </td>

          <td>
            <component :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
              v-if="!readonly"
              v-model="menu['row-' + rowidx]"
              transition="scale-transition"
              location="start center"
              max-width="300">

              <template #activator="{ props }">
                <v-btn
                  v-bind="props"
                  :title="$gettext('Actions')"
                  icon="mdi-dots-vertical"
                  variant="text"
                />
              </template>

              <v-card>
                <v-toolbar density="compact">
                  <v-toolbar-title>{{ $gettext('Actions') }}</v-toolbar-title>
                  <v-btn icon="mdi-close" @click="menu['row-' + rowidx] = false" />
                </v-toolbar>

                <v-list @click="menu['row-' + rowidx] = false">
                  <v-list-item>
                    <v-btn prepend-icon="mdi-table-row-plus-before" variant="text" @click="addRow(rowidx)">{{ $gettext('Insert before') }}</v-btn>
                  </v-list-item>
                  <v-list-item>
                    <v-btn prepend-icon="mdi-table-row-plus-after" variant="text" @click="addRow(rowidx+1)">{{ $gettext('Insert after') }}</v-btn>
                  </v-list-item>
                  <v-list-item v-if="table.length > 1">
                    <v-btn prepend-icon="mdi-delete" variant="text" @click="rmRow(rowidx)">{{ $gettext('Delete') }}</v-btn>
                  </v-list-item>
                </v-list>
              </v-card>
            </component>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
  .table-wrapper {
    overflow-x: auto;
  }

  table {
    border-collapse: collapse;
    width: 100%;
  }

  thead tr,
  tbody tr {
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-medium-emphasis-opacity));
  }

  td:not(:last-child) {
    border-inline-end: 1px solid rgba(var(--v-border-color), var(--v-medium-emphasis-opacity));
  }

  td {
    word-break: break-word;
    vertical-align: top;
    text-align: center;
    min-width: 100px;
  }

  td:first-of-type,
  td:last-of-type {
    min-width: 50px;
    width: 50px;
  }

  .cursor-move {
    cursor: move;
  }

  .v-textarea {
    height: 100%;
  }

  .v-textarea :deep(.v-field__input) {
    --v-field-input-padding-bottom: 4px;
    --v-field-input-padding-top: 12px;
    --v-field-padding-start: 8px;
    --v-field-padding-end: 8px;
    -webkit-mask-image: none;
    mask-image: none;
  }
</style>
