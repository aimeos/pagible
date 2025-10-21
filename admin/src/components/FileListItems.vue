/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

<script>
  import gql from 'graphql-tag'
  import { useAppStore, useAuthStore, useMessageStore } from '../stores'

  export default {
    props: {
      'grid': {type: Boolean, default: false},
      'embed': {type: Boolean, default: false},
      'filter': {type: Object, default: () => ({})},
    },

    emits: ['select'],

    inject: ['debounce', 'url', 'srcset'],

    data() {
      return {
        items: [],
        menu: [],
        term: '',
        sort: {column: 'ID', order: 'DESC'},
        page: 1,
        last: 1,
        limit: 100,
        checked: false,
        loading: true,
        vgrid: false,
      }
    },

    setup() {
      const messages = useMessageStore()
      const auth = useAuthStore()
      const app = useAppStore()

      return { app, auth, messages }
    },

    created() {
      this.searchd = this.debounce(this.search, 500)
      this.vgrid = this.grid
      this.search()
    },

    computed: {
      canTrash() {
        return this.items.some(item => item._checked && !item.deleted_at)
      },

      isChecked() {
        return this.items.some(item => item._checked)
      },

      isTrashed() {
        return this.items.some(item => item._checked && item.deleted_at)
      },

      order() {
        return this.sort?.column === 'ID'
          ? (this.sort?.order === 'DESC' ? this.$gettext('latest') : this.$gettext('oldest') )
          : (this.sort?.column === 'BYVERSIONS_COUNT' ? this.$gettext('usage') : this.sort?.column || '')
      }
    },

    methods: {
      add(ev) {
        if(this.embed || !this.auth.can('file:add')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const promises = []
        const files = ev.target.files || ev.dataTransfer.files || []

        if(!files.length) {
          return
        }

        Array.from(files).forEach(file => {
          promises.push(this.$apollo.mutate({
            mutation: gql`mutation($file: Upload!) {
              addFile(file: $file) {
                id
                lang
                mime
                name
                path
                previews
                description
                transcription
                editor
                created_at
                updated_at
                deleted_at
              }
            }`,
            variables: {
              file: file
            },
            context: {
              hasUpload: true
            }
          }).then(response => {
            if(response.errors) {
              throw response.errors
            }

            const data = response.data?.addFile || {}
            data.previews = JSON.parse(data.previews) || {}
            data.description = JSON.parse(data.description) || {}
            data.transcription = JSON.parse(data.transcription) || {}
            data.published = true

            this.items.unshift(data)
            this.$emit('select', data)

            return data
          }).catch(error => {
            this.messages.add(this.$gettext(`Error adding file %{path}`, {path: file.name}) + ":\n" + error, 'error')
            this.$log(`FileListItems::add(): Error adding file`, ev, error)
          }))
        })

        return Promise.all(promises).then(() => {
          this.invalidate()
        })
      },


      drop(item) {
        if(!this.auth.can('file:drop')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const list = item ? [item] : this.items.filter(item => item._checked)

        if(!list.length) {
          return
        }

        this.$apollo.mutate({
          mutation: gql`
            mutation($id: [ID!]!) {
              dropFile(id: $id) {
                id
              }
            }
          `,
          variables: {
            id: list.map(item => item.id)
          },
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          this.invalidate()
          this.search()
        }).catch(error => {
          this.messages.add(this.$gettext('Error trashing file') + ":\n" + error, 'error')
          this.$log(`FileListItems::drop(): Error trashing file`, item, error)
        })
      },


      invalidate() {
        const cache = this.$apollo.provider.defaultClient.cache
        cache.evict({id: 'ROOT_QUERY', fieldName: 'files'})
        cache.gc()
      },


      keep(item) {
        if(!this.auth.can('file:keep')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const list = item ? [item] : this.items.filter(item => item._checked)

        if(!list.length) {
          return
        }

        this.$apollo.mutate({
          mutation: gql`
            mutation($id: [ID!]!) {
              keepFile(id: $id) {
                id
              }
            }
          `,
          variables: {
            id: list.map(item => item.id)
          },
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          list.forEach(item => {
            item.deleted_at = null
          })

          this.invalidate()
          this.search()
        }).catch(error => {
          this.messages.add(this.$gettext('Error restoring file') + ":\n" + error, 'error')
          this.$log(`FileListItems::keep(): Error restoring file`, item, error)
        })
      },


      publish(item) {
        if(!this.auth.can('file:publish')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const list = item ? [item] : this.items.filter(item => {
          return item._checked && item.id && !item.published
        })

        if(!list.length) {
          return
        }

        this.$apollo.mutate({
          mutation: gql`mutation ($id: [ID!]!) {
            pubFile(id: $id) {
              id
            }
          }`,
          variables: {
            id: list.map(item => item.id)
          }
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          list.forEach(item => {
            item.published = true
            item._checked = false
          })

          this.invalidate()
          this.search()
        }).catch(error => {
          this.messages.add(this.$gettext('Error publishing file') + ":\n" + error, 'error')
          this.$log(`FileListItems::publish(): Error publishing file`, item, error)
        })
      },


      purge(item) {
        if(!this.auth.can('file:purge')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const list = item ? [item] : this.items.filter(item => item._checked)

        if(!list.length) {
          return
        }

        this.$apollo.mutate({
          mutation: gql`
            mutation($id: [ID!]!) {
              purgeFile(id: $id) {
                id
              }
            }
          `,
          variables: {
            id: list.map(item => item.id)
          },
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          this.invalidate()
          this.search()
        }).catch(error => {
          this.messages.add(this.$gettext('Error purging file') + ":\n" + error, 'error')
          this.$log(`FileListItems::purge(): Error purging file`, item, error)
        })
      },


      search() {
        if(!this.auth.can('file:view')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return Promise.resolve([])
        }

        const publish = this.filter.publish || null
        const trashed = this.filter.trashed || 'WITHOUT'
        const filter = {...this.filter}

        delete filter.trashed
        delete filter.publish

        if(this.term) {
          filter.any = this.term
        }

        this.loading = true

        return this.$apollo.query({
          query: gql`
            query($filter: FileFilter, $sort: [QueryFilesSortOrderByClause!], $limit: Int!, $page: Int!, $trashed: Trashed, $publish: Publish) {
              files(filter: $filter, sort: $sort, first: $limit, page: $page, trashed: $trashed, publish: $publish) {
                data {
                  id
                  lang
                  name
                  mime
                  path
                  previews
                  description
                  transcription
                  editor
                  created_at
                  updated_at
                  deleted_at
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
          `,
          variables: {
            filter: filter,
            page: this.page,
            limit: this.limit,
            sort: [this.sort],
            trashed: trashed,
            publish: publish,
          },
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          const files = result.data.files || {}
          const keys = ['previews', 'description', 'transcription']

          this.last = files.paginatorInfo?.lastPage || 1
          this.items = [...files.data || []].map(entry => {
            const item = entry.latest?.data ? JSON.parse(entry.latest?.data) : {
              ...entry,
              previews: JSON.parse(entry.previews || '{}'),
              description: JSON.parse(entry.description || '{}'),
              transcription: JSON.parse(entry.transcription || '{}'),
            }

            keys.forEach(key => item[key] ??= {})

            return Object.assign(item, {
              id: entry.id,
              deleted_at: entry.deleted_at,
              created_at: entry.created_at,
              updated_at: entry.latest?.created_at || entry.updated_at,
              editor: entry.latest?.editor || entry.editor,
              published: entry.latest?.published ?? true,
              publish_at: entry.latest?.publish_at || null,
              latest: entry.latest,
              usage: entry.byversions_count,
            })
          })
          this.checked = false
          this.loading = false

          return this.items
        }).catch(error => {
          this.messages.add(this.$gettext('Error fetching files') + ":\n" + error, 'error')
          this.$log(`FileListItems::search(): Error fetching files`, error)
        })
      },


      title(item) {
        const list = []

        if(item.publish_at) {
          list.push('Publish at: ' + (new Date(item.publish_at)).toLocaleDateString())
        }

        return list.join("\n")
      },


      toggle() {
        this.items.forEach(el => {
          el._checked = !el._checked
        })
      }
    },

    watch: {
      filter: {
        deep: true,
        handler() {
          this.search()
        }
      },


      term() {
        this.searchd()
      },


      page() {
        this.search()
      },


      sort() {
        this.search()
      }
    }
  }
</script>

<template>
  <div class="header">
    <div class="bulk">
      <v-checkbox-btn v-model="checked" @click.stop="toggle()" />
      <v-menu>
        <template #activator="{ props }">
          <v-btn v-bind="props"
            :disabled="!isChecked && (embed || !auth.can('file:add'))"
            append-icon="mdi-menu-down"
            variant="text"
          >{{ $gettext('Actions') }}</v-btn>
        </template>
        <v-list>
          <v-list-item v-if="!embed && auth.can('file:add')">
            <v-btn prepend-icon="mdi-folder-plus" variant="text" @click="$refs.upload.click()">{{ $gettext('Add files') }}</v-btn>
          </v-list-item>
          <v-list-item v-if="isChecked && auth.can('file:publish')">
            <v-btn prepend-icon="mdi-publish" variant="text" @click="publish()">{{ $gettext('Publish') }}</v-btn>
          </v-list-item>
          <v-list-item v-if="canTrash && auth.can('file:drop')">
            <v-btn prepend-icon="mdi-delete" variant="text" @click="drop()">{{ $gettext('Delete') }}</v-btn>
          </v-list-item>
          <v-list-item v-if="isTrashed && auth.can('file:keep')">
            <v-btn prepend-icon="mdi-delete-restore" variant="text" @click="keep()">{{ $gettext('Restore') }}</v-btn>
          </v-list-item>
          <v-list-item v-if="isChecked && auth.can('file:purge')">
            <v-btn prepend-icon="mdi-delete-forever" variant="text" @click="purge()">{{ $gettext('Purge') }}</v-btn>
          </v-list-item>
        </v-list>
      </v-menu>
    </div>

    <div class="search">
      <v-text-field
        v-model="term"
        :label="$gettext('Search for')"
        prepend-inner-icon="mdi-magnify"
        variant="underlined"
        hide-details
        clearable
      ></v-text-field>
    </div>

    <div class="layout">
      <v-btn v-if="!vgrid"
        @click="vgrid = true"
        :title="$gettext('Grid view')"
        icon="mdi-view-grid-outline"
        variant="text"
      />
      <v-btn v-if="vgrid"
        @click="vgrid = false"
        :title="$gettext('List view')"
        icon="mdi-format-list-bulleted-square"
        variant="text"
      />

      <v-menu>
        <template #activator="{ props }">
          <v-btn v-bind="props"
            :title="$gettext('Sort by')"
            append-icon="mdi-menu-down"
            prepend-icon="mdi-sort"
            variant="text"
          >{{ order }}</v-btn>
        </template>
        <v-list>
          <v-list-item>
            <v-btn variant="text" @click="sort = {column: 'ID', order: 'DESC'}">{{ $gettext('latest') }}</v-btn>
          </v-list-item>
          <v-list-item>
            <v-btn variant="text" @click="sort = {column: 'ID', order: 'ASC'}">{{ $gettext('oldest') }}</v-btn>
          </v-list-item>
          <v-list-item>
            <v-btn variant="text" @click="sort = {column: 'NAME', order: 'ASC'}">{{ $gettext('name') }}</v-btn>
          </v-list-item>
          <v-list-item>
            <v-btn variant="text" @click="sort = {column: 'MIME', order: 'ASC'}">{{ $gettext('mime') }}</v-btn>
          </v-list-item>
          <v-list-item>
            <v-btn variant="text" @click="sort = {column: 'LANG', order: 'ASC'}">{{ $gettext('language') }}</v-btn>
          </v-list-item>
          <v-list-item>
            <v-btn variant="text" @click="sort = {column: 'EDITOR', order: 'ASC'}">{{ $gettext('editor') }}</v-btn>
          </v-list-item>
          <v-list-item>
            <v-btn variant="text" @click="sort = {column: 'BYVERSIONS_COUNT', order: 'ASC'}">{{ $gettext('usage') }}</v-btn>
          </v-list-item>
        </v-list>
      </v-menu>
    </div>
  </div>

  <v-list class="items" :class="{grid: vgrid, list: !vgrid}">
    <v-list-item v-for="(item, idx) in items" :key="idx">
      <v-checkbox-btn v-model="item._checked" :class="{draft: !item.published}" class="item-check" />

      <component :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
        v-model="menu[idx]"
        transition="scale-transition"
        :location="vgrid ? 'start' : 'end center'"
        max-width="300">

        <template v-slot:activator="{ props }">
          <v-btn v-bind="props"
            :title="$gettext('Actions')"
            icon="mdi-dots-vertical"
            class="item-menu"
            variant="text"
          />
        </template>
        <v-card>
          <v-toolbar density="compact">
            <v-toolbar-title>{{ $gettext('Actions') }}</v-toolbar-title>
            <v-btn icon="mdi-close" @click="menu[idx] = false" />
          </v-toolbar>

          <v-list @click="menu[idx] = false">
            <v-list-item v-show="!item.deleted_at && !item.published && auth.can('file:publish')">
              <v-btn prepend-icon="mdi-publish" variant="text" @click="publish(item)">{{ $gettext('Publish') }}</v-btn>
            </v-list-item>
            <v-list-item v-if="!item.deleted_at && auth.can('file:drop')">
              <v-btn prepend-icon="mdi-delete" variant="text" @click="drop(item)">{{ $gettext('Delete') }}</v-btn>
            </v-list-item>
            <v-list-item v-if="item.deleted_at && auth.can('file:keep')">
              <v-btn prepend-icon="mdi-delete-restore" variant="text" @click="keep(item)">{{ $gettext('Restore') }}</v-btn>
            </v-list-item>
            <v-list-item v-if="auth.can('file:purge')">
              <v-btn prepend-icon="mdi-delete-forever" variant="text" @click="purge(item)">{{ $gettext('Purge') }}</v-btn>
            </v-list-item>
          </v-list>
        </v-card>
      </component>

      <div class="item-usage" :class="{notused: !item.usage}" @click="$emit('select', item)" :title="title(item)">
        {{ item.usage }}
      </div>

      <div class="item-preview" @click="$emit('select', item)" :title="title(item)">
        <v-img v-if="item.mime?.startsWith('image/')"
          :src="url(item.path)"
          :srcset="srcset(item.previews)"
          :title="item.name"
        ></v-img>

        <v-img v-else-if="item.mime?.startsWith('video/') && Object.values(item.previews).length"
          :src="url(Object.values(item.previews)[0] ?? '')"
          :srcset="srcset(item.previews)"
          :title="item.name"
        ></v-img>

        <svg v-else-if="item.mime?.startsWith('video/') && !Object.values(item.previews).length" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
          <path d="M10,15L15.19,12L10,9V15M21.56,7.17C21.69,7.64 21.78,8.27 21.84,9.07C21.91,9.87 21.94,10.56 21.94,11.16L22,12C22,14.19 21.84,15.8 21.56,16.83C21.31,17.73 20.73,18.31 19.83,18.56C19.36,18.69 18.5,18.78 17.18,18.84C15.88,18.91 14.69,18.94 13.59,18.94L12,19C7.81,19 5.2,18.84 4.17,18.56C3.27,18.31 2.69,17.73 2.44,16.83C2.31,16.36 2.22,15.73 2.16,14.93C2.09,14.13 2.06,13.44 2.06,12.84L2,12C2,9.81 2.16,8.2 2.44,7.17C2.69,6.27 3.27,5.69 4.17,5.44C4.64,5.31 5.5,5.22 6.82,5.16C8.12,5.09 9.31,5.06 10.41,5.06L12,5C16.19,5 18.8,5.16 19.83,5.44C20.73,5.69 21.31,6.27 21.56,7.17Z" />
        </svg>

        <svg v-else-if="item.mime?.startsWith('audio/')" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M21,3V15.5A3.5,3.5 0 0,1 17.5,19A3.5,3.5 0 0,1 14,15.5A3.5,3.5 0 0,1 17.5,12C18.04,12 18.55,12.12 19,12.34V6.47L9,8.6V17.5A3.5,3.5 0 0,1 5.5,21A3.5,3.5 0 0,1 2,17.5A3.5,3.5 0 0,1 5.5,14C6.04,14 6.55,14.12 7,14.34V6L21,3Z" />
        </svg>

        <svg v-else width="24" height="24" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
          <path d="M7.05 11.885c0 1.415-.548 2.206-1.524 2.206C4.548 14.09 4 13.3 4 11.885c0-1.412.548-2.203 1.526-2.203.976 0 1.524.79 1.524 2.203m-1.524-1.612c-.542 0-.832.563-.832 1.612q0 .133.006.252l1.559-1.143c-.126-.474-.375-.72-.733-.72zm-.732 2.508c.126.472.372.718.732.718.54 0 .83-.563.83-1.614q0-.129-.006-.25zm6.061.624V14h-3v-.595h1.181V10.5h-.05l-1.136.747v-.688l1.19-.786h.69v3.633z"/>
          <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2M9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/>
        </svg>
      </div>

      <div class="item-content" @click="$emit('select', item)" :class="{trashed: item.deleted_at}" :title="title(item)">
        <div class="item-text">
          <div class="item-head">
            <span class="item-lang" v-if="item.lang">{{ item.lang }}</span>
            <v-icon v-if="item.publish_at" class="publish-at" icon="mdi-clock-outline" />
            <span class="item-title">{{ item.name }}</span>
          </div>
          <div class="item-mime item-subtitle">{{ item.mime }}</div>
        </div>

        <div class="item-aux">
          <div class="item-editor">{{ item.editor }}</div>
          <div class="item-modified item-subtitle">{{ (new Date(item.updated_at)).toLocaleString() }}</div>
        </div>
      </div>
    </v-list-item>
  </v-list>

  <p v-if="loading" class="loading">
    {{ $gettext('Loading') }}
    <svg class="spinner" width="32" height="32" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <circle class="spin1" cx="4" cy="12" r="3"/>
      <circle class="spin1 spin2" cx="12" cy="12" r="3"/>
      <circle class="spin1 spin3" cx="20" cy="12" r="3"/>
    </svg>
  </p>

  <p v-if="!loading && !items.length" class="notfound">
    {{ $gettext('No entries found') }}
  </p>

  <v-pagination v-if="last > 1"
    v-model="page"
    :length="last"
  ></v-pagination>

  <div v-if="!this.embed && auth.can('file:add')" class="btn-group">
    <input @change="add($event)"
      ref="upload"
      type="file"
      multiple
      hidden
    />
    <v-btn
      @click="$refs.upload.click()"
      :title="$gettext('Add files')"
      icon="mdi-folder-plus"
      color="primary"
      variant="flat"
    />
  </div>
</template>

<style scoped>
  .layout .v-list-item {
    text-transform: uppercase;
  }

  .items .item-usage {
    text-align: center;
  }

  .items .item-usage.notused {
    color: rgb(var(--v-theme-error));
  }

  .items .item-preview .v-img {
    background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAIAQMAAAD+wSzIAAAABlBMVEX////Ly8vsgL9iAAAADklEQVQI12P4AIX8EAgALgAD/aNpbtEAAAAASUVORK5CYII=);
    background-repeat: repeat;
  }

  .items.list .v-list-item {
    border-bottom: 1px solid rgba(var(--v-border-color), 0.38);
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
    grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
    display: grid;
    gap: 16px;
  }

  .items.grid .v-list-item {
    grid-template-rows: max-content;
    border: 1px solid rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  }

  .items.grid .v-list-item .item-check,
  .items.grid .v-list-item .item-menu {
    background: rgb(var(--v-theme-surface-variant));
    color: rgb(var(--v-theme-surface));
    opacity: 0.66;
    border-radius: 50%;
    position: absolute;
    display: block;
    z-index: 2;
    top: 0;
  }

  .items.grid .v-list-item .item-check {
    left: 0;
  }

  .items.grid .v-list-item .item-menu {
    right: 0;
  }

  .items.grid .item-preview {
    display: flex;
    height: 180px;
    z-index: 1;
  }

  .items.grid .item-preview .v-img {
    display: block;
  }

  .items.grid .item-open {
    display: none;
  }

  .items.grid .item-content {
    flex-direction: column;
  }

  .items.grid .item-aux {
    text-align: start;
    width: 100%;
  }
</style>
