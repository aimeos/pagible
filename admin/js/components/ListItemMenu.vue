/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiDelete, mdiDeleteForever, mdiDeleteRestore, mdiDotsVertical, mdiPencil, mdiPublish } from '@mdi/js'
import ActionItem from './ActionItem.vue'
import ActionMenu from './ActionMenu.vue'
import { useUserStore } from '../stores'

/**
 * Menu with the actions for a single element or file. Additional actions can be added after
 * "Edit properties" by the default slot.
 */
export default {
  components: { ActionItem, ActionMenu },

  props: {
    type: { type: String, required: true },
    item: { type: Object, required: true },
    activatorClass: { type: [String, Array, Object], default: '' },
    location: { type: String, default: 'end center' }
  },

  emits: ['drop', 'edit', 'keep', 'publish', 'purge'],

  setup() {
    const user = useUserStore()

    return { user, mdiDelete, mdiDeleteForever, mdiDeleteRestore, mdiDotsVertical, mdiPencil, mdiPublish }
  },

  computed: {
    publishable() {
      return !this.item.deleted_at && !this.item.published && this.can('publish')
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
  <ActionMenu :location="location">
    <template #activator="{ props, label }">
      <v-btn v-bind="props" :title="label" :icon="mdiDotsVertical" :class="activatorClass" variant="text" />
    </template>
    <ActionItem v-if="publishable" :prepend-icon="mdiPublish" @click="$emit('publish')">
      {{ $gettext('Publish') }}
    </ActionItem>

    <v-divider v-if="publishable && can('save')"></v-divider>

    <ActionItem v-if="can('save')" :prepend-icon="mdiPencil" @click="$emit('edit')">
      {{ $gettext('Edit properties') }}
    </ActionItem>
    <slot />

    <v-divider v-if="can('save')"></v-divider>

    <ActionItem v-if="!item.deleted_at && can('drop')" :prepend-icon="mdiDelete" @click="$emit('drop')">
      {{ $gettext('Delete') }}
    </ActionItem>
    <ActionItem v-if="item.deleted_at && can('keep')" :prepend-icon="mdiDeleteRestore" @click="$emit('keep')">
      {{ $gettext('Restore') }}
    </ActionItem>
    <ActionItem v-if="can('purge')" :prepend-icon="mdiDeleteForever" @click="$emit('purge')">
      {{ $gettext('Purge') }}
    </ActionItem>
  </ActionMenu>
</template>
