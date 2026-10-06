/** @license MIT, https://opensource.org/license/mit */

<script>
import {
  mdiDotsVertical,
  mdiDragHorizontal,
  mdiDragVertical,
  mdiTableColumnPlusBefore,
  mdiTableColumnPlusAfter,
  mdiDelete,
  mdiTableRowPlusBefore,
  mdiTableRowPlusAfter
} from '@mdi/js'
import { vDraggable } from 'vue-draggable-plus'
import ActionMenu from '../components/ActionMenu.vue'
import { minColumns, maxColumns } from '../rules'
import { debounce } from '../utils'

export default {
  components: { ActionMenu },

  directives: { draggable: vDraggable },

  setup() {
    return {
      mdiDotsVertical,
      mdiDragHorizontal,
      mdiDragVertical,
      mdiTableColumnPlusBefore,
      mdiTableColumnPlusAfter,
      mdiDelete,
      mdiTableRowPlusBefore,
      mdiTableRowPlusAfter,
      debounce
    }
  },

  props: {
    modelValue: { type: Array, default: () => [] },
    config: { type: Object, default: () => ({}) },
    readonly: { type: Boolean, default: false },
    context: { type: Object }
  },

  emits: ['update:modelValue', 'error'],

  data() {
    return {
      columns: this.header(),
      lastError: null,
      table: this.modelValue
    }
  },

  created() {
    this.validated = this.debounce(this.validate, 500)
    this.updated = this.debounce(this.update, 500)

    if (!this.table.length) {
      this.$emit('update:modelValue', this.config.default ?? [['']])
    }
  },

  computed: {
    cols() {
      return this.columns.filter((c) => !!c)
    },

    rules() {
      return [
        minColumns(this.$ngettext, this.config.min),
        maxColumns(this.$ngettext, this.config.max)
      ]
    }
  },

  methods: {
    addCol(index) {
      this.columns.splice(index + 1, 0, true)
      this.table.forEach((row) => row.splice(index, 0, ''))
      this.$emit('update:modelValue', this.table)
    },

    addRow(index) {
      this.table.splice(
        index,
        0,
        this.cols.map(() => '')
      )
      this.$emit('update:modelValue', this.table)
    },

    header() {
      const size = this.modelValue.reduce((max, row) => Math.max(max, row.length), 0)
      const cols = Array(size).fill(true)

      cols.unshift(null)
      cols.push(null)

      return cols
    },

    move(ev) {
      if (ev.oldIndex === ev.newIndex) return

      this.table.forEach((row) => {
        row.splice(ev.newIndex - 1, 0, row.splice(ev.oldIndex - 1, 1)[0])
      })

      this.$emit('update:modelValue', this.table)
    },

    rmCol(index) {
      if (this.cols.length <= 1) return

      this.columns.splice(index, 1)
      this.table.forEach((row) => row.splice(index, 1))

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
      const hasError = !this.rules.every((rule) => rule(val) === true)
      if (hasError !== this.lastError) {
        this.lastError = hasError
        this.$emit('error', hasError)
      }
    }
  },

  watch: {
    modelValue: {
      handler(val) {
        this.table = val
        this.validated(val)
        this.columns = this.header()
      }
    }
  }
}
</script>

<template>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr
          v-draggable="[columns, { animation: 300, handle: '.col-handle', onUpdate: move }]"
          class="col-header"
        >
          <td></td>

          <td v-for="(col, idx) in cols" :key="idx">
            <v-btn
              variant="text"
              class="col-handle cursor-move"
              :aria-label="$gettext('Move column')"
              :icon="mdiDragHorizontal"
            />

            <span class="btn-actions" v-if="!readonly">
              <ActionMenu :title="$gettext('Column actions')" location="start center">
                <template #activator="{ props, label }">
                  <v-btn v-bind="props" :title="label" :icon="mdiDotsVertical" variant="text" />
                </template>

                <v-list-item
                  v-for="item in [
                    { icon: mdiTableColumnPlusBefore, label: $gettext('Insert before'), fn: () => addCol(idx) },
                    { icon: mdiTableColumnPlusAfter, label: $gettext('Insert after'), fn: () => addCol(idx + 1) },
                    { icon: mdiDelete, label: $gettext('Remove'), fn: () => rmCol(idx), hide: cols.length <= 1 }
                  ].filter((item) => !item.hide)"
                  :key="item.icon"
                >
                  <v-btn :prepend-icon="item.icon" variant="text" @click="item.fn()">{{
                    item.label
                  }}</v-btn>
                </v-list-item>
              </ActionMenu>
            </span>
          </td>

          <td></td>
        </tr>
      </thead>

      <tbody v-draggable="[table, { animation: 300, handle: '.row-handle', onUpdate: update }]">
        <tr v-for="(row, rowidx) in table" :key="rowidx">
          <td>
            <v-btn
              :aria-label="$gettext('Move row')"
              variant="text"
              class="row-handle cursor-move"
              :icon="mdiDragVertical"
            />
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
            <span class="btn-actions" v-if="!readonly">
              <ActionMenu :title="$gettext('Row actions')" location="start center">
                <template #activator="{ props, label }">
                  <v-btn v-bind="props" :title="label" :icon="mdiDotsVertical" variant="text" />
                </template>

                <v-list-item
                  v-for="item in [
                    { icon: mdiTableRowPlusBefore, label: $gettext('Insert before'), fn: () => addRow(rowidx) },
                    { icon: mdiTableRowPlusAfter, label: $gettext('Insert after'), fn: () => addRow(rowidx + 1) },
                    { icon: mdiDelete, label: $gettext('Remove'), fn: () => rmRow(rowidx), hide: table.length <= 1 }
                  ].filter((item) => !item.hide)"
                  :key="item.icon"
                >
                  <v-btn :prepend-icon="item.icon" variant="text" @click="item.fn()">{{
                    item.label
                  }}</v-btn>
                </v-list-item>
              </ActionMenu>
            </span>
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
