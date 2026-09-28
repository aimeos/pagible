/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiMagnify, mdiRefresh } from '@mdi/js'
import { useMessageStore, useSchemaStore, useUserStore } from '../stores'
import ListSort from './ListSort.vue'

const SORT_OPTIONS = Object.freeze([
  { column: 'POSITION', order: 'ASC', label: 'Position' },
  { column: 'NAME', order: 'ASC', label: 'Name' }
])

export default {
  components: {
    ListSort
  },

  props: {
    type: { type: String, required: true }
  },

  emits: ['add'],

  data() {
    return {
      loading: false,
      sort: this.user.setting('schema', 'sort', { column: 'POSITION', order: 'ASC' }),
      tab: 'basic',
      term: ''
    }
  },

  setup() {
    const messages = useMessageStore()
    const schemas = useSchemaStore()
    const user = useUserStore()

    return { messages, schemas, user, mdiMagnify, mdiRefresh, sortOptions: SORT_OPTIONS }
  },

  computed: {
    active() {
      return this.groups[this.tab] ? this.tab : Object.keys(this.groups)[0]
    },

    groups() {
      const map = {}

      for (const type in this.schemas[this.type] || {}) {
        const el = this.schemas[this.type][type]
        const name = el.group || 'uncategorized'

        map[name] = map[name] || []
        map[name].push({ ...el, type })
      }

      return map
    },

    items() {
      const list = this.matches || this.groups[this.active] || []

      if (this.sort?.column !== 'NAME') {
        return list
      }

      const collator = new Intl.Collator(this.$language?.current)

      return list
        .map((item) => [this.label(item), item])
        .sort((a, b) => collator.compare(a[0], b[0]))
        .map(([, item]) => item)
    },

    matches() {
      const term = (this.term || '').trim().toLowerCase()

      if (!term) {
        return null
      }

      return Object.entries(this.groups).flatMap(([name, list]) => {
        if (this.group(name).toLowerCase().includes(term)) {
          return list
        }

        return list.filter((item) =>
          [item.type, this.label(item)].some((str) => str.toLowerCase().includes(term))
        )
      })
    }
  },

  methods: {
    add(item) {
      this.$emit('add', { type: item.type })
    },

    group(name) {
      return name === 'uncategorized' ? this.$gettext('uncategorized') : this.$pgettext('sg', name)
    },

    label(item) {
      return this.$pgettext('st', item.label || item.type)
    },

    reload() {
      this.loading = true

      return this.schemas
        .reload()
        .catch((error) => {
          this.messages.add(this.$gettext('Error fetching content elements') + ':\n' + error, 'error')
          this.$log(`SchemaItems::reload(): Error fetching schemas`, error)
        })
        .finally(() => {
          this.loading = false
        })
    },

    select(name) {
      this.tab = name
      this.term = ''
    }
  }
}
</script>

<template>
  <div class="header">
    <div class="search">
      <v-text-field
        v-model="term"
        :prepend-inner-icon="mdiMagnify"
        :label="$gettext('Search for')"
        variant="underlined"
        hide-details
        clearable
      ></v-text-field>
    </div>

    <div class="layout">
      <v-btn
        @click="reload()"
        :loading="loading"
        :title="$gettext('Reload elements')"
        :icon="mdiRefresh"
        class="btn-reload"
        variant="text"
      />

      <ListSort v-model="sort" :options="sortOptions" />
    </div>
  </div>

  <div class="schemas">
    <v-tabs
      :model-value="matches ? null : active"
      :direction="$vuetify.display.xs ? 'horizontal' : 'vertical'"
      :mandatory="false"
      class="tint-tabs"
    >
      <v-tab v-for="name in Object.keys(groups)" :key="name" :value="name" @click="select(name)">{{
        group(name)
      }}</v-tab>
    </v-tabs>

    <v-card class="items">
      <v-btn
        v-for="item in items"
        :key="item.type"
        @click="add(item)"
        variant="text"
        stacked
      >
        <template v-slot:prepend>
          <span class="el-icon" v-safe-svg="item.icon"></span>
        </template>
        {{ label(item) }}
      </v-btn>

      <p v-if="matches && !matches.length" class="none">
        {{ $gettext('No entries found') }}
      </p>
    </v-card>
  </div>
</template>

<style scoped>
.schemas {
  display: flex;
  align-items: flex-start;
}

.v-tabs--vertical {
  border-radius: 4px;
  flex-shrink: 0;
  margin-inline-end: 16px;
}

.items {
  display: flex;
  flex-wrap: wrap;
  align-content: flex-start;
  flex-grow: 1;
  min-width: 0;
  padding: 8px 0;
}

.none {
  padding: 16px;
}

@media (max-width: 599px) {
  .schemas {
    flex-direction: column;
    align-items: stretch;
  }

  .v-tabs {
    margin-bottom: 8px;
  }
}

.items .v-btn,
.v-tab {
  max-width: 12rem;
  min-width: 8rem !important;
}

.el-icon {
  width: 2rem;
}
</style>
