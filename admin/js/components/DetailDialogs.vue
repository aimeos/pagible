/** @license MIT, https://opensource.org/license/mit */

<script>
import { defineAsyncComponent } from 'vue'

/**
 * History and conflict dialogs of the page, element and file detail views, bound by the
 * "dialogs" computed property of the detail base; the slot adds further dialogs
 */
export default {
  components: {
    ChangesDialog: defineAsyncComponent(() => import('./ChangesDialog.vue')),
    HistoryDialog: defineAsyncComponent(() => import('./HistoryDialog.vue'))
  },

  props: {
    history: { type: Boolean, default: false },
    changes: { type: Boolean, default: false },
    // conflicting changes of the last save
    changed: { type: Object, default: null },
    // snapshot of the current state compared with the old versions
    current: { type: Object, default: null },
    // loads the versions of the item
    load: { type: Function, required: true },
    readonly: { type: Boolean, default: false }
  },

  emits: ['update:history', 'update:changes', 'apply', 'use', 'resolve', 'unresolve']
}
</script>

<template>
  <Teleport to="body">
    <slot />
    <HistoryDialog
      v-if="history"
      :model-value="history"
      :readonly="readonly"
      :current="current"
      :load="load"
      @update:model-value="$emit('update:history', $event)"
      @apply="(...args) => $emit('apply', ...args)"
      @use="(...args) => $emit('use', ...args)"
    />
    <ChangesDialog
      :model-value="changes"
      :changed="changed"
      @update:model-value="$emit('update:changes', $event)"
      @resolve="$emit('resolve', $event)"
      @unresolve="$emit('unresolve', $event)"
    />
  </Teleport>
</template>
