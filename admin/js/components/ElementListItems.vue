/** @license MIT, https://opensource.org/license/mit */

<script>
import { markRaw } from 'vue'
import gql from 'graphql-tag'
import ListBulkMenu from './ListBulkMenu.vue'
import ListItemMenu from './ListItemMenu.vue'
import ListReload from './ListReload.vue'
import ListSearch from './ListSearch.vue'
import ListStatus from './ListStatus.vue'
import SchemaDialog from './SchemaDialog.vue'
import EditBulkDialog from './EditBulkDialog.vue'
import ListSort from './ListSort.vue'
import { FILE_FIELDS, normalizeFile } from '../files'
import { listBase, useList } from '../lists'
import { frozenParse, safeParse } from '../json'
import { formatDate } from '../utils'
import { versionMeta } from '../pages'

const ADD_ELEMENT = gql`
  mutation ($input: ElementInput!) {
    addElement(input: $input) {
      id
      lang
      name
      type
      data
      editor
      created_at
      updated_at
      deleted_at
    }
  }
`

const FETCH_ELEMENTS = gql`
  ${FILE_FIELDS}
  query (
    $filter: ElementFilter
    $sort: [QueryElementsSortOrderByClause!]
    $limit: Int!
    $page: Int!
    $trashed: Trashed
    $publish: Publish
  ) {
    elements(
      filter: $filter
      sort: $sort
      first: $limit
      page: $page
      trashed: $trashed
      publish: $publish
    ) {
      data {
        id
        lang
        name
        type
        data
        editor
        created_at
        updated_at
        deleted_at
        files {
          ...CmsFileFields
        }
        latest {
          id
          published
          publish_at
          data
          editor
          created_at
          files {
            ...CmsFileFields
          }
        }
      }
      paginatorInfo {
        lastPage
      }
    }
  }
`

const SORT_OPTIONS = Object.freeze([
  { column: 'ID', order: 'DESC', label: 'Latest' },
  { column: 'ID', order: 'ASC', label: 'Oldest' },
  { column: 'LATEST_ID', order: 'DESC', label: 'Latest edit' },
  { column: 'LATEST_ID', order: 'ASC', label: 'Oldest edit' },
  { column: 'NAME', order: 'ASC', label: 'Name' },
  { column: 'TYPE', order: 'ASC', label: 'Type' },
  { column: 'EDITOR', order: 'ASC', label: 'Editor' }
])

export default {
  extends: listBase,

  components: {
    ListBulkMenu,
    ListItemMenu,
    ListReload,
    ListSearch,
    ListStatus,
    SchemaDialog,
    EditBulkDialog,
    ListSort
  },

  data() {
    return {
      vschemas: false
    }
  },

  setup() {
    return {
      ...useList('element', FETCH_ELEMENTS, (vm) => (vm.vschemas = true)),
      formatDate,
      sortOptions: SORT_OPTIONS
    }
  },

  methods: {
    add(item) {
      if (this.embed || !this.user.canAdd('element:add')) return this.messages.denied()

      // restricted users can only add elements in their languages
      const lang = this.user.addLang()

      return this.$apollo
        .mutate({
          mutation: ADD_ELEMENT,
          variables: {
            input: {
              type: item.type,
              name: '',
              data: '{}',
              ...(lang ? { lang } : {})
            }
          }
        })
        .then((response) => {
          const data = response.data?.addElement || {}
          data.data = frozenParse(data.data)
          data.published = true

          this.vschemas = false
          this.items.unshift(data)

          this.$emit('select', data)
          this.invalidate()

          return data
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error adding shared element'), error, item)
        })
    },

    failed(action) {
      return {
        drop: this.$gettext('Error trashing shared element'),
        keep: this.$gettext('Error restoring shared element'),
        pub: this.$gettext('Error publishing shared element'),
        purge: this.$gettext('Error purging shared element'),
        save: this.$gettext('Error saving shared element'),
        search: this.$gettext('Error fetching shared elements')
      }[action]
    },

    hydrate(entry) {
      const latest = entry.latest
      const item = latest?.data
        ? safeParse(latest.data)
        : {
            ...entry,
            data: safeParse(entry.data)
          }

      if (item.data && typeof item.data === 'object') {
        item.data = markRaw(item.data)
      }

      return Object.assign(item, {
        id: entry.id,
        deleted_at: entry.deleted_at,
        created_at: entry.created_at,
        ...versionMeta(entry),
        files: Object.freeze((latest?.files || entry.files || []).map(normalizeFile))
      })
    }
  }
}
</script>

