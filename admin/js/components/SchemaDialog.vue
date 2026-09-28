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
  emits: ['update:modelValue', 'add'],

  data: () => ({
    tab: 'new'
  })
}
</script>

<template>
  <CmsDialog
    :model-value="modelValue"
    :title="$gettext('Content elements')"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="1200"
  >
    <v-tabs v-if="elements" v-model="tab" class="tint-tabs">
      <v-tab value="new">{{ $gettext('New elements') }}</v-tab>
      <v-tab value="shared">{{ $gettext('Shared elements') }}</v-tab>
    </v-tabs>

    <v-tabs-window v-model="tab">
      <v-tabs-window-item value="new">
        <SchemaItems :type="type" @add="$emit('add', $event)" />
      </v-tabs-window-item>
      <v-tabs-window-item v-if="elements" value="shared">
        <ElementListItems @select="$emit('add', $event)" embed />
      </v-tabs-window-item>
    </v-tabs-window>
  </CmsDialog>
</template>

<style scoped>
.v-tabs {
  margin-bottom: 16px;
}
</style>
