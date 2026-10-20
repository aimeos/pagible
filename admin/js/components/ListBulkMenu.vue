/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiDelete, mdiDeleteForever, mdiDeleteRestore, mdiDotsVertical, mdiPencil, mdiPublish } from '@mdi/js'
import ActionItem from './ActionItem.vue'
import ActionMenu from './ActionMenu.vue'
import { useUserStore } from '../stores'

/**
 * Menu with the actions for the selected elements or files, the counts tell how many items each
 * action applies to. Additional actions can be added after "Edit properties" by the default slot.
 */
export default {
  components: { ActionItem, ActionMenu },

  props: {
    type: { type: String, required: true },
    counts: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
    selected: { type: Boolean, default: false }
  },

  emits: ['drop', 'edit', 'keep', 'publish', 'purge'],

  setup() {
    const user = useUserStore()

    return { user, mdiDelete, mdiDeleteForever, mdiDeleteRestore, mdiDotsVertical, mdiPencil, mdiPublish }
  },

  computed: {
    // additional actions in the slot require saving too (e.g. translating file descriptions)
    permitted() {
      return ['publish', 'save', 'drop', 'keep', 'purge'].some((action) => this.can(action))
    }
  },

  methods: {
    can(action) {
      return this.user.can(this.type + ':' + action)
    }
  }
}
</script>

<template>
  <span class="btn-actions">
    <ActionMenu>
      <template #activator="{ props, label }">
        <v-btn v-bind="props" :disabled="disabled || !permitted" :title="label" :icon="mdiDotsVertical" variant="text" />
      </template>
      <ActionItem v-if="counts.draft && can('publish')" :prepend-icon="mdiPublish" @click="$emit('publish')">
        {{ $gettext('Publish') }} ({{ counts.draft }})
      </ActionItem>
      <ActionItem v-if="selected && can('save')" :prepend-icon="mdiPencil" @click="$emit('edit')">
        {{ $gettext('Edit properties') }} ({{ counts.all }})
      </ActionItem>
      <slot />
      <ActionItem v-if="counts.live && can('drop')" :prepend-icon="mdiDelete" @click="$emit('drop')">
        {{ $gettext('Delete') }} ({{ counts.live }})
      </ActionItem>
      <ActionItem v-if="counts.trashed && can('keep')" :prepend-icon="mdiDeleteRestore" @click="$emit('keep')">
        {{ $gettext('Restore') }} ({{ counts.trashed }})
      </ActionItem>
      <ActionItem v-if="selected && can('purge')" :prepend-icon="mdiDeleteForever" @click="$emit('purge')">
        {{ $gettext('Purge') }} ({{ counts.all }})
      </ActionItem>
    </ActionMenu>
  </span>
</template>
