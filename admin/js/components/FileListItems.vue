/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import {
  mdiFormatListBulletedSquare,
  mdiLock,
  mdiMusic,
  mdiPlusLock,
  mdiViewGridOutline,
  mdiYoutube
} from '@mdi/js'
import ActionItem from './ActionItem.vue'
import ActionMenu from './ActionMenu.vue'
import EditBulkDialog from './EditBulkDialog.vue'
import ListStatus from './ListStatus.vue'
import ListSort from './ListSort.vue'
import { createFile, FILE_FIELDS, normalizeFile } from '../files'
import { listBase, useList } from '../lists'
import { fileurl, filesrcset } from '../utils'

const FETCH_FILES = gql`
  ${FILE_FIELDS}
  query (
    $filter: FileFilter
    $sort: [QueryFilesSortOrderByClause!]
    $limit: Int!
    $page: Int!
    $trashed: Trashed
    $publish: Publish
  ) {
    files(
      filter: $filter
      sort: $sort
      first: $limit
      page: $page
      trashed: $trashed
      publish: $publish
    ) {
      data {
        ...CmsFileFields
        latest {
          id
          published
          publish_at
          data
          editor
          created_at
        }
        byversions_count
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
  { column: 'MIME', order: 'ASC', label: 'MIME' },
  { column: 'LANG', order: 'ASC', label: 'Language' },
  { column: 'EDITOR', order: 'ASC', label: 'Editor' },
  { column: 'BYVERSIONS_COUNT', order: 'ASC', label: 'Usage' }
])

export default {
  extends: listBase,

  components: {
    ActionItem,
    ActionMenu,
    EditBulkDialog,
    ListStatus,
    ListSort
  },

  props: {
    grid: { type: Boolean, default: false }
  },

  data() {
    return {
      vgrid: this.user.getData('file', 'grid') ?? this.grid
    }
  },

  setup() {
    return {
      ...useList('file', FETCH_FILES, (vm) => vm.$refs.upload?.click()),
      mdiViewGridOutline,
      mdiFormatListBulletedSquare,
      mdiLock,
      mdiMusic,
      mdiPlusLock,
      mdiYoutube,
      sortOptions: SORT_OPTIONS,
      fileurl,
      filesrcset
    }
  },

  methods: {
    add(ev, disk = 'public') {
      if (this.embed || !this.user.can('file:add')) return this.messages.denied()

      const promises = []
      const files = ev.target.files || ev.dataTransfer.files || []

      if (!files.length) {
        return
      }

      for (const file of files) {
        promises.push(
          createFile(this.$apollo, { disk, file })
            .then((item) => {
              const data = {
                ...item,
                published: true
              }

              this.items.unshift(data)

              if (files.length === 1) {
                this.$emit('select', data)
              }

              return data
            })
            .catch((error) => {
              this.messages.error(this.$gettext(`Error adding file %{path}`, { path: file.name }), error, file)
            })
        )
      }

      return Promise.all(promises).then(() => {
        this.invalidate()
      })
    },

    failed(action) {
      return {
        drop: this.$gettext('Error trashing file'),
        keep: this.$gettext('Error restoring file'),
        pub: this.$gettext('Error publishing file'),
        purge: this.$gettext('Error purging file'),
        save: this.$gettext('Error saving file'),
        search: this.$gettext('Error fetching files')
      }[action]
    },

    hydrate(entry) {
      const latest = entry.latest

      return Object.assign(normalizeFile(entry), {
        disk: entry.disk,
        id: entry.id,
        deleted_at: entry.deleted_at,
        created_at: entry.created_at,
        updated_at: latest?.created_at || entry.updated_at,
        editor: latest?.editor || entry.editor,
        published: latest?.published ?? true,
        publish_at: latest?.publish_at || null,
        latest_id: latest?.id || null,
        usage: entry.byversions_count
      })
    }
  },

  watch: {
    vgrid(val) {
      this.user.saveData('file', 'grid', val)
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
              :disabled="!isChecked || embed || !user.can('file:add')"
              :title="label"
              :icon="mdiDotsVertical"
              variant="text"
            />
          </template>
          <ActionItem v-if="counts.draft && user.can('file:publish')" :prepend-icon="mdiPublish" @click="publish()">
            {{ $gettext('Publish') }} ({{ counts.draft }})
          </ActionItem>
          <ActionItem v-if="isChecked && user.can('file:save')" :prepend-icon="mdiPencil" @click="edit()">
            {{ $gettext('Edit properties') }} ({{ counts.all }})
          </ActionItem>
          <ActionItem v-if="counts.live && user.can('file:drop')" :prepend-icon="mdiDelete" @click="drop()">
            {{ $gettext('Delete') }} ({{ counts.live }})
          </ActionItem>
          <ActionItem v-if="counts.trashed && user.can('file:keep')" :prepend-icon="mdiDeleteRestore" @click="keep()">
            {{ $gettext('Restore') }} ({{ counts.trashed }})
          </ActionItem>
          <ActionItem v-if="isChecked && user.can('file:purge')" :prepend-icon="mdiDeleteForever" @click="purge()">
            {{ $gettext('Purge') }} ({{ counts.all }})
          </ActionItem>
        </ActionMenu>
      </span>

      <div v-if="!this.embed && user.can('file:add')" class="upload-actions">
        <input @change="add($event, 'public')" ref="upload" type="file" multiple hidden />
        <input @change="add($event, 'private')" ref="uploadPrivate" type="file" multiple hidden />
        <v-btn
          @click="$refs.upload.click()"
          :title="$gettext('Add files')"
          :disabled="loading"
          :icon="mdiPlus"
          class="btn-add"
          color="primary"
          variant="tonal"
        />
        <v-btn
          v-if="user.can('file:relocate')"
          @click="$refs.uploadPrivate.click()"
          :title="$gettext('Add files') + ': ' + $gettext('Protect access')"
          :disabled="loading"
          :icon="mdiPlusLock"
          class="btn-add-private"
          color="primary"
          variant="tonal"
        />
      </div>
    </div>

    <div class="search">
      <v-text-field
        ref="search"
        v-model="term"
        :label="$gettext('Search for')"
        :prepend-inner-icon="mdiMagnify"
        variant="underlined"
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
        :title="$gettext('Reload files')"
        :icon="mdiRefresh"
        class="btn-reload"
        variant="text"
      />

      <v-btn
        v-if="!vgrid"
        @click="vgrid = true"
        :title="$gettext('Grid view')"
        :icon="mdiViewGridOutline"
        class="btn-grid"
        variant="text"
      />
      <v-btn
        v-if="vgrid"
        @click="vgrid = false"
        :title="$gettext('List view')"
        :icon="mdiFormatListBulletedSquare"
        class="btn-list"
        variant="text"
      />

      <ListSort v-model="sort" :options="sortOptions" />
    </div>
  </div>

  <v-list
    class="items"
    :class="{ grid: vgrid, list: !vgrid }"
    @keydown="listKey"
  >
    <v-list-item v-for="item in items" :key="item.id" :data-id="item.id">
      <v-checkbox-btn
        :model-value="checked.has(item.id)"
        @update:model-value="toggleCheck(item)"
        :class="{ draft: !item.published }"
        class="item-check"
      />

      <ActionMenu :location="vgrid ? 'start' : 'end center'">
        <template #activator="{ props, label }">
          <v-btn
            v-bind="props"
            :title="label"
            :icon="mdiDotsVertical"
            class="btn-actions item-menu"
            variant="text"
          />
        </template>
        <ActionItem
          v-show="!item.deleted_at && !item.published && user.can('file:publish')"
          :prepend-icon="mdiPublish"
          @click="publish(item)"
          >{{ $gettext('Publish') }}</ActionItem
        >

        <v-divider
          v-if="
            !item.deleted_at && !item.published && user.can('file:publish') && user.can('file:save')
          "
        ></v-divider>

        <ActionItem v-if="user.can('file:save')" :prepend-icon="mdiPencil" @click="edit(item)">
          {{ $gettext('Edit properties') }}
        </ActionItem>

        <v-divider v-if="user.can('file:save')"></v-divider>

        <ActionItem v-if="!item.deleted_at && user.can('file:drop')" :prepend-icon="mdiDelete" @click="drop(item)">
          {{ $gettext('Delete') }}
        </ActionItem>
        <ActionItem
          v-if="item.deleted_at && user.can('file:keep')"
          :prepend-icon="mdiDeleteRestore"
          @click="keep(item)"
          >{{ $gettext('Restore') }}</ActionItem
        >
        <ActionItem v-if="user.can('file:purge')" :prepend-icon="mdiDeleteForever" @click="purge(item)">
          {{ $gettext('Purge') }}
        </ActionItem>
      </ActionMenu>

      <a
        href="#"
        class="item-usage"
        :class="{ notused: !item.usage }"
        @click.prevent="$emit('select', item)"
        :title="title(item)"
      >
        {{ item.usage || 0 }}
      </a>

      <div class="item-preview" @click="$emit('select', item)" :title="title(item)">
        <v-img
          v-if="item.mime?.startsWith('image/') || (item.mime?.startsWith('video/') && Object.values(item.previews).length)"
          :src="fileurl(item, Object.values(item.previews)[0] ?? item.path)"
          :srcset="filesrcset(item)"
          :title="item.name"
          :alt="item.name"
          class="checkered"
        ></v-img>

        <svg
          v-else-if="item.mime?.startsWith('video/') || item.mime?.startsWith('audio/')"
          viewBox="0 0 24 24"
          fill="currentColor"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path :d="item.mime.startsWith('audio/') ? mdiMusic : mdiYoutube" />
        </svg>

        <svg
          v-else
          width="24"
          height="24"
          viewBox="0 0 16 16"
          fill="currentColor"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path
            d="M7.05 11.885c0 1.415-.548 2.206-1.524 2.206C4.548 14.09 4 13.3 4 11.885c0-1.412.548-2.203 1.526-2.203.976 0 1.524.79 1.524 2.203m-1.524-1.612c-.542 0-.832.563-.832 1.612q0 .133.006.252l1.559-1.143c-.126-.474-.375-.72-.733-.72zm-.732 2.508c.126.472.372.718.732.718.54 0 .83-.563.83-1.614q0-.129-.006-.25zm6.061.624V14h-3v-.595h1.181V10.5h-.05l-1.136.747v-.688l1.19-.786h.69v3.633z"
          />
          <path
            d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2M9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"
          />
        </svg>
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
            <v-icon
              v-if="item.disk === 'private'"
              class="item-access"
              :icon="mdiLock"
              :title="$gettext('Protect access')"
            />
            <span class="item-title">{{ item.name }}</span>
          </div>
          <div class="item-mime item-subtitle">{{ item.mime }}</div>
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

  <div v-if="!this.embed && user.can('file:add')" class="btn-group upload-actions">
    <v-btn
      @click="$refs.upload.click()"
      :title="$gettext('Add files')"
      :disabled="loading"
      :icon="mdiPlus"
      class="btn-add"
      color="primary"
      variant="tonal"
    />
    <v-btn
      v-if="user.can('file:relocate')"
      @click="$refs.uploadPrivate.click()"
      :title="$gettext('Add files') + ': ' + $gettext('Protect access')"
      :disabled="loading"
      :icon="mdiPlusLock"
      class="btn-add-private"
      color="primary"
      variant="tonal"
    />
  </div>

  <EditBulkDialog v-model="editDialog" :count="editIds.length" @apply="save" />
</template>

<style scoped>
.upload-actions {
  display: flex;
  gap: 8px;
}

.layout .v-list-item {
  text-transform: uppercase;
}

.items {
  margin: 0;
}

a.item-usage {
  color: inherit;
  text-decoration: none;
}

.items .item-usage {
  text-align: center;
  display: block;
}

.items .item-usage.notused {
  color: rgb(var(--v-theme-error));
}

.items.list .v-list-item {
  content-visibility: auto;
  contain-intrinsic-size: auto 56px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0;
  padding: 4px 0;
}

.items.list .v-list-item > * {
  display: flex;
  align-items: center;
}

.items.list .v-selection-control {
  flex-grow: unset;
}

.items.list .item-usage {
  width: 2rem;
}

.items.list .item-preview {
  justify-content: center;
  align-items: center;
  cursor: pointer;
  display: flex;
  margin: 0 8px;
  height: 48px;
  min-width: 72px;
  max-width: 72px;
}

.items.list .item-preview svg {
  max-height: 100%;
}

.items.list .item-preview .v-img {
  height: 48px;
  width: 72px;
}

.items.list .item-aux {
  display: none;
}

@media (min-width: 480px) {
  .items.list .item-aux {
    display: block;
  }
}

.items.grid {
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  display: grid;
  gap: 8px;
}

.items.grid .v-list-item {
  grid-template-rows: max-content;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  content-visibility: auto;
  contain-intrinsic-size: auto 260px;
}

.items.grid .v-list-item .item-check,
.items.grid .v-list-item .item-menu {
  position: absolute;
  display: block;
  z-index: 2;
  top: 0;
}

.items.grid .v-list-item .item-check {
  left: 0;
}

.items.grid .item-usage {
  margin-top: 8px;
}

.items.grid .v-list-item .item-menu {
  background: rgba(var(--v-theme-surface-variant), 0.8);
  color: rgb(var(--v-theme-on-surface-variant));
  border-radius: 50%;
  right: 0;
}

.items.grid .item-preview {
  display: flex;
  height: 160px;
  z-index: 1;
}

.items.grid .item-preview .v-img {
  display: block;
}

.items.grid .item-content {
  flex-direction: column;
  margin-top: 16px;
  display: none;
}

.items.grid .item-aux {
  text-align: end;
  width: 100%;
}
</style>
