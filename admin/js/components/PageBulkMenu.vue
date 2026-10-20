/** @license MIT, https://opensource.org/license/mit */

<script>
import {
  mdiCached,
  mdiCheckAll,
  mdiDelete,
  mdiDeleteForever,
  mdiDeleteRestore,
  mdiDotsVertical,
  mdiEye,
  mdiEyeOff,
  mdiKeyVariant,
  mdiPencil,
  mdiPublish,
  mdiTranslate,
  mdiTranslateOff
} from '@mdi/js'
import ActionItem from './ActionItem.vue'
import ActionMenu from './ActionMenu.vue'
import { useUserStore } from '../stores'

/**
 * Menu with the actions for the selected pages, the counts tell how many pages each action applies to.
 */
export default {
  components: { ActionItem, ActionMenu },

  props: {
    counts: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
    selected: { type: Boolean, default: false }
  },

  emits: [
    'clear',
    'disable',
    'drop',
    'drop-lang',
    'edit-access',
    'edit-props',
    'enable',
    'ignore',
    'keep',
    'keep-lang',
    'open',
    'publish',
    'purge',
    'purge-lang',
    'translate'
  ],

  setup() {
    const user = useUserStore()

    return {
      user,
      mdiCached,
      mdiCheckAll,
      mdiDelete,
      mdiDeleteForever,
      mdiDeleteRestore,
      mdiDotsVertical,
      mdiEye,
      mdiEyeOff,
      mdiKeyVariant,
      mdiPencil,
      mdiPublish,
      mdiTranslate,
      mdiTranslateOff
    }
  }
}
</script>

<template>
  <span class="btn-actions">
    <ActionMenu>
      <template #activator="{ props, label }">
        <v-btn
          v-bind="props"
          @click="$emit('open')"
          :disabled="disabled"
          :title="label"
          :icon="mdiDotsVertical"
          variant="text"
        />
      </template>
      <ActionItem v-if="counts.draft && user.can('page:publish')" :prepend-icon="mdiPublish" @click="$emit('publish')">
        {{ $gettext('Publish') }} ({{ counts.draft }})
      </ActionItem>
      <ActionItem v-if="counts.own && user.can('page:save')" :prepend-icon="mdiEye" @click="$emit('enable')">
        {{ $pgettext('page status', 'Enable') }} ({{ counts.own }})
      </ActionItem>
      <ActionItem v-if="counts.own && user.can('page:save')" :prepend-icon="mdiEyeOff" @click="$emit('disable')">
        {{ $pgettext('page status', 'Disable') }} ({{ counts.own }})
      </ActionItem>
      <v-divider></v-divider>
      <ActionItem v-if="counts.own && user.can('page:save')" :prepend-icon="mdiPencil" @click="$emit('edit-props')">
        {{ $gettext('Edit properties') }} ({{ counts.own }})
      </ActionItem>
      <ActionItem v-if="selected && user.can('page:access')" :prepend-icon="mdiKeyVariant" @click="$emit('edit-access')">
        {{ $gettext('Access') }} ({{ counts.all }})
      </ActionItem>
      <ActionItem v-if="selected && user.can('cache:clear')" :prepend-icon="mdiCached" @click="$emit('clear')">
        {{ $gettext('Clear cache') }} ({{ counts.all }})
      </ActionItem>

      <v-divider></v-divider>

      <ActionItem v-if="counts.live && user.can('page:drop')" :prepend-icon="mdiDelete" @click="$emit('drop')">
        {{ $gettext('Delete') }} ({{ counts.live }})
      </ActionItem>
      <ActionItem v-if="counts.trashed && user.can('page:keep')" :prepend-icon="mdiDeleteRestore" @click="$emit('keep')">
        {{ $gettext('Restore') }} ({{ counts.trashed }})
      </ActionItem>
      <ActionItem v-if="counts.purge" :prepend-icon="mdiDeleteForever" @click="$emit('purge')">
        {{ $gettext('Purge') }} ({{ counts.purge }})
      </ActionItem>

      <template v-if="counts.translate || counts.stale || counts.langLive || counts.langTrashed || counts.langPurge">
        <v-divider></v-divider>

        <ActionItem
          v-if="counts.translate"
          :prepend-icon="mdiTranslate"
          class="action-translate"
          @click="$emit('translate')"
        >
          {{ $gettext('Translate') }} ({{ counts.translate }})
        </ActionItem>
        <ActionItem
          v-if="counts.stale"
          :prepend-icon="mdiCheckAll"
          class="action-ignore-changes"
          @click="$emit('ignore')"
        >
          {{ $gettext('Ignore changes') }} ({{ counts.stale }})
        </ActionItem>

        <ActionItem
          v-if="counts.langLive"
          :prepend-icon="mdiTranslateOff"
          class="action-drop-lang"
          @click="$emit('drop-lang')"
        >
          {{ $gettext('Delete this language') }} ({{ counts.langLive }})
        </ActionItem>
        <ActionItem
          v-if="counts.langTrashed"
          :prepend-icon="mdiDeleteRestore"
          class="action-keep-lang"
          @click="$emit('keep-lang')"
        >
          {{ $gettext('Restore this language') }} ({{ counts.langTrashed }})
        </ActionItem>
        <ActionItem
          v-if="counts.langPurge"
          :prepend-icon="mdiDeleteForever"
          class="action-purge-lang"
          @click="$emit('purge-lang')"
        >
          {{ $gettext('Purge language') }} ({{ counts.langPurge }})
        </ActionItem>
      </template>
    </ActionMenu>
  </span>
</template>
