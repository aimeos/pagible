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
          [item.type, this.label(item), this.description(item)].some((str) =>
            str.toLowerCase().includes(term)
          )
        )
      })
    }
  },

  methods: {
    add(item) {
      this.$emit('add', { type: item.type })
    },

    description(item) {
      return item.description ? this.$pgettext('sd', item.description) : ''
    },

    group(name) {
      return name === 'uncategorized' ? this.$gettext('Uncategorized') : this.$pgettext('sg', name)
    },

    label(item) {
      return this.$pgettext('st', item.label || item.type).replace('::', ' ')
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

    <v-card class="items" flat>
      <div class="grid">
        <v-list-item
          v-for="item in items"
          :key="item.type"
          :title="label(item)"
          :subtitle="description(item) || undefined"
          @click="add(item)"
          class="item"
          link
        >
          <template v-slot:prepend>
            <span class="el-icon" v-safe-svg="item.icon"></span>
          </template>
        </v-list-item>
      </div>

      <p v-if="matches && !matches.length" class="none">
        {{ $gettext('No entries found') }}
      </p>
    </v-card>
  </div>
</template>

<style scoped>
.schemas {
  display: flex;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  overflow: hidden;
}

.schemas .v-tabs {
  border-radius: 0;
  flex-shrink: 0;
}

.schemas .v-tab--selected {
  background-color: rgb(var(--v-theme-surface));
}

.items {
  container-type: inline-size;
  flex-grow: 1;
  min-width: 0;
  padding: 8px;
  border-radius: 0;
}

.grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 4px 8px;
}

@container (width >= 576px) {
  .grid {
    grid-template-columns: 1fr 1fr;
  }
}

.item {
  border-radius: 4px;
  padding-block: 8px;
}

.item :deep(.v-list-item-subtitle) {
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.none {
  padding: 16px;
}

@media (max-width: 599px) {
  .schemas {
    flex-direction: column;
  }
}

.v-tab {
  max-width: 12rem;
  min-width: 8rem !important;
}

.el-icon {
  flex-shrink: 0;
  width: 2rem;
  margin-inline-end: 16px;
}

.el-icon :deep(svg) {
  display: block;
  width: 100%;
  height: 2rem;
}
</style>
