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
import ActionItem from '../components/ActionItem.vue'
import ActionMenu from '../components/ActionMenu.vue'
import { minRows, maxRows } from '../rules'
import { debounce, moveStep, DRAG_ANIMATION, INPUT_DELAY } from '../utils'
import { fieldBase } from '../field'

// unique column IDs so moved and inserted columns keep their DOM state
let columnId = 0

export default {
  extends: fieldBase,

  components: { ActionItem, ActionMenu },

  directives: { draggable: vDraggable },

  setup() {
    return {
      DRAG_ANIMATION,
      mdiDotsVertical,
      mdiDragHorizontal,
      mdiDragVertical
    }
  },

  props: {
    modelValue: { type: Array, default: () => [] }
  },

  data() {
    return {
      columns: this.header(),
      // own working copy of the rows, the modelValue prop is never changed in place
      table: this.copy(this.modelValue)
    }
  },

  created() {
    this.updated = debounce(this.update, INPUT_DELAY)
    // stable keys for the row arrays, they move with the rows when dragging
    this.rowKeys = new WeakMap()
    this.rowSeq = 0
    // last value emitted by the field, the model watcher ignores it when it comes back
    this.emitted = null

    if (!this.table.length) {
      this.$emit('update:modelValue', this.copy(this.config.default ?? [['']]))
    }
  },

  beforeUnmount() {
    this.updated.flush() // don't lose the last edit
  },

  computed: {
    cols() {
      return this.columns.filter((c) => !!c)
    },

    rules() {
      return [
        minRows(this.$ngettext, this.config.min),
        maxRows(this.$ngettext, this.config.max)
      ]
    }
  },

  methods: {
    // entries of the column action menu
    colActions(idx) {
      return [
        { icon: mdiTableColumnPlusBefore, label: this.$gettext('Insert before'), fn: () => this.addCol(idx) },
        { icon: mdiTableColumnPlusAfter, label: this.$gettext('Insert after'), fn: () => this.addCol(idx + 1) },
        { icon: mdiDelete, label: this.$gettext('Remove'), fn: () => this.rmCol(idx), hide: this.cols.length <= 1 }
      ].filter((action) => !action.hide)
    },

    // entries of the row action menu
    rowActions(idx) {
      return [
        { icon: mdiTableRowPlusBefore, label: this.$gettext('Insert before'), fn: () => this.addRow(idx) },
        { icon: mdiTableRowPlusAfter, label: this.$gettext('Insert after'), fn: () => this.addRow(idx + 1) },
        { icon: mdiDelete, label: this.$gettext('Remove'), fn: () => this.rmRow(idx), hide: this.table.length <= 1 }
      ].filter((action) => !action.hide)
    },

    addCol(index) {
      // the first column entry is the placeholder of the row handle column
      this.columns.splice(index + 1, 0, ++columnId)
      this.table.forEach((row) => row.splice(index, 0, ''))
      this.update()
    },

    addRow(index) {
      this.table.splice(
        index,
        0,
        this.cols.map(() => '')
      )
      this.update()
    },

    copy(table) {
      return (table || []).map((row) => [...row])
    },

    header() {
      const size = this.modelValue.reduce((max, row) => Math.max(max, row.length), 0)
      // keep the IDs of the existing columns, otherwise all cells are re-created after each update
      const ids = (this.columns || []).filter(Boolean).slice(0, size)

      while (ids.length < size) {
        ids.push(++columnId)
      }

      return [null, ...ids, null]
    },

    move(ev) {
      const ids = this.columns.filter(Boolean)
      // the placeholders of the handle and action columns must stay at both ends
      const from = ev.oldIndex - 1
      const to = Math.min(Math.max(ev.newIndex - 1, 0), ids.length - 1)

      this.columns.splice(0, this.columns.length, null, ...ids, null)

      if (from === to) return

      this.table.forEach((row) => {
        row.splice(to, 0, row.splice(from, 1)[0])
      })

      this.update()
    },

    // moves the column by keyboard, the handle keeps the focus
    moveCol(ev, idx) {
      const step = moveStep(ev, true, this.$vuetify.locale.isRtl)

      if (!step) return

      // Alt+ArrowLeft would navigate back in the browser history otherwise
      ev.preventDefault()

      const pos = idx + step
      const target = ev.currentTarget

      if (pos < 0 || pos >= this.cols.length) return

      // the first column entry is the placeholder of the row handle column
      this.columns.splice(pos + 1, 0, this.columns.splice(idx + 1, 1)[0])
      this.table.forEach((row) => row.splice(pos, 0, row.splice(idx, 1)[0]))

      this.update()
      this.$nextTick(() => target.focus())
    },

    // moves the row by keyboard, the handle keeps the focus
    moveRow(ev, idx) {
      const step = moveStep(ev)

      if (!step) return

      ev.preventDefault()

      const pos = idx + step
      const target = ev.currentTarget

      if (pos < 0 || pos >= this.table.length) return

      this.table.splice(pos, 0, this.table.splice(idx, 1)[0])

      this.update()
      this.$nextTick(() => target.focus())
    },

    movable(ev) {
      return !ev.related?.classList.contains('col-fixed')
    },

    rmCol(index) {
      if (this.cols.length <= 1) return

      // the first column entry is the placeholder of the row handle column
      this.columns.splice(index + 1, 1)
      this.table.forEach((row) => row.splice(index, 1))

      this.update()
    },

    rowKey(row) {
      if (!this.rowKeys.has(row)) {
        this.rowKeys.set(row, ++this.rowSeq)
      }

      return this.rowKeys.get(row)
    },

    rmRow(index) {
      if (this.table.length <= 1) return

      this.table.splice(index, 1)
      this.update()
    },

    // emits a copy so the parent's value and the working copy never share rows
    update() {
      this.emitted = this.copy(this.table)
      this.$emit('update:modelValue', this.emitted)
    }
  },

  watch: {
    modelValue: {
      handler(val) {
        if (val !== this.emitted) {
          this.table = this.copy(val)
        }
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
          v-draggable="[columns, { animation: DRAG_ANIMATION, disabled: readonly, handle: '.col-handle', onMove: movable, onUpdate: move }]"
          class="col-header"
        >
          <td class="col-fixed"></td>

          <td v-for="(col, idx) in cols" :key="col">
            <v-btn
              v-if="!readonly"
              variant="text"
              class="col-handle cursor-move"
              :aria-label="$gettext('Move column')"
              aria-keyshortcuts="Alt+ArrowLeft Alt+ArrowRight"
              @keydown="moveCol($event, idx)"
              :icon="mdiDragHorizontal"
            />

            <span class="btn-actions" v-if="!readonly">
              <ActionMenu :title="$gettext('Column actions')" location="start center">
                <template #activator="{ props, label }">
                  <v-btn v-bind="props" :title="label" :icon="mdiDotsVertical" variant="text" />
                </template>

                <ActionItem v-for="action in colActions(idx)" :key="action.icon" :prepend-icon="action.icon" @click="action.fn()">
                  {{ action.label }}
                </ActionItem>
              </ActionMenu>
            </span>
          </td>

          <td class="col-fixed"></td>
        </tr>
      </thead>

      <tbody v-draggable="[table, { animation: DRAG_ANIMATION, disabled: readonly, handle: '.row-handle', onUpdate: update }]">
        <tr v-for="(row, rowidx) in table" :key="rowKey(row)">
          <td>
            <v-btn
              v-if="!readonly"
              :aria-label="$gettext('Move row')"
              aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown"
              @keydown="moveRow($event, rowidx)"
              variant="text"
              class="row-handle cursor-move"
              :icon="mdiDragVertical"
            />
          </td>

          <td v-for="(col, colidx) in cols" :key="col">
            <v-textarea
              v-model="table[rowidx][colidx]"
              :aria-label="$gettext('Row %{row}, column %{col}', { row: rowidx + 1, col: colidx + 1 })"
              @input="updated()"
              :readonly="readonly"
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

                <ActionItem v-for="action in rowActions(rowidx)" :key="action.icon" :prepend-icon="action.icon" @click="action.fn()">
                  {{ action.label }}
                </ActionItem>
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
