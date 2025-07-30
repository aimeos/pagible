<script>
  import FileListItems from './FileListItems.vue'

  export default {
    components: {
      FileListItems
    },

    props: {
      'modelValue': {type: Boolean, required: true},
      'filter': {type: Object, default: () => ({})},
      'grid': {type: Boolean, default: false},
    },

    emits: ['update:modelValue', 'add']
  }
</script>

<template>
  <v-dialog :modelValue="modelValue" @afterLeave="$emit('update:modelValue', false)" max-width="1200" scrollable>
    <v-card>
      <template v-slot:append>
        <v-btn
          @click="$emit('update:modelValue', false)"
          :title="$gettext('Close')"
          icon="mdi-close"
          variant="flat"
        />
      </template>
      <template v-slot:title>
        {{ $gettext('Files') }}
      </template>

      <v-divider></v-divider>

      <v-card-text>
        <FileListItems :filter="filter" :grid="grid" @select="$emit('add', $event)" embed />
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped>
</style>
