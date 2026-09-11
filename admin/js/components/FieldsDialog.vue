/** @license MIT, https://opensource.org/license/mit */

<script>
import Fields from './Fields.vue'
import { useSchemaStore } from '../stores'
import { mdiClose } from '@mdi/js'

export default {
  components: {
    Fields
  },

  props: {
    modelValue: { type: Boolean, required: true },
    readonly: { type: Boolean, default: false },
    element: { type: Object, required: true },
    assets: { type: Object, default: () => ({}) },
    type: { type: String, default: 'content' }
  },

  emits: ['change', 'update:modelValue', 'update:element'],

  setup() {
    const schemas = useSchemaStore()
    return { schemas, mdiClose }
  },

  data() {
    return {
      error: false
    }
  },

  methods: {
    close() {
      if (this.element._changed) {
        this.$emit('change', this.element)
      }

      this.$emit('update:modelValue', false)
    },

    fields(type) {
      if (!this.schemas[this.type] || !this.schemas[this.type][type]?.fields) {
        console.warn(`No definition of fields for "${type}" (${this.type}) schemas`)
        return []
      }

      return this.schemas.content[type]?.fields
    }
  }
}
</script>

<template>
  <v-dialog
    :aria-label="$gettext('Content Element')"
    :modelValue="modelValue"
    @afterLeave="$emit('update:modelValue', false)"
    max-width="1200"
    scrollable
  >
    <v-card>
      <v-toolbar density="compact">
        <v-toolbar-title>{{ $gettext('Content Element') }}</v-toolbar-title>
        <v-btn
          v-if="!readonly && !error && element._changed"
          @click="$emit('update:element', element)"
          variant="tonal"
          color="primary"
        >{{ $gettext('Save') }}</v-btn>
        <v-btn :icon="mdiClose" :aria-label="$gettext('Close')" @click="close" />
      </v-toolbar>
      <v-card-text>
        <Fields
          v-model:data="element.data"
          v-model:files="element.files"
          :fields="fields(element.type)"
          :readonly="readonly"
          :type="element.type"
          :assets="assets"
          @error="error = element._error = $event"
          @change="element._changed = true"
        />
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped></style>
