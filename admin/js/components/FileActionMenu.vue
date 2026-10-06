/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiDotsVertical, mdiPencil, mdiTrashCan } from '@mdi/js'
import ActionItem from './ActionItem.vue'
import ActionMenu from './ActionMenu.vue'

export default {
  components: { ActionItem, ActionMenu },

  props: {
    editable: { type: Boolean, default: false }
  },

  emits: ['edit', 'remove'],

  setup() {
    return { mdiDotsVertical, mdiPencil, mdiTrashCan }
  }
}
</script>

<template>
  <ActionMenu location="start">
    <template #activator="{ props, label }">
      <v-btn
        v-bind="props"
        :title="label"
        :icon="mdiDotsVertical"
        class="btn-overlay"
        variant="text"
      />
    </template>

    <ActionItem v-if="editable" :prepend-icon="mdiPencil" @click="$emit('edit')">
      {{ $gettext('Edit') }}
    </ActionItem>
    <ActionItem :prepend-icon="mdiTrashCan" @click="$emit('remove')">
      {{ $gettext('Remove') }}
    </ActionItem>
  </ActionMenu>
</template>
