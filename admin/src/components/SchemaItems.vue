<script>
  import { useSchemaStore } from '../stores'
  import { uid } from '../utils'

  export default {
    props: {
      'type': {type: String, required: true}
    },

    emits: ['add'],

    data: () => ({
      tab: ['basic'],
    }),

    setup() {
      const schemas = useSchemaStore()
      return { schemas }
    },

    computed: {
      groups() {
        const map = {}

        for(const type in this.schemas[this.type] || {}) {
          const el = this.schemas[this.type][type]
          const name = el.group || this.$gettext('uncategorized')

          el.type = type
          map[name] = map[name] || []
          map[name].push(el)
        }

        return map
      }
    },

    methods: {
      add(item) {
        this.$emit('add', {type: item.type})
      }
    }
  }
</script>

<template>
  <v-tabs v-model="tab">
    <v-tab v-for="(group, name) in groups" :key="name" :value="name">{{ $pgettext('sg', name) }}</v-tab>
  </v-tabs>

  <v-tabs-window v-model="tab">
    <v-tabs-window-item v-for="name in Object.keys(groups)" :key="name" :value="name">

      <v-card>
        <v-btn v-for="item in groups[name]" :key="item.type" @click="add(item)" variant="text" stacked>
          <template v-slot:prepend>
            <span class="icon" v-html="item.icon"></span>
          </template>
          {{ $pgettext('st', item.label || item.type) }}
        </v-btn>
      </v-card>

    </v-tabs-window-item>
  </v-tabs-window>
</template>

<style scoped>
  .v-container {
    background-color: rgb(var(--v-theme-surface));
    max-width: 100%;
    min-width: 50vw;
    padding: 8px !important;
  }

  .v-tabs {
    background-color: rgb(var(--v-theme-background));
  }

  .v-card {
    padding: 8px 0;
  }

  .v-btn {
    max-width: 12rem;
    min-width: 8rem !important;
  }

  .icon {
    width: 2rem;
  }
</style>
