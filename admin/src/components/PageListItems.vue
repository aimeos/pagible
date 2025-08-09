<script>
  import gql from 'graphql-tag'
  import { Draggable } from '@he-tree/vue'
  import { dragContext } from '@he-tree/vue'
  import { useAppStore, useAuthStore, useLanguageStore, useMessageStore } from '../stores'

  export default {
    components: {
      Draggable
    },

    props: {
      'embed': {type: Boolean, default: false},
      'filter': {type: Object, default: () => ({})},
    },

    emits: ['select'],

    inject: ['debounce'],

    data() {
      return {
        menu: {},
        items: [],
        loading: true,
        checked: null,
        clip: null,
        term: '',
      }
    },

    setup() {
      const languages = useLanguageStore()
      const messages = useMessageStore()
      const auth = useAuthStore()
      const app = useAppStore()

      return { app, auth, languages, messages }
    },

    created() {
      this.searchd = this.debounce(this.search, 500)

      this.fetch().then(result => {
        this.items = result.data
        this.loading = false
      })
    },

    mounted() {
      this.checked = false // required for isChecked() to work correctly
    },

    computed: {
      canTrash() {
        return this.isChecked && this.$refs.tree?.statsFlat.some(stat => stat._checked && !stat.data.deleted_at)
      },

      isChecked() {
        return this.checked || this.$refs.tree?.statsFlat.some(stat => stat._checked)
      },

      isTrashed() {
        return this.isChecked && this.$refs.tree?.statsFlat.some(stat => stat._checked && stat.data.deleted_at)
      },
    },

    methods: {
      add() {
        if(this.embed || !this.auth.can('page:add')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const item = this.create()

        this.$apollo.mutate({
          mutation: gql`mutation ($input: PageInput!) {
            addPage(input: $input) {
              id
            }
          }`,
          variables: {
            input: item
          }
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          if(!result.data.addPage) {
            throw new Error('No data in addPage mutation result')
          }

          const page = {...result.data.addPage}

          this.$refs.tree.add(page)
          this.$emit('select', page)
        }).catch(error => {
          this.messages.add(this.$gettext('Error adding root page'), 'error')
          this.$log(`PageList::add(): Error adding root page`, error)
        })
      },


      change() {
        if(!this.auth.can('page:move')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const parent = dragContext.targetInfo.parent
        const siblings = dragContext.targetInfo.siblings
        const ref = siblings[dragContext.targetInfo.indexBeforeDrop+1] || null

        this.$apollo.mutate({
          mutation: gql`mutation ($id: ID!, $parent: ID, $ref: ID) {
            movePage(id: $id, parent: $parent, ref: $ref) {
              id
            }
          }`,
          variables: {
            id: dragContext.startInfo.dragNode.data.id,
            parent: parent ? parent.data.id : null,
            ref: ref ? ref.data.id : null
          }
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          const srcparent = dragContext.startInfo.parent

          if(srcparent?.data && !srcparent?.children.length) {
            srcparent.data.has = false
          }

          if(parent) {
            parent.data.has = true
          }
        }).catch(error => {
          this.messages.add(this.$gettext('Error moving page'), 'error')
          this.$log(`PageList::change(): Error moving page`, error)
        })
      },


      copy(stat, node) {
        this.clip = {type: 'copy', node: node, stat: stat}
      },


      create(attr = {}) {
        return Object.assign({
          path: '_' + Math.floor(Math.random() * 10000),
          lang: this.$vuetify.locale.current || this.languages.default(),
          status: 0,
          cache: 5
        }, attr)
      },


      cut(stat, node) {
        this.$refs.tree.statsFlat.forEach(stat => {
          delete stat.cut
        })
        stat.cut = true

        this.clip = {type: 'cut', node: node, stat: stat}
      },


      drop(stat) {
        if(!this.auth.can('page:drop')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const list = (stat ? [stat] : this.$refs.tree.statsFlat.filter(stat => {
          return stat._checked && stat.data?.id
        }))

        if(!list.length) {
          return
        }

        this.$apollo.mutate({
          mutation: gql`mutation ($id: [ID!]!) {
            dropPage(id: $id) {
              id
            }
          }`,
          variables: {
            id: list.map(item => item.data.id)
          }
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          for(const item of list) {
            this.update(item, (item) => {
              item.data.deleted_at = (new Date).toISOString().replace(/T/, ' ').substring(0, 19)
              item.check = false
            })
          }

          this.invalidate()
        }).catch(error => {
          this.messages.add(this.$gettext('Error trashing page'), 'error')
          this.$log(`PageList::drop(): Error trashing page`, list, error)
        })
      },


      fetch(parent = null, page = 1, limit = 100) {
        if(!this.auth.can('page:view')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return Promise.resolve([])
        }

        const publish = this.filter.publish || null
        const trashed = this.filter.trashed || 'WITHOUT'
        const filter = {...this.filter}

        delete filter.trashed
        delete filter.publish
        delete filter.view

        filter.parent_id = parent

        return this.$apollo.query({
          query: gql`query($filter: PageFilter, $limit: Int!, $page: Int!, $trashed: Trashed, $publish: Publish) {
            pages(filter: $filter, first: $limit, page: $page, trashed: $trashed, publish: $publish) {
              data {
                ${this.fields()}
              }
              paginatorInfo {
                currentPage
                lastPage
              }
            }
          }`,
          variables: {
            filter: filter,
            page: page,
            limit: limit,
            trashed: trashed,
            publish: publish
          }
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          return this.transform(result.data.pages)
        }).catch(error => {
          this.messages.add(this.$gettext('Error fetching pages'), 'error')
          this.$log(`PageList::fetch(): Error fetching page`, parent, page, limit, error)
        })
      },


      fields() {
        return `id
          parent_id
          created_at
          deleted_at
          editor
          has
          latest {
            id
            published
            publish_at
            data
            editor
            created_at
          }`
      },


      insert(stat, idx = null) {
        if(!this.auth.can('page:add')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const siblings = this.$refs.tree.getSiblings(stat)
        const parent = idx !== null ? stat.parent : stat
        const pos = siblings.indexOf(stat)
        const node = this.create()
        let refid = null

        if(idx === null && !stat.open) {
          this.load(stat, stat.data)
        }

        switch(idx) {
          case 0: refid = stat.data.id; break
          case null: refid = stat.children && stat.children[0] ? stat.children[0].data.id : null; break
          case 1: refid = siblings[pos + 1] ? siblings[pos + 1].data.id : null; break
        }

        this.$apollo.mutate({
          mutation: gql`mutation ($input: PageInput!, $parent: ID, $ref: ID) {
            addPage(input: $input, parent: $parent, ref: $ref) {
              id
            }
          }`,
          variables: {
            input: node,
            parent: parent ? parent.data.id : null,
            ref: refid
          }
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          node.id = result.data.addPage.id

          if(idx !== null || stat.open) {
            this.$refs.tree.add(node, parent, idx !== null ? pos + idx : 0)
          }

          if(parent) {
            parent.data.has = true
          }

          this.invalidate()
        }).catch(error => {
          this.messages.add(this.$gettext('Error inserting page'), 'error')
          this.$log(`PageList::insert(): Error inserting page`, error)
        })
      },


      invalidate() {
        const cache = this.$apollo.provider.defaultClient.cache
        cache.evict({id: 'ROOT_QUERY', fieldName: 'pages'})
        cache.gc()
      },


      keep(stat) {
        if(!this.auth.can('page:keep')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const stats = stat ? [stat] : this.$refs.tree.statsFlat.filter(stat => {
          return stat._checked && stat.data.id && stat.data.deleted_at
        })
        const list = stats.filter(stat => {
          return stats.indexOf(stat.parent) === -1
        })
        const deleted_at = stat.data.deleted_at || null

        if(!list.length) {
          return
        }

        this.$apollo.mutate({
          mutation: gql`mutation ($id: [ID!]!) {
            keepPage(id: $id) {
              id
            }
          }`,
          variables: {
            id: list.map(item => item.data.id)
          }
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          for(const item of list) {
            this.update(item, (item) => {
              if(deleted_at >= item.data.deleted_at) {
                item.data.deleted_at = null
                item.check = false
              }
            })
          }

          this.invalidate()
        }).catch(error => {
          this.messages.add(this.$gettext('Error restoring page'), 'error')
          this.$log(`PageList::keep(): Error restoring page`, list, error)
        })
      },


      load(stat, node) {
        if(!stat.open && !node.children) {
          stat.loading = true

          this.fetch(node.id, stat.page ? stat.page + 1 : 1).then(result => {
            this.$refs.tree.addMulti(result.data, stat, 0)
            stat.page = result.currentPage || 1
          }).finally(() => {
            stat.loading = false
          })
        }

        stat.open = !stat.open
      },


      move(stat, idx = null) {
        if(!this.auth.can('page:move')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const siblings = this.$refs.tree.getSiblings(stat)
        const parent = idx !== null ? stat.parent : stat
        const pos = siblings.indexOf(stat)
        let refid = null

        switch(idx) {
          case 0: refid = stat.data.id; break
          case null: refid = stat.children && stat.children[0] ? stat.children[0].data.id : null; break
          case 1: refid = siblings[pos + 1] ? siblings[pos + 1].data.id : null; break
        }

        this.$apollo.mutate({
          mutation: gql`mutation ($id: ID!, $parent: ID, $ref: ID) {
            movePage(id: $id, parent: $parent, ref: $ref) {
              id
            }
          }`,
          variables: {
            id: this.clip.node.id,
            parent: parent ? parent.data.id : null,
            ref: refid
          }
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          const index = idx !== null ? (pos < this.$refs.tree.getSiblings(stat).indexOf(this.clip.stat) ? pos + idx : pos) : 0

          this.$refs.tree.move(this.clip.stat, parent, index)

          if(parent) {
            if(!this.clip.stat.children?.length) {
              stat.parent.data.has = false
            }
            parent.data.has = true
          }

          this.invalidate()
        }).catch(error => {
          this.messages.add(this.$gettext('Error moving page'), 'error')
          this.$log(`PageList::move(): Error moving page`, stat, idx, error)
        })

        this.show()
      },


      paste(stat, idx = null) {
        if(!this.auth.can('page:add')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const siblings = this.$refs.tree.getSiblings(stat)
        const parent = idx !== null ? stat.parent : stat
        const pos = siblings.indexOf(stat)
        const node = {...this.clip.node}
        let refid = null

        switch(idx) {
          case 0: refid = stat.data.id; break
          case null: refid = stat.children && stat.children[0] ? stat.children[0].data.id : null; break
          case 1: refid = siblings[pos + 1] ? siblings[pos + 1].data.id : null; break
        }

        return this.$apollo.query({
          query: gql`query($id: ID!) {
            page(id: $id) {
              meta
              config
              content
              files {
                id
              }
              elements {
                id
              }
            }
          }`,
          variables: {
            id:  node.id
          }
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          this.$apollo.mutate({
            mutation: gql`mutation ($input: PageInput!, $parent: ID, $ref: ID, $elements: [ID!], $files: [ID!]) {
              addPage(input: $input, parent: $parent, ref: $ref, elements: $elements, files: $files) {
                ${this.fields()}
              }
            }`,
            variables: {
              input: {
                status: 0,
                to: node.to,
                tag: node.tag,
                type: node.type,
                theme: node.theme,
                lang: node.lang,
                name: node.name,
                title: node.title,
                cache: node.cache,
                domain: node.domain,
                related_id: node.id,
                meta: result?.data?.page?.meta || '{}',
                config: result?.data?.page?.config || '{}',
                content: result?.data?.page?.content || '[]',
                path: node.path + '_' + Math.floor(Math.random() * 10000),
              },
              parent: parent ? parent.data.id : null,
              ref: refid,
              elements: result?.data?.page?.elements.map(el => el.id) || [],
              files: result?.data?.page?.files.map(file => file.id) || []
            }
          }).then(result => {
            if(result.errors) {
              throw result.errors
            }

            if(!result.data.addPage) {
              throw new Error('No page data returned')
            }

            const index = idx !== null ? this.$refs.tree.getSiblings(stat).indexOf(stat) + idx : 0
            const item = result.data.addPage

            this.$refs.tree.add(item, parent, index)
            this.invalidate()
          }).catch(error => {
            this.messages.add(this.$gettext('Error copying page'), 'error')
            this.$log(`PageList::paste(): Error copying page`, stat, idx, error)
          })
        }).catch(error => {
          this.messages.add(this.$gettext('Error fetching page'), 'error')
          this.$log(`PageList::paste(): Error fetching page`, node.id, error)
        })

        this.show()
      },


      publish(stat) {
        if(!this.auth.can('page:publish')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const list = stat ? [stat] : this.$refs.tree.statsFlat.filter(stat => {
          return stat._checked && stat.data.id && !stat.data.published
        })

        if(!list.length) {
          return
        }

        this.$apollo.mutate({
          mutation: gql`mutation ($id: [ID!]!) {
            pubPage(id: $id) {
              id
            }
          }`,
          variables: {
            id: list.map(item => item.data.id)
          }
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          for(const item of list) {
            item.data.published = true
            item.check = false
          }

          this.invalidate()
        }).catch(error => {
          this.messages.add(this.$gettext('Error publishing page'), 'error')
          this.$log(`PageList::publish(): Error publishing page`, list, error)
        })
      },


      purge(stat) {
        if(!this.auth.can('page:purge')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const list = stat ? [stat] : this.$refs.tree.statsFlat.filter(stat => {
          return stat._checked && stat.data.id
        })

        if(!list.length) {
          return
        }

        this.$apollo.mutate({
          mutation: gql`mutation ($id: [ID!]!) {
            purgePage(id: $id) {
              id
            }
          }`,
          variables: {
            id: list.map(item => item.data.id).reverse()
          }
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          for(const item of list) {
            this.$refs.tree.remove(item)

            if(item.parent && !item.parent.children?.length) {
              item.parent.data.has = false
            }
          }
        }).catch(error => {
          this.messages.add(this.$gettext('Error purging page'), 'error')
          this.$log(`PageList::purge(): Error purging page`, list, error)
        })
      },


      reload(cache = true) {
          this.items = []
          this.loading = true

          if(cache) {
            const cache = this.$apollo.provider.defaultClient.cache
            cache.evict({id: 'ROOT_QUERY', fieldName: 'pages'})
            cache.gc()
          }

          const promise = this.filter.view === 'list' ? this.search() : this.fetch()

          promise.then(result => {
            this.items = result?.data || []
            this.loading = false
          })
      },


      search(page = 1, limit = 100) {
        if(!this.auth.can('page:view')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return Promise.resolve([])
        }

        const publish = this.filter.publish || null
        const trashed = this.filter.trashed || 'WITHOUT'
        const filter = {...this.filter}

        delete filter.trashed
        delete filter.publish
        delete filter.view

        if(this.term) {
          filter.any = this.term
        }

        return this.$apollo.query({
          query: gql`query($filter: PageFilter, $limit: Int!, $page: Int!, $trashed: Trashed, $publish: Publish) {
            pages(filter: $filter, first: $limit, page: $page, trashed: $trashed, publish: $publish) {
              data {
                ${this.fields()}
              }
              paginatorInfo {
                currentPage
                lastPage
              }
            }
          }`,
          variables: {
            filter: filter,
            page: page,
            limit: limit,
            trashed: trashed,
            publish: publish
          }
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          return this.transform(result.data.pages)
        }).catch(error => {
          this.messages.add(this.$gettext('Error searching pages'), 'error')
          this.$log(`PageList::search(): Error searching pages`, page, limit, error)
        })
      },


      show(what = null) {
        if(what === null) {
          this.menu = {}
        } else if(!this.menu[what]) {
          this.menu = {}
          this.menu[what] = true
        } else {
          this.menu[what] = false
        }
      },


      status(stat, val) {
        if(!this.auth.can('page:save')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const list = stat ? [stat] : this.$refs.tree.statsFlat.filter(stat => {
          return stat._checked && stat.data.id
        })

        list.forEach(stat => {
          this.$apollo.mutate({
            mutation: gql`mutation ($id: ID!, $input: PageInput!) {
              savePage(id: $id, input: $input) {
                id
              }
            }`,
            variables: {
              id: stat.data.id,
              input: {
                status: val
              }
            }
          }).then(result => {
            if(result.errors) {
              throw result.errors
            }

            stat.data.status = val
          }).catch(error => {
            this.messages.add(this.$gettext('Error saving page'), 'error')
            this.$log(`PageList::status(): Error saving page`, stat, val, error)
          })
        })
      },


      title(item) {
        const list = []

        if(item.publish_at) {
          list.push('Publish at: ' + (new Date(item.publish_at)).toLocaleDateString())
        }

        if(item.theme) {
          list.push('Theme: ' + item.theme)
        }

        if(item.type) {
          list.push('Page type: ' + item.type)
        }

        if(item.tag) {
          list.push('Tag: ' + item.tag)
        }

        if(item.cache) {
          list.push('Cache: ' + item.cache + ' min')
        }

        return list.join("\n")
      },


      toggle() {
        this.$refs.tree.statsFlat.forEach(stat => {
          stat._checked = !stat._checked
        })
      },


      transform(result) {
        const pages = result.data.map(entry => {
          const item = entry.latest?.data ? JSON.parse(entry.latest.data) : {...entry}

          return Object.assign(item, {
            id: entry.id,
            has: entry.has,
            parent_id: entry.parent_id,
            deleted_at: entry.deleted_at,
            created_at: entry.created_at,
            updated_at: entry.latest?.created_at || entry.updated_at,
            editor: entry.latest?.editor || entry.editor,
            published: entry.latest?.published ?? true,
            publish_at: entry.latest?.publish_at || null,
            latest: entry.latest,
          })
        })

        return {
          data: pages,
          currentPage: result.paginatorInfo.currentPage,
          lastPage: result.paginatorInfo.lastPage
        }
      },


      update(stat, fcn) {
        if(typeof fcn !== 'function') {
          throw new Error('Second paramter must be a function')
        }

        fcn(stat)
        stat.children?.forEach((stat) => {
          fcn(stat, fcn)
        })
      },


      url(node) {
        return this.app.urlpage
          .replace(/_domain_/, node.domain || '')
          .replace(/_path_/, node.path || '')
          .replace(/\/+$/, '')
      }
    },

    watch: {
      filter: {
        deep: true,
        handler(filter) {
          this.items = []
          this.loading = true

          const promise = filter.view === 'list' ? this.search() : this.fetch()

          promise.then(result => {
            this.items = result.data
            this.loading = false
          })
        }
      },


      term: {
        handler() {
          this.reload(false)
        }
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
          <v-btn append-icon="mdi-menu-down" variant="text" v-bind="props" :disabled="!isChecked">{{ $gettext('Actions') }}</v-btn>
        </template>
        <v-list>
          <v-list-item v-if="isChecked && auth.can('page:publish')">
            <v-btn prepend-icon="mdi-publish" variant="text" @click="publish()">{{ $gettext('Publish') }}</v-btn>
          </v-list-item>
          <v-list-item v-if="isChecked && auth.can('page:save')">
            <v-btn prepend-icon="mdi-eye" variant="text" @click="status(null, 1)">{{ $gettext('Enable') }}</v-btn>
          </v-list-item>
          <v-list-item v-if="isChecked && auth.can('page:save')">
            <v-btn prepend-icon="mdi-eye-off" variant="text" @click="status(null, 0)">{{ $gettext('Disable') }}</v-btn>
          </v-list-item>

          <v-divider></v-divider>

          <v-list-item v-if="canTrash && auth.can('page:drop')">
            <v-btn prepend-icon="mdi-delete" variant="text" @click="drop()">{{ $gettext('Delete') }}</v-btn>
          </v-list-item>
          <v-list-item v-if="isTrashed && auth.can('page:keep')">
            <v-btn prepend-icon="mdi-delete-restore" variant="text" @click="keep()">{{ $gettext('Restore') }}</v-btn>
          </v-list-item>
          <v-list-item v-if="isChecked && auth.can('page:purge')">
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

    <v-btn
      @click="reload()"
      :title="$gettext('Reload page tree')"
      icon="mdi-refresh"
      variant="flat"
      class="no-rtl"
    />
  </div>

  <Draggable ref="tree"
    v-model="items"
    @change="change()"
    :defaultOpen="false"
    :disableDrag="!auth.can('page:move')"
    :rtl="$vuetify.locale.isRtl"
    :watermark="false"
    virtualization
  >
    <template #default="{ node, stat }">
      <div class="actions">
        <svg v-if="stat.loading" class="spinner" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <circle class="spin1" cx="4" cy="12" r="3"/>
          <circle class="spin1 spin2" cx="12" cy="12" r="3"/>
          <circle class="spin1 spin3" cx="20" cy="12" r="3"/>
        </svg>
        <v-btn v-else
          @click="load(stat, node)"
          :class="{hidden: !node.has}"
          :icon="stat.open ? 'mdi-menu-down' : 'mdi-menu-right'"
          :title="$gettext('Toggle child nodes')"
          variant="flat"
        />

        <v-checkbox-btn v-model="stat._checked" :class="{draft: !node.published}" />

        <v-menu v-if="node.id">
          <template #activator="{ props }">
            <v-btn v-bind="props"
              :title="$gettext('Actions')"
              icon="mdi-dots-vertical"
              variant="text"
            />
          </template>
          <v-list>
            <v-list-item v-if="!node.deleted_at && !node.published && auth.can('page:publish')">
              <v-btn prepend-icon="mdi-publish" variant="text" @click="publish(stat)">{{ $gettext('Publish') }}</v-btn>
            </v-list-item>
            <v-list-item v-if="node.status !== 0 && auth.can('page:save')">
              <v-btn prepend-icon="mdi-eye-off" variant="text" @click="status(stat, 0)">{{ $gettext('Disable') }}</v-btn>
            </v-list-item>
            <v-list-item v-if="node.status !== 1 && auth.can('page:save')">
              <v-btn prepend-icon="mdi-eye" variant="text" @click="status(stat, 1)">{{ $gettext('Enable') }}</v-btn>
            </v-list-item>
            <v-list-item v-if="node.status !== 2 && auth.can('page:save')">
              <v-btn prepend-icon="mdi-eye-off-outline" variant="text" @click="status(stat, 2)">{{ $gettext('Hide') }}</v-btn>
            </v-list-item>

            <v-divider></v-divider>

            <v-list-item v-if="auth.can('page:move')">
              <v-btn prepend-icon="mdi-content-cut" variant="text" @click="cut(stat, node)">{{ $gettext('Cut') }}</v-btn>
            </v-list-item>
            <v-list-item v-if="!this.embed && auth.can('page:add')">
              <v-btn prepend-icon="mdi-content-copy" variant="text" @click="copy(stat, node)">{{ $gettext('Copy') }}</v-btn>
            </v-list-item>
            <v-list-item v-if="clip && clip.type == 'copy' && !this.embed && auth.can('page:add')">
              <v-btn prepend-icon="mdi-content-paste" variant="text" @click.stop="show('paste')">{{ $gettext('Paste') }}</v-btn>
            </v-list-item>
            <v-fade-transition v-if="clip && clip.type == 'copy' && menu.paste && !this.embed && auth.can('page:add')">
              <v-list-item>
                <v-btn prepend-icon="mdi-arrow-up" variant="text" @click="paste(stat, 0)" class="subitem">{{ $gettext('Before') }}</v-btn>
              </v-list-item>
            </v-fade-transition>
            <v-fade-transition v-if="clip && clip.type == 'copy' && menu.paste && !this.embed && auth.can('page:add')">
              <v-list-item>
                <v-btn prepend-icon="mdi-arrow-right" variant="text" @click="paste(stat)" class="subitem">{{ $gettext('Into') }}</v-btn>
              </v-list-item>
            </v-fade-transition>
            <v-fade-transition v-if="clip && clip.type == 'copy' && menu.paste && !this.embed && auth.can('page:add')">
              <v-list-item>
                <v-btn prepend-icon="mdi-arrow-down" variant="text" @click="paste(stat, 1)" class="subitem">{{ $gettext('After') }}</v-btn>
              </v-list-item>
            </v-fade-transition>
            <v-list-item v-if="clip && clip.type == 'cut' && auth.can('page:move')">
              <v-btn prepend-icon="mdi-content-paste" variant="text" @click.stop="show('move')">{{ $gettext('Paste') }}</v-btn>
            </v-list-item>
            <v-fade-transition v-if="clip && clip.type == 'cut' && menu.move && auth.can('page:move')">
              <v-list-item>
                <v-btn prepend-icon="mdi-arrow-up" variant="text" @click="move(stat, 0)" class="subitem">{{ $gettext('Before') }}</v-btn>
              </v-list-item>
            </v-fade-transition>
            <v-fade-transition v-if="clip && clip.type == 'cut' && menu.move && auth.can('page:move')">
              <v-list-item>
                <v-btn prepend-icon="mdi-arrow-right" variant="text" @click="move(stat)" class="subitem">{{ $gettext('Into') }}</v-btn>
              </v-list-item>
            </v-fade-transition>
            <v-fade-transition v-if="clip && clip.type == 'cut' && menu.move && auth.can('page:move')">
              <v-list-item>
                <v-btn prepend-icon="mdi-arrow-down" variant="text" @click="move(stat, 1)" class="subitem">{{ $gettext('After') }}</v-btn>
              </v-list-item>
            </v-fade-transition>
            <v-list-item v-if="!this.embed && auth.can('page:add')">
              <v-btn prepend-icon="mdi-content-paste" variant="text" @click.stop="show('insert')">{{ $gettext('Insert') }}</v-btn>
            </v-list-item>
            <v-fade-transition v-if="menu.insert && !this.embed && auth.can('page:add')">
              <v-list-item>
                <v-btn prepend-icon="mdi-arrow-up" variant="text" @click="insert(stat, 0)" class="subitem">{{ $gettext('Before') }}</v-btn>
              </v-list-item>
            </v-fade-transition>
            <v-fade-transition v-if="menu.insert && !this.embed && auth.can('page:add')">
              <v-list-item>
                <v-btn prepend-icon="mdi-arrow-right" variant="text" @click="insert(stat)" class="subitem">{{ $gettext('Into') }}</v-btn>
              </v-list-item>
            </v-fade-transition>
            <v-fade-transition v-if="menu.insert && !this.embed && auth.can('page:add')">
              <v-list-item>
                <v-btn prepend-icon="mdi-arrow-down" variant="text" @click="insert(stat, 1)" class="subitem">{{ $gettext('After') }}</v-btn>
              </v-list-item>
            </v-fade-transition>

            <v-divider></v-divider>

            <v-list-item v-if="!node.deleted_at && auth.can('page:drop')">
              <v-btn prepend-icon="mdi-delete" variant="text" @click="drop(stat)">{{ $gettext('Delete') }}</v-btn>
            </v-list-item>
            <v-list-item v-if="node.deleted_at && auth.can('page:keep')">
              <v-btn prepend-icon="mdi-delete-restore" variant="text" @click="keep(stat)">{{ $gettext('Restore') }}</v-btn>
            </v-list-item>
            <v-list-item v-if="auth.can('page:purge')">
              <v-btn prepend-icon="mdi-delete-forever" variant="text" @click="purge(stat)">{{ $gettext('Purge') }}</v-btn>
            </v-list-item>
          </v-list>
        </v-menu>
      </div>
      <div class="item-content"
        :class="{
          'status-hidden': node.status == 2,
          'status-enabled': node.status == 1,
          'status-disabled': !node.status,
          'trashed': node.deleted_at,
          'cut': stat.cut,
        }"
        :title="title(node)"
      >
        <div class="item-text" @click="$emit('select', node)">
          <div class="item-head">
            <span class="item-lang" v-if="node.lang">{{ node.lang }}</span>
            <v-icon v-if="node.publish_at" class="publish-at" icon="mdi-clock-outline" />
            <v-icon v-if="node.status > 1" class="item-status" icon="mdi-eye-off-outline" />
            <span class="item-title">{{ node.name || $gettext('New') }}</span>
          </div>
          <div v-if="node.title" class="item-subtitle">{{ node.title }}</div>
        </div>
        <a class="item-aux" :href="url(node)" target="_blank" draggable="false">
          <div class="item-domain">{{ node.domain }}</div>
          <span class="item-path item-subtitle">{{ url(node) }}</span>
          <span v-if="node.to" class="item-to item-subtitle"> ➔ {{ node.to }}</span>
        </a>
      </div>
    </template>
  </Draggable>

  <p v-if="loading" class="loading">
    {{ $gettext('Loading') }}
    <svg class="spinner" width="32" height="32" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle class="spin1" cx="4" cy="12" r="3"/><circle class="spin1 spin2" cx="12" cy="12" r="3"/><circle class="spin1 spin3" cx="20" cy="12" r="3"/></svg>
  </p>

  <p v-if="!loading && !items.length" class="notfound">
    {{ $gettext('No entries found') }}
  </p>

  <div v-if="!loading && !items.length && !this.embed && this.auth.can('page:add')" class="btn-group">
    <v-btn
      @click="add()"
      :title="$gettext('Add page')"
      icon="mdi-folder-plus"
      color="primary"
      variant="flat"
    />
  </div>
</template>

<style>
  .drag-placeholder {
    height: 48px;
  }

  .drag-placeholder-wrapper .tree-node-inner {
    background-color: rgb(var(--v-theme-background));
  }

  .tree-node-inner {
    border-bottom: 1px solid rgba(var(--v-border-color), 0.38);
    align-items: start;
    display: flex;
    padding: 4px 0;
    user-select: none;
  }

  .tree-node-inner .actions {
    display: flex;
    flex-wrap: wrap;
    max-width: 33%;
    flex-shrink: 0;
  }

  .tree-node-inner .spinner {
    transform: rotate(90deg);
    padding: 14px;
    height: 48px;
    width: 48px;
  }

  .item-content.cut {
    opacity: 0.5;
  }

  .item-domain {
    color: initial;
    display: block;
  }

  .item-domain,
  .item-to {
    white-space: nowrap;
    text-overflow: ellipsis;
    overflow: hidden;
  }

  .status-disabled .item-title {
    text-decoration: line-through;
  }

  @media (min-width: 600px) {
    .tree-node-inner {
      padding: 4px 0;
    }
  }
</style>
