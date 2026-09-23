/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiAlertCircleOutline } from '@mdi/js'
import CmsDialog from './Dialog.vue'
import { useConfirmStore } from '../stores'

export default {
  components: {
    CmsDialog
  },

  setup() {
    const confirm = useConfirmStore()
    return { confirm, mdiAlertCircleOutline }
  }
}
</script>

<template>
  <CmsDialog
    :model-value="confirm.show"
    :title="$gettext('Purge')"
    @update:model-value="!$event && confirm.close(false)"
    toolbar-color="warning"
    role="alertdialog"
    max-width="600"
    scrollable
  >
    <div class="warning">
      <v-icon :icon="mdiAlertCircleOutline" color="warning" size="40" aria-hidden="true" />
      <p>
        {{
          $ngettext(
            'The following entry will be permanently deleted and cannot be restored:',
            'The following %{num} entries will be permanently deleted and cannot be restored:',
            confirm.items.length,
            { num: confirm.items.length }
          )
        }}
      </p>
    </div>

    <v-list class="confirm-list" density="compact">
      <v-list-item
        v-for="(item, idx) in confirm.items"
        :key="idx"
        :title="item.name || $gettext('New')"
        :subtitle="item.info"
      />
    </v-list>

    <p v-if="confirm.hint" class="confirm-hint">{{ confirm.hint }}</p>

    <template #actions>
      <v-btn @click="confirm.close(false)" variant="text">{{ $gettext('Cancel') }}</v-btn>
      <v-btn @click="confirm.close(true)" class="btn-confirm" color="error" variant="flat">{{
        $gettext('Confirm')
      }}</v-btn>
    </template>
  </CmsDialog>
</template>

<style scoped>
.warning {
  align-items: center;
  display: flex;
  gap: 16px;
}

.warning p {
  margin: 0;
}

.confirm-list {
  margin-top: 16px;
  border: 1px solid rgba(var(--v-border-color), 0.38);
  border-radius: 4px;
  padding: 0;
}

.confirm-hint {
  color: rgb(var(--v-theme-on-surface));
  margin: 16px 0 0;
}
</style>
