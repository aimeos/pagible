/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiDotsVertical, mdiPencil, mdiTrashCan } from '@mdi/js'
import ActionMenu from './ActionMenu.vue'

export default {
  components: { ActionMenu },

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

    <v-list-item v-if="editable">
      <v-btn :prepend-icon="mdiPencil" variant="text" @click="$emit('edit')">
        {{ $gettext('Edit') }}
      </v-btn>
    </v-list-item>
    <v-list-item>
      <v-btn :prepend-icon="mdiTrashCan" variant="text" @click="$emit('remove')">
        {{ $gettext('Remove') }}
      </v-btn>
    </v-list-item>
  </ActionMenu>
</template>
