<script>
  import ElementListItems from './ElementListItems.vue'
  import SchemaItems from './SchemaItems.vue'

  export default {
    components: {
      ElementListItems,
      SchemaItems,
    },

    props: {
      'modelValue': {type: Boolean, required: true},
      'elements': {type: Boolean, default: true},
    },

    emits: ['update:modelValue', 'add']
  }
</script>

<template>
  <v-dialog :modelValue="modelValue" @afterLeave="$emit('update:modelValue', false)" max-width="1200" scrollable>
    <v-card>
      <template v-slot:append>
        <v-btn
          :title="$gettext('Close')"
          @click="$emit('update:modelValue', false)"
          icon="mdi-close"
          variant="text"
          elevation="0"
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
