/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import ElementListItems from './ElementListItems.vue'
import SchemaItems from './SchemaItems.vue'
import { mdiClose } from '@mdi/js'

export default {
  components: {
    ElementListItems,
    SchemaItems
  },

  props: {
    modelValue: { type: Boolean, required: true },
    elements: { type: Boolean, default: true }
  },

  emits: ['update:modelValue', 'add'],

  setup() {
    return { mdiClose }
  }
}
</script>

<template>
  <v-dialog
    :aria-label="$gettext('Content elements')"
    :modelValue="modelValue"
    @afterLeave="$emit('update:modelValue', false)"
    max-width="1200"
    scrollable
  >
    <v-card>
      <template v-slot:append>
        <v-btn
          :title="$gettext('Close')"
          @click="$emit('update:modelValue', false)"
          :icon="mdiClose"
          variant="text"
        />
      </template>
      <template v-slot:title>
        {{ $gettext('Content elements') }}
      </template>

      <v-divider></v-divider>

      <v-card-text>
        <SchemaItems type="content" @add="$emit('add', $event)" />

        <div v-if="elements">
          <v-tabs>
            <v-tab>{{ $gettext('Shared elements') }}</v-tab>
          </v-tabs>
          <ElementListItems @select="$emit('add', $event)" embed />
        </div>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.v-tabs {
  background-color: rgb(var(--v-theme-background));
  margin-bottom: 8px;
}
</style>