<template>
  <div class="header">
    <div class="bulk">
      <v-checkbox-btn
        :model-value="allChecked"
        :indeterminate="isChecked && !allChecked"
        @click.stop="toggle()"
        :aria-label="$gettext('Toggle selection')"
      />

      <ListBulkMenu
        type="element"
        :counts="counts"
        :selected="isChecked"
        :disabled="!isChecked || embed"
        @publish="publish()"
        @edit="edit()"
        @drop="drop()"
        @keep="keep()"
        @purge="purge()"
      />

      <v-btn
        v-if="!embed && user.canAdd('element:add')"
        @click="vschemas = true"
        :title="$gettext('Add element')"
        :disabled="loading"
        :icon="mdiPlus"
        class="btn-add"
        color="primary"
        variant="tonal"
      />
    </div>

    <ListSearch ref="search" v-model="term" />

    <div class="layout">
      <ListReload :loading="loading" :outdated="outdated" :title="$gettext('Reload elements')" @reload="reload()" />

      <ListSort v-model="sort" :options="sortOptions" />
    </div>
  </div>

  <v-list class="items" @keydown="listKey">
    <v-list-item v-for="item in items" :key="item.id" :data-id="item.id">
      <div class="actions">
        <v-checkbox-btn
          :model-value="checked.has(item.id)"
          @update:model-value="toggleCheck(item)"
          :aria-label="$gettext('Toggle selection')"
          :class="{ draft: !item.published }"
          class="item-check"
        />

        <span class="btn-actions">
          <ListItemMenu
            type="element"
            :item="item"
            @publish="publish(item)"
            @edit="edit(item)"
            @drop="drop(item)"
            @keep="keep(item)"
            @purge="purge(item)"
          />
        </span>
      </div>

      <a
        href="#"
        class="item-content"
        @click.prevent="$emit('select', item)"
        :class="{ trashed: item.deleted_at }"
        :title="title(item)"
      >
        <div class="item-text">
          <div class="item-head">
            <span class="item-lang" v-if="item.lang">{{ item.lang }}</span>
            <v-icon v-if="item.publish_at" class="publish-at" :icon="mdiClockOutline" />
            <span class="item-title">{{ item.name || $gettext('New') }}</span>
          </div>
          <div class="item-type item-subtitle">{{ item.type?.replace('::', ' ') }}</div>
        </div>

        <div class="item-aux">
          <div class="item-editor">{{ item.editor }}</div>
          <div class="item-modified item-subtitle">
            {{ formatDate(item.updated_at, $vuetify.locale.current) }}
          </div>
        </div>
      </a>
    </v-list-item>
  </v-list>

  <ListStatus
    :empty="!items?.length"
    :filtered="filtered"
    :loading="loading"
    :resettable="!!(term || defaults)"
    @reset="resetFilter()"
  />

  <v-pagination v-if="last > 1" v-model="page" :length="last"></v-pagination>

  <div v-if="!embed && user.canAdd('element:add')" class="btn-group">
    <v-btn
      @click="vschemas = true"
      :title="$gettext('Add element')"
      :disabled="loading"
      :icon="mdiPlus"
      class="btn-add"
      color="primary"
      variant="tonal"
    />
  </div>

  <SchemaDialog v-model="vschemas" :elements="false" @add="add($event)" />

  <EditBulkDialog v-model="editDialog" :count="editIds.length" @apply="save" />
</template>

<style scoped>
.items {
  margin: 0;
}

.items .v-list-item {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0;
  contain-intrinsic-size: auto 56px;
  content-visibility: auto;
  padding: 4px 0;
}

.items .v-list-item > * {
  display: flex;
  align-items: center;
}

.items .actions {
  display: flex;
  flex-wrap: wrap;
  max-width: 48px;
  flex-shrink: 0;
  margin-inline-end: 8px;
}

.items .item-aux {
  text-align: end;
  width: 100%;
}

@media (min-width: 360px) {
  .items .actions {
    max-width: 33%;
  }

  .items .item-aux {
    width: unset;
  }
}
</style>
