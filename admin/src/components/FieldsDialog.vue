<script>
  import Fields from './Fields.vue'
  import { useSchemaStore } from '../stores'

  export default {
    components: {
      Fields,
    },

    props: {
      'modelValue': {type: Boolean, required: true},
      'readonly': {type: Boolean, default: false},
      'element': {type: Object, required: true},
      'assets': {type: Object, default: () => ({})},
      'type': {type: String, default: 'content'},
    },

    emits: ['update:modelValue', 'update:element'],

    setup() {
      const schemas = useSchemaStore()
      return { schemas }
    },

    data() {
      return {
        error: false,
      }
    },

    methods: {
      fields(type) {
        if(!this.schemas[this.type] || !this.schemas[this.type][type]?.fields) {
          console.warn(`No definition of fields for "${type}" (${this.type}) schemas`)
          return []
        }

        return this.schemas.content[type]?.fields
      }
    }
}
</script>

<template>
  <v-dialog :modelValue="modelValue" @afterLeave="$emit('update:modelValue', false)" max-width="1200" scrollable>
    <v-card>
      <template v-slot:append>
        <v-btn v-if="!readonly && !error"
          @click="$emit('update:element', element)"
          variant="outlined"
        >{{ $gettext('Save') }}</v-btn>
        <v-btn
          @click="$emit('update:modelValue', false)"
          :title="$gettext('Close')"
          icon="mdi-close"
          variant="text"
          elevation="0"
        />
      </template>
      <template v-slot:title>
        {{ $gettext('Content Element') }}
      </template>

      <v-divider></v-divider>

      <v-card-text>
        <Fields
          v-model:data="element.data"
          v-model:files="element.files"
          :fields="fields(element.type)"
          :readonly="readonly"
          :type="element.type"
          :assets="assets"
          @error="error = element._error = $event"
        />
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped>
</style>
