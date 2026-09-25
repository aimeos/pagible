/** @license MIT, https://opensource.org/license/mit */

<script>
import { defineAsyncComponent } from 'vue'
import CmsDialog from './Dialog.vue'
import SchemaItems from './SchemaItems.vue'

const ElementListItems = defineAsyncComponent(() => import('./ElementListItems.vue'))

export default {
  components: {
    CmsDialog,
    ElementListItems,
    SchemaItems
  },

  props: {
    modelValue: { type: Boolean, required: true },
    elements: { type: Boolean, default: true },
    type: { type: String, default: 'content' }
  },
  emits: ['update:modelValue', 'add']
}
</script>

<template>
  <CmsDialog
    :model-value="modelValue"
    :title="$gettext('Content elements')"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="1200"
  >
    <SchemaItems :type="type" @add="$emit('add', $event)" />

    <div v-if="elements">
      <v-tabs>
        <v-tab>{{ $gettext('Shared elements') }}</v-tab>
      </v-tabs>
      <ElementListItems @select="$emit('add', $event)" embed />
    </div>
  </CmsDialog>
</template>

<style scoped>
.v-tabs {
  background-color: rgb(var(--v-theme-emphasis, var(--v-theme-surface-light)));
  color: rgb(var(--v-theme-on-emphasis, var(--v-theme-on-surface-light)));
  margin-bottom: 8px;
}
</style>
