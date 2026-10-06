/** @license MIT, https://opensource.org/license/mit */

<script>
import { markRaw } from 'vue'
import gql from 'graphql-tag'
import ActionItem from './ActionItem.vue'
import ActionMenu from './ActionMenu.vue'
import ListStatus from './ListStatus.vue'
import SchemaDialog from './SchemaDialog.vue'
import EditBulkDialog from './EditBulkDialog.vue'
import ListSort from './ListSort.vue'
import { FILE_FIELDS, normalizeFile } from '../files'
import { listBase, useList } from '../lists'
import { frozenParse, safeParse } from '../utils'

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
    ActionItem,
    ActionMenu,
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
      sortOptions: SORT_OPTIONS
    }
  },

  methods: {
    add(item) {
      if (this.embed || !this.user.can('element:add')) return this.messages.denied()

      return this.$apollo
        .mutate({
          mutation: ADD_ELEMENT,
          variables: {
            input: {
              type: item.type,
              name: '',
              data: '{}'
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
          this.$log(`ElementListItems::add(): Error adding shared element`, error)
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
        updated_at: entry.latest?.created_at || entry.updated_at,
        editor: entry.latest?.editor || entry.editor,
        published: entry.latest?.published ?? true,
        publish_at: entry.latest?.publish_at || null,
        latest_id: entry.latest?.id || null,
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
        :model-value="checked.size > 0"
        @click.stop="toggle()"
        :aria-label="$gettext('Toggle selection')"
      />

      <span class="btn-actions">
        <ActionMenu>
          <template #activator="{ props, label }">
            <v-btn
              v-bind="props"
              :disabled="!isChecked || embed || !user.can('element:add')"
              :title="label"
              :icon="mdiDotsVertical"
              variant="text"
            />
          </template>
          <ActionItem
            v-show="counts.draft && user.can('element:publish')"
            :prepend-icon="mdiPublish"
            @click="publish()"
            >{{ $gettext('Publish') }} ({{ counts.draft }})</ActionItem
          >
          <ActionItem v-show="isChecked && user.can('element:save')" :prepend-icon="mdiPencil" @click="edit()">
            {{ $gettext('Edit properties') }} ({{ counts.all }})
          </ActionItem>
          <ActionItem v-show="counts.live && user.can('element:drop')" :prepend-icon="mdiDelete" @click="drop()">
            {{ $gettext('Delete') }} ({{ counts.live }})
          </ActionItem>
          <ActionItem
            v-show="counts.trashed && user.can('element:keep')"
            :prepend-icon="mdiDeleteRestore"
            @click="keep()"
            >{{ $gettext('Restore') }} ({{ counts.trashed }})</ActionItem
          >
          <ActionItem v-show="isChecked && user.can('element:purge')" :prepend-icon="mdiDeleteForever" @click="purge()">
            {{ $gettext('Purge') }} ({{ counts.all }})
          </ActionItem>
        </ActionMenu>
      </span>

      <v-btn
        v-if="!this.embed && this.user.can('element:add')"
        @click="vschemas = true"
        :title="$gettext('Add element')"
        :disabled="loading"
        :icon="mdiPlus"
        class="btn-add"
        color="primary"
        variant="tonal"
      />
    </div>

    <div class="search">
      <v-text-field
        ref="search"
        v-model="term"
        :prepend-inner-icon="mdiMagnify"
        variant="underlined"
        :label="$gettext('Search for')"
        hide-details
        clearable
      ></v-text-field>
    </div>

    <div class="layout">
      <v-btn
        v-if="outdated"
        @click="reload()"
        :prepend-icon="mdiRefresh"
        :title="$gettext('Updated by another user')"
        color="warning"
        variant="tonal"
        size="small"
        rounded="lg"
        class="btn-outdated"
        >{{ $gettext('Refresh') }}</v-btn
      >

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

  <v-list class="items" @keydown="listKey">
    <v-list-item v-for="item in items" :key="item.id" :data-id="item.id">
      <div class="actions">
        <v-checkbox-btn
          :model-value="checked.has(item.id)"
          @update:model-value="toggleCheck(item)"
          :class="{ draft: !item.published }"
          class="item-check"
        />

        <span class="btn-actions">
          <ActionMenu>
            <template #activator="{ props, label }">
              <v-btn v-bind="props" :title="label" :icon="mdiDotsVertical" variant="text" />
            </template>
            <ActionItem
              v-show="!item.deleted_at && !item.published && this.user.can('element:publish')"
              :prepend-icon="mdiPublish"
              @click="publish(item)"
              >{{ $gettext('Publish') }}</ActionItem
            >

            <v-divider
              v-if="
                !item.deleted_at &&
                !item.published &&
                user.can('element:publish') &&
                user.can('element:save')
              "
            ></v-divider>

            <ActionItem v-if="user.can('element:save')" :prepend-icon="mdiPencil" @click="edit(item)">
              {{ $gettext('Edit properties') }}
            </ActionItem>

            <v-divider v-if="user.can('element:save')"></v-divider>

            <ActionItem
              v-if="!item.deleted_at && this.user.can('element:drop')"
              :prepend-icon="mdiDelete"
              @click="drop(item)"
              >{{ $gettext('Delete') }}</ActionItem
            >
            <ActionItem
              v-if="item.deleted_at && this.user.can('element:keep')"
              :prepend-icon="mdiDeleteRestore"
              @click="keep(item)"
              >{{ $gettext('Restore') }}</ActionItem
            >
            <ActionItem v-if="this.user.can('element:purge')" :prepend-icon="mdiDeleteForever" @click="purge(item)">
              {{ $gettext('Purge') }}
            </ActionItem>
          </ActionMenu>
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
            {{ new Date(item.updated_at).toLocaleString() }}
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

  <div v-if="!this.embed && this.user.can('element:add')" class="btn-group">
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

.items .v-selection-control {
  flex-grow: unset;
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
