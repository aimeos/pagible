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
            this.messages.add(this.$gettext(`Error adding file %{path}`, {path: file.name}), 'error')
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
          this.messages.add(this.$gettext('Error trashing file'), 'error')
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
          this.messages.add(this.$gettext('Error restoring file'), 'error')
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
          this.messages.add(this.$gettext('Error publishing file'), 'error')
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
          this.messages.add(this.$gettext('Error purging file'), 'error')
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
            })
          })
          this.checked = false
          this.loading = false

          return this.items
        }).catch(error => {
          this.messages.add(this.$gettext('Error fetching files'), 'error')
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
      <v-menu location="bottom left">
        <template #activator="{ props }">
          <v-btn v-bind="props"
            :disabled="!isChecked && this.embed"
            append-icon="mdi-menu-down"
            variant="text"
          >{{ $gettext('Actions') }}</v-btn>
        </template>
        <v-list>
          <v-list-item v-if="isChecked && auth.can('file:publish')">
            <v-btn prepend-icon="mdi-publish" variant="text" @click="publish()">{{ $gettext('Publish') }}</v-btn>
          </v-list-item>
          <v-list-item v-if="!this.embed && auth.can('file:add')">
            <v-btn prepend-icon="mdi-folder-plus" variant="text" @click="$refs.upload.click()">{{ $gettext('Add files') }}</v-btn>
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
        variant="flat"
      />
      <v-btn v-if="vgrid"
        @click="vgrid = false"
        :title="$gettext('List view')"
        icon="mdi-format-list-bulleted-square"
        variant="flat"
      />

      <v-menu location="bottom right">
        <template #activator="{ props }">
          <v-btn v-bind="props"
            :title="$gettext('Sort by')"
            append-icon="mdi-menu-down"
            prepend-icon="mdi-sort"
            variant="text">
            {{ sort?.column === 'ID' ? (sort?.order === 'DESC' ? $gettext('latest') : $gettext('oldest') ) : (sort?.column || '') }}
          </v-btn>
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
        </v-list>
      </v-menu>
    </div>
  </div>

  <v-list class="items" :class="{grid: vgrid, list: !vgrid}">
    <v-list-item v-for="(item, idx) in items" :key="idx">
      <v-checkbox-btn v-model="item._checked" :class="{draft: !item.published}" class="item-check" />

      <v-menu :location="'bottom ' + (vgrid ? 'right' : 'left')">
        <template v-slot:activator="{ props }">
          <v-btn v-bind="props"
            :title="$gettext('Actions')"
            icon="mdi-dots-vertical"
            class="item-menu"
            variant="text"
          />
        </template>
        <v-list>
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
      </v-menu>

      <div class="item-preview" @click="$emit('select', item)" :title="title(item)">
        <v-img v-if="item.previews"
          :src="url(item.path)"
          :srcset="srcset(item.previews)"
          :alt="item.name"
        ></v-img>
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
    />
  </div>
</template>

<style scoped>
  .layoout .v-list-item {
    text-transform: uppercase;
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

  .items.list .item-preview .v-img {
    margin-inline-start: 8px;
    margin-inline-end: 16px;
    cursor: pointer;
    display: none;
    height: 48px;
    width: 72px
  }

  @media (min-width: 500px) {
    .items.list .item-preview .v-img {
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
    border: 1px solid rgb(var(--v-theme-primary));
  }

  .items.grid .v-list-item .item-check,
  .items.grid .v-list-item .item-menu {
    background: rgb(var(--v-theme-surface-variant));
    color: rgb(var(--v-theme-surface));
    opacity: 0.66;
    border-radius: 50%;
    position: absolute;
    display: none;
    z-index: 2;
    top: 0;
  }

  .items.grid .v-list-item:hover .item-menu,
  .items.grid .v-list-item:hover .item-check,
  .items.grid .v-list-item .item-check.draft,
  .items.grid .v-list-item .item-check:has(input:checked) {
    display: block;
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
</style>
