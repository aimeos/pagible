/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import {
  mdiDotsVertical,
  mdiClose,
  mdiPublish,
  mdiEye,
  mdiEyeOff,
  mdiDelete,
  mdiDeleteRestore,
  mdiDeleteForever,
  mdiPlus,
  mdiMagnify,
  mdiRefresh,
  mdiMenuDown,
  mdiSort,
  mdiMenuRight,
  mdiEyeOffOutline,
  mdiContentCut,
  mdiContentCopy,
  mdiContentPaste,
  mdiArrowUp,
  mdiArrowRight,
  mdiArrowDown,
  mdiClockOutline
} from '@mdi/js'
import { Draggable } from '@he-tree/vue'
import { dragContext } from '@he-tree/vue'
import { useAppStore, useUserStore, useLanguageStore, useMessageStore } from '../stores'
import { debounce } from '../utils'

export default {
  components: {
    Draggable
  },

  props: {
    embed: { type: Boolean, default: false },
    filter: { type: Object, default: () => ({}) }
  },

  emits: ['select'],

  data() {
    return {
      menu: {},
      items: [],
      actions: false,
      loading: true,
      checked: null,
      clip: null,
      sort: this.user.getData('page', 'sort') || { column: 'LFT', order: 'ASC' },
      term: ''
    }
  },

  setup() {
    const languages = useLanguageStore()
    const messages = useMessageStore()
    const user = useUserStore()
    const app = useAppStore()

    return {
      app,
      user,
      languages,
      messages,
      mdiDotsVertical,
      mdiClose,
      mdiPublish,
      mdiEye,
      mdiEyeOff,
      mdiDelete,
      mdiDeleteRestore,
      mdiDeleteForever,
      mdiPlus,
      mdiMagnify,
      mdiRefresh,
      mdiMenuDown,
      mdiSort,
      mdiMenuRight,
      mdiEyeOffOutline,
      mdiContentCut,
      mdiContentCopy,
      mdiContentPaste,
      mdiArrowUp,
      mdiArrowRight,
      mdiArrowDown,
      mdiClockOutline,
      debounce
    }
  },

  created() {
    this.searchd = this.debounce(this.search, 500)

    this.fetch().then((result) => {
      this.items = result.data
      this.loading = false
    })
  },

  mounted() {
    this.checked = false // required for isChecked() to work correctly
  },

  computed: {
    canTrash() {
      return (
        this.isChecked &&
        this.$refs.tree?.statsFlat.some((stat) => stat._checked && !stat.data.deleted_at)
      )
    },

    isChecked() {
      return this.checked || this.$refs.tree?.statsFlat.some((stat) => stat._checked)
    },

    isTrashed() {
      return (
        this.isChecked &&
        this.$refs.tree?.statsFlat.some((stat) => stat._checked && stat.data.deleted_at)
      )
    }
  },

  methods: {
    add() {
      if (this.embed || !this.user.can('page:add')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      const item = this.create()

      this.$apollo
        .mutate({
          mutation: gql`
            mutation ($input: PageInput!) {
              addPage(input: $input) {
                id
              }
            }
          `,
          variables: {
            input: item
          }
        })
        .then((result) => {
          if (result.errors) {
            throw result.errors
          }

          if (!result.data.addPage) {
            throw new Error('No data in addPage mutation result')
          }

          const page = { ...result.data.addPage }

          this.$refs.tree.add(page)
          this.$emit('select', page)
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error adding root page') + ':\n' + error, 'error')
          this.$log(`PageList::add(): Error adding root page`, error)
        })
    },

    change() {
      if (!dragContext?.targetInfo) return

      const parent = dragContext.targetInfo.parent
      const siblings = dragContext.targetInfo.siblings
      const ref = siblings[dragContext.targetInfo.indexBeforeDrop + 1] || null

      this.movePage(
        dragContext.startInfo.dragNode.data.id,
        parent ? parent.data.id : null,
        ref ? ref.data.id : null
      ).then(() => {
        const srcparent = dragContext.startInfo.parent

        if (srcparent?.data && !srcparent?.children.length) {
          srcparent.data.has = false
        }

        if (parent) {
          parent.data.has = true
        }
      })
    },

    check() {
      const stat = this.$refs.tree.activeDescendant

      if (stat) {
        stat._checked = !stat._checked
      }
    },

    copy(stat, node) {
      this.clip = { type: 'copy', node: node, stat: stat }
    },

    copyKey() {
      const stat = this.$refs.tree.activeDescendant

      if (stat) {
        this.copy(stat, stat.data)
      }
    },

    create(attr = {}) {
      return Object.assign(
        {
          path: '_' + Math.floor(Math.random() * 10000),
          lang: this.$vuetify.locale.current || this.languages.default(),
          status: 0,
          cache: 5
        },
        attr
      )
    },

    cut(stat, node) {
      this.$refs.tree.statsFlat.forEach((stat) => {
        delete stat.cut
      })
      stat.cut = true

      this.clip = { type: 'cut', node: node, stat: stat }
    },

    cutKey() {
      const stat = this.$refs.tree.activeDescendant

      if (stat) {
        this.cut(stat, stat.data)
      }
    },

    deleteKey() {
      const stat = this.$refs.tree.activeDescendant

      if (stat) {
        this.drop(stat)
      }
    },

    drop(stat) {
      if (!this.user.can('page:drop')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      const list = stat
        ? [stat]
        : this.$refs.tree.statsFlat.filter((stat) => {
            return stat._checked && stat.data?.id
          })

      if (!list.length) {
        return
      }

      this.$apollo
        .mutate({
          mutation: gql`
            mutation ($id: [ID!]!) {
              dropPage(id: $id) {
                id
              }
            }
          `,
          variables: {
            id: list.map((item) => item.data.id)
          }
        })
        .then((result) => {
          if (result.errors) {
            throw result.errors
          }

          for (const item of list) {
            this.update(item, (item) => {
              item.data.deleted_at = new Date().toISOString().replace(/T/, ' ').substring(0, 19)
              item.check = false

              if (this.filter.trashed === 'WITHOUT') {
                this.$refs.tree.remove(item)
              }
            })
          }

          this.invalidate()
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error trashing page') + ':\n' + error, 'error')
          this.$log(`PageList::drop(): Error trashing page`, list, error)
        })
    },

    expand() {
      const stat = this.$refs.tree.activeDescendant

      if (stat && stat.data.has && !stat.children.length) {
        this.load(stat, stat.data)
      }
    },

    fetch(parent = null, page = 1, limit = 100) {
      if (!this.user.can('page:view')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return Promise.resolve([])
      }

      const publish = this.filter.publish || null
      const trashed = this.filter.trashed || 'WITHOUT'
      const filter = { ...this.filter }

      delete filter.trashed
      delete filter.publish
      delete filter.view

      filter.parent_id = parent

      return this.$apollo
        .query({
          query: gql`
            query(
              $filter: PageFilter,
              $limit: Int!,
              $page: Int!,
              $trashed: Trashed,
              $publish: Publish
            ) {
              pages(
                filter: $filter,
                first: $limit,
                page: $page,
                trashed: $trashed,
                publish: $publish
              ) {
                data {
                  ${this.fields()}
                }
                paginatorInfo {
                  currentPage
                  lastPage
                }
              }
            }
          `,
          variables: {
            filter: filter,
            page: page,
            limit: limit,
            trashed: trashed,
            publish: publish
          }
        })
        .then((result) => {
          if (result.errors) {
            throw result.errors
          }

          return this.transform(result.data.pages)
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error fetching pages') + ':\n' + error, 'error')
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

    hydrate(entry) {
      const item = entry.latest?.data ? JSON.parse(entry.latest.data) : { ...entry }

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
        latest: entry.latest
      })

      item.text = this.label(item)
      return item
    },

    insert(stat, idx = null) {
      if (!this.user.can('page:add')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      const siblings = this.$refs.tree.getSiblings(stat)
      const parent = idx !== null ? stat.parent : stat
      const pos = siblings.indexOf(stat)
      const node = this.create()
      let refid = null

      if (idx === null && !stat.open) {
        this.load(stat, stat.data)
      }

      switch (idx) {
        case 0:
          refid = stat.data.id
          break
        case null:
          refid = stat.children && stat.children[0] ? stat.children[0].data.id : null
          break
        case 1:
          refid = siblings[pos + 1] ? siblings[pos + 1].data.id : null
          break
      }

      this.$apollo
        .mutate({
          mutation: gql`
            mutation ($input: PageInput!, $parent: ID, $ref: ID) {
              addPage(input: $input, parent: $parent, ref: $ref) {
                id
              }
            }
          `,
          variables: {
            input: node,
            parent: parent ? parent.data.id : null,
            ref: refid
          }
        })
        .then((result) => {
          if (result.errors) {
            throw result.errors
          }

          node.id = result.data.addPage.id

          if (idx !== null || stat.open) {
            this.$refs.tree.add(node, parent, idx !== null ? pos + idx : 0)
          }

          if (parent) {
            parent.data.has = true
          }

          this.invalidate()
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error inserting page') + ':\n' + error, 'error')
          this.$log(`PageList::insert(): Error inserting page`, error)
        })
    },

    invalidate() {
      const cache = this.$apollo.provider.defaultClient.cache
      cache.evict({ id: 'ROOT_QUERY', fieldName: 'pages' })
      cache.evict({ id: 'ROOT_QUERY', fieldName: 'page' })

      Object.keys(cache.extract()).forEach(key => {
        if(key.startsWith('Page:')) {
          cache.evict({ id: key })
        }
      })

      cache.gc()
    },

    keep(stat) {
      if (!this.user.can('page:keep')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      const stats = stat
        ? [stat]
        : this.$refs.tree.statsFlat.filter((stat) => {
            return stat._checked && stat.data.id && stat.data.deleted_at
          })
      const list = stats.filter((stat) => {
        return stats.indexOf(stat.parent) === -1
      })
      const deleted_at = stat.data.deleted_at || null

      if (!list.length) {
        return
      }

      this.$apollo
        .mutate({
          mutation: gql`
            mutation ($id: [ID!]!) {
              keepPage(id: $id) {
                id
              }
            }
          `,
          variables: {
            id: list.map((item) => item.data.id)
          }
        })
        .then((result) => {
          if (result.errors) {
            throw result.errors
          }

          for (const item of list) {
            this.update(item, (item) => {
              if (deleted_at >= item.data.deleted_at) {
                item.data.deleted_at = null
                item.check = false
              }
            })
          }

          this.invalidate()
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error restoring page') + ':\n' + error, 'error')
          this.$log(`PageList::keep(): Error restoring page`, list, error)
        })
    },

    label(node) {
      const name = node.name || this.$gettext('New')
      const path = '/' + (node.path || '')

      if (node.domain) {
        return this.$gettext('%{name} (%{lang}, path: %{path}, domain: %{domain})', { name, lang: node.lang || '', path, domain: node.domain })
      }

      return this.$gettext('%{name} (%{lang}, path: %{path})', { name, lang: node.lang || '', path })
    },

    load(stat, node) {
      if (!stat.open && !node.children) {
        stat.loading = true

        this.fetch(node.id, stat.page ? stat.page + 1 : 1)
          .then((result) => {
            this.$refs.tree.addMulti(result.data, stat, 0)
            this.$refs.tree._focusNode(stat)
            stat.page = result.currentPage || 1
          })
          .finally(() => {
            stat.loading = false
          })
      }

      stat.open = !stat.open
    },

    move(stat, idx = null) {
      const siblings = this.$refs.tree.getSiblings(stat)
      const parent = idx !== null ? stat.parent : stat
      const pos = siblings.indexOf(stat)
      let refid = null

      switch (idx) {
        case 0:
          refid = stat.data.id
          break
        case null:
          refid = stat.children && stat.children[0] ? stat.children[0].data.id : null
          break
        case 1:
          refid = siblings[pos + 1] ? siblings[pos + 1].data.id : null
          break
      }

      this.movePage(
        this.clip.node.id,
        parent ? parent.data.id : null,
        refid
      ).then(() => {
        const clipIdx = this.$refs.tree.getSiblings(stat).indexOf(this.clip.stat)
        const index =
          idx !== null
            ? clipIdx >= 0 && clipIdx <= pos
              ? pos
              : pos + idx
            : 0

        this.$refs.tree.move(this.clip.stat, parent, index)

        if (parent) {
          if (!this.clip.stat.children?.length) {
            stat.parent.data.has = false
          }
          parent.data.has = true
        }

        this.invalidate()
      })
    },

    movePage(id, parentId, refId) {
      if (!this.user.can('page:move')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return Promise.reject()
      }

      return this.$apollo
        .mutate({
          mutation: gql`
            mutation ($id: ID!, $parent: ID, $ref: ID) {
              movePage(id: $id, parent: $parent, ref: $ref) {
                id
              }
            }
          `,
          variables: { id, parent: parentId, ref: refId }
        })
        .then((result) => {
          if (result.errors) {
            throw result.errors
          }
        })
        .catch((error) => {
          if (error) {
            this.messages.add(this.$gettext('Error moving page') + ':\n' + error, 'error')
            this.$log(`PageList::movePage(): Error moving page`, error)
          }
        })
    },

    paste(stat, idx = null) {
      if (!this.user.can('page:add')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      const siblings = this.$refs.tree.getSiblings(stat)
      const parent = idx !== null ? stat.parent : stat
      const pos = siblings.indexOf(stat)
      const node = { ...this.clip.node }
      let refid = null

      switch (idx) {
        case 0:
          refid = stat.data.id
          break
        case null:
          refid = stat.children && stat.children[0] ? stat.children[0].data.id : null
          break
        case 1:
          refid = siblings[pos + 1] ? siblings[pos + 1].data.id : null
          break
      }

      return this.$apollo
        .query({
          query: gql`
            query ($id: ID!) {
              page(id: $id) {
                id
                latest {
                  id
                  aux
                  files {
                    id
                  }
                  elements {
                    id
                  }
                }
              }
            }
          `,
          variables: {
            id: node.id
          }
        })
        .then((result) => {
          if (result.errors) {
            throw result.errors
          }

          const latest = result?.data?.page?.latest
          const aux = JSON.parse(latest?.aux || '{}')

          this.$apollo
            .mutate({
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
                  meta: JSON.stringify(aux?.meta || {}),
                  config: JSON.stringify(aux?.config || {}),
                  content: JSON.stringify(aux?.content || []),
                  path: node.path + '_' + Math.floor(Math.random() * 10000)
                },
                parent: parent ? parent.data.id : null,
                ref: refid,
                elements: latest?.elements.map((el) => el.id) || [],
                files: latest?.files.map((file) => file.id) || []
              }
            })
            .then((result) => {
              if (result.errors) {
                throw result.errors
              }

              if (!result.data.addPage) {
                throw new Error('No page data returned')
              }

              const index = idx !== null ? this.$refs.tree.getSiblings(stat).indexOf(stat) + idx : 0
              const item = this.hydrate(result.data.addPage)

              this.$refs.tree.add(item, parent, index)
              this.invalidate()
            })
            .catch((error) => {
              this.messages.add(this.$gettext('Error copying page') + ':\n' + error, 'error')
              this.$log(`PageList::paste(): Error copying page`, stat, idx, error)
            })
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error fetching page') + ':\n' + error, 'error')
          this.$log(`PageList::paste(): Error fetching page`, node.id, error)
        })
    },

    pasteKey() {
      const stat = this.$refs.tree.activeDescendant

      if (!stat || !this.clip) {
        return
      }

      if (this.clip.type === 'copy' && !this.embed && this.user.can('page:add')) {
        this.paste(stat, 1)
      } else if (this.clip.type === 'cut' && !this.embed && this.user.can('page:move')) {
        this.move(stat, 1)
      }
    },

    publish(stat) {
      if (!this.user.can('page:publish')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      const list = stat
        ? [stat]
        : this.$refs.tree.statsFlat.filter((stat) => {
            return stat._checked && stat.data.id && !stat.data.published
          })

      if (!list.length) {
        return
      }

      this.$apollo
        .mutate({
          mutation: gql`
            mutation ($id: [ID!]!) {
              pubPage(id: $id) {
                id
              }
            }
          `,
          variables: {
            id: list.map((item) => item.data.id)
          }
        })
        .then((result) => {
          if (result.errors) {
            throw result.errors
          }

          for (const item of list) {
            item.data.published = true
            item.check = false
          }

          this.invalidate()
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error publishing page') + ':\n' + error, 'error')
          this.$log(`PageList::publish(): Error publishing page`, list, error)
        })
    },

    purge(stat) {
      if (!this.user.can('page:purge')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      const list = stat
        ? [stat]
        : this.$refs.tree.statsFlat.filter((stat) => {
            return stat._checked && stat.data.id
          })

      if (!list.length) {
        return
      }

      this.$apollo
        .mutate({
          mutation: gql`
            mutation ($id: [ID!]!) {
              purgePage(id: $id) {
                id
              }
            }
          `,
          variables: {
            id: list.map((item) => item.data.id).reverse()
          }
        })
        .then((result) => {
          if (result.errors) {
            throw result.errors
          }

          for (const item of list) {
            this.$refs.tree.remove(item)

            if (item.parent && !item.parent.children?.length) {
              item.parent.data.has = false
            }
          }
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error purging page') + ':\n' + error, 'error')
          this.$log(`PageList::purge(): Error purging page`, list, error)
        })
    },

    reload(cache = true) {
      this.items = []
      this.loading = true

      if (cache) {
        this.invalidate()
      }

      const promise = this.filter.view === 'list' ? this.search() : this.fetch()

      promise.then((result) => {
        this.items = result?.data || []
        this.loading = false
      })
    },

    reorder(event) {
      if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) return

      this.$nextTick(() => {
        const stat = this.$refs.tree.activeDescendant
        if (!stat) return

        const siblings = this.$refs.tree.getSiblings(stat)
        const ref = siblings[siblings.indexOf(stat) + 1] || null

        this.movePage(
          stat.data.id,
          stat.parent ? stat.parent.data.id : null,
          ref ? ref.data.id : null
        )
      })
    },

    search(page = 1, limit = 100) {
      if (!this.user.can('page:view')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return Promise.resolve([])
      }

      const publish = this.filter.publish || null
      const trashed = this.filter.trashed || 'WITHOUT'
      const filter = { ...this.filter }

      delete filter.trashed
      delete filter.publish
      delete filter.view

      if (this.term) {
        filter.any = this.term
      }

      return this.$apollo
        .query({
          query: gql`
            query(
              $filter: PageFilter,
              $sort: [QueryPagesSortOrderByClause!],
              $limit: Int!,
              $page: Int!,
              $trashed: Trashed,
              $publish: Publish
            ) {
              pages(
                filter: $filter,
                sort: $sort,
                first: $limit,
                page: $page,
                trashed: $trashed,
                publish: $publish
              ) {
                data {
                  ${this.fields()}
                }
                paginatorInfo {
                  currentPage
                  lastPage
                }
              }
            }
          `,
          variables: {
            filter: filter,
            sort: this.sort ? [this.sort] : null,
            page: page,
            limit: limit,
            trashed: trashed,
            publish: publish
          }
        })
        .then((result) => {
          if (result.errors) {
            throw result.errors
          }

          return this.transform(result.data.pages)
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error searching pages') + ':\n' + error, 'error')
          this.$log(`PageList::search(): Error searching pages`, page, limit, error)
        })
    },

    status(stat, val) {
      if (!this.user.can('page:save')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      const list = stat
        ? [stat]
        : this.$refs.tree.statsFlat.filter((stat) => {
            return stat._checked && stat.data.id
          })

      list.forEach((stat) => {
        this.$apollo
          .mutate({
            mutation: gql`
              mutation ($id: ID!, $input: PageInput!) {
                savePage(id: $id, input: $input) {
                  id
                }
              }
            `,
            variables: {
              id: stat.data.id,
              input: {
                status: val
              }
            }
          })
          .then((result) => {
            if (result.errors) {
              throw result.errors
            }

            stat.data.status = val
          })
          .catch((error) => {
            this.messages.add(this.$gettext('Error saving page') + ':\n' + error, 'error')
            this.$log(`PageList::status(): Error saving page`, stat, val, error)
          })
      })
    },

    title(item) {
      const list = []

      if (item.publish_at) {
        list.push('Publish at: ' + new Date(item.publish_at).toLocaleDateString())
      }

      if (item.theme) {
        list.push('Theme: ' + item.theme)
      }

      if (item.type) {
        list.push('Page type: ' + item.type)
      }

      if (item.tag) {
        list.push('Tag: ' + item.tag)
      }

      if (item.cache) {
        list.push('Cache: ' + item.cache + ' min')
      }

      return list.join('\n')
    },

    toggle() {
      this.$refs.tree.statsFlat.forEach((stat) => {
        stat._checked = !stat._checked
      })
      this.$forceUpdate()
    },

    transform(result) {
      const pages = result.data.map((entry) => {
        return this.hydrate(entry)
      })

      return {
        data: pages,
        currentPage: result.paginatorInfo.currentPage,
        lastPage: result.paginatorInfo.lastPage
      }
    },

    update(stat, fcn) {
      if (typeof fcn !== 'function') {
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

        promise.then((result) => {
          this.items = result?.data || []
          this.loading = false
        })
      }
    },

    sort: {
      deep: true,
      handler() {
        this.user.saveData('page', 'sort', this.sort)
        this.reload(false)
      }
    },

    term() {
      this.reload(false)
    }
  }
}
</script>

<template>
  <div class="header">
    <div class="bulk">
      <v-checkbox-btn v-model="checked" @click.stop="toggle()" :aria-label="$gettext('Toggle selection')" />

      <component
        :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
        v-model="actions"
        transition="scale-transition"
        location="end center"
        max-width="300"
      >
        <template v-slot:activator="{ props }">
          <v-btn
            v-bind="props"
            :disabled="!isChecked || embed || !user.can('page:add')"
            :title="$gettext('Actions')"
            :icon="mdiDotsVertical"
            variant="text"
          />
        </template>
        <v-card>
          <v-toolbar density="compact">
            <v-toolbar-title>{{ $gettext('Actions') }}</v-toolbar-title>
            <v-btn :icon="mdiClose" :aria-label="$gettext('Close')" @click="actions = false" />
          </v-toolbar>

          <v-list @click="actions = false">
            <v-list-item v-if="isChecked && user.can('page:publish')">
              <v-btn :prepend-icon="mdiPublish" variant="text" @click="publish()">{{
                $gettext('Publish')
              }}</v-btn>
            </v-list-item>
            <v-list-item v-if="isChecked && user.can('page:save')">
              <v-btn :prepend-icon="mdiEye" variant="text" @click="status(null, 1)">{{
                $gettext('Enable')
              }}</v-btn>
            </v-list-item>
            <v-list-item v-if="isChecked && user.can('page:save')">
              <v-btn :prepend-icon="mdiEyeOff" variant="text" @click="status(null, 0)">{{
                $gettext('Disable')
              }}</v-btn>
            </v-list-item>

            <v-divider></v-divider>

            <v-list-item v-if="canTrash && user.can('page:drop')">
              <v-btn :prepend-icon="mdiDelete" variant="text" @click="drop()">{{
                $gettext('Delete')
              }}</v-btn>
            </v-list-item>
            <v-list-item v-if="isTrashed && user.can('page:keep')">
              <v-btn :prepend-icon="mdiDeleteRestore" variant="text" @click="keep()">{{
                $gettext('Restore')
              }}</v-btn>
            </v-list-item>
            <v-list-item v-if="isChecked && user.can('page:purge')">
              <v-btn :prepend-icon="mdiDeleteForever" variant="text" @click="purge()">{{
                $gettext('Purge')
              }}</v-btn>
            </v-list-item>
          </v-list>
        </v-card>
      </component>

      <v-btn
        v-if="!this.embed && this.user.can('page:add')"
        @click="add()"
        :disabled="loading"
        :title="$gettext('Add page')"
        :icon="mdiPlus"
        color="primary"
        variant="tonal"
      />
    </div>

    <div class="search">
      <v-text-field
        v-model="term"
        :label="$gettext('Search for')"
        :prepend-inner-icon="mdiMagnify"
        variant="underlined"
        hide-details
        clearable
      ></v-text-field>
    </div>

    <v-btn
      @click="reload()"
      :title="$gettext('Reload page tree')"
      :icon="mdiRefresh"
      variant="text"
      class="no-rtl"
    />

    <v-menu v-if="filter.view === 'list'">
      <template #activator="{ props }">
        <v-btn
          v-bind="props"
          :title="$gettext('Sort by')"
          :aria-label="$gettext('Sort by')"
          :append-icon="mdiMenuDown"
          :prepend-icon="mdiSort"
          variant="text"
        >
          {{
            sort?.column === 'ID'
              ? sort?.order === 'DESC'
                ? $gettext('latest')
                : $gettext('oldest')
              : $gettext('tree')
          }}
        </v-btn>
      </template>
      <v-list>
        <v-list-item>
          <v-btn variant="text" @click="sort = { column: 'LFT', order: 'ASC' }">{{
            $gettext('tree')
          }}</v-btn>
        </v-list-item>
        <v-list-item>
          <v-btn variant="text" @click="sort = { column: 'ID', order: 'DESC' }">{{
            $gettext('latest')
          }}</v-btn>
        </v-list-item>
        <v-list-item>
          <v-btn variant="text" @click="sort = { column: 'ID', order: 'ASC' }">{{
            $gettext('oldest')
          }}</v-btn>
        </v-list-item>
        <v-list-item>
          <v-btn variant="text" @click="sort = { column: 'NAME', order: 'ASC' }">{{
            $gettext('name')
          }}</v-btn>
        </v-list-item>
        <v-list-item>
          <v-btn variant="text" @click="sort = { column: 'TITLE', order: 'ASC' }">{{
            $gettext('title')
          }}</v-btn>
        </v-list-item>
        <v-list-item>
          <v-btn variant="text" @click="sort = { column: 'EDITOR', order: 'ASC' }">{{
            $gettext('editor')
          }}</v-btn>
        </v-list-item>
      </v-list>
    </v-menu>
  </div>

  <Draggable
    ref="tree"
    v-model="items"
    @change="change()"
    @keydown.alt="reorder"
    @keydown.right.exact="expand"
    @keydown.space.exact.prevent="check"
    @keydown.ctrl.c.exact.prevent="copyKey"
    @keydown.ctrl.x.exact.prevent="cutKey"
    @keydown.ctrl.v.exact.prevent="pasteKey"
    @keydown.delete.exact="deleteKey"
    :defaultOpen="false"
    :disableDrag="$vuetify.display.smAndDown || !user.can('page:move')"
    :i18n="{
      instructions: $gettext('Use arrow keys to navigate. Alt plus arrow keys to reorder.'),
      movedToPosition: (position, total) => $gettext('Moved to position %{position} of %{total}', {position, total}),
      outdentedToLevel: (level, position, total) => $gettext('Outdented to level %{level}, position %{position} of %{total}', {level, position, total}),
      indentedToLevel: (level, position, total) => $gettext('Indented to level %{level}, position %{position} of %{total}', {level, position, total}),
    }"
    :rtl="$vuetify.locale.isRtl"
    :watermark="false"
    virtualization
  >
    <template #default="{ node, stat }">
      <div class="actions">
        <svg
          v-if="stat.loading"
          class="spinner"
          fill="currentColor"
          viewBox="0 0 24 24"
          xmlns="http://www.w3.org/2000/svg"
        >
          <circle class="spin1" cx="4" cy="12" r="3" />
          <circle class="spin1 spin2" cx="12" cy="12" r="3" />
          <circle class="spin1 spin3" cx="20" cy="12" r="3" />
        </svg>
        <v-btn
          v-else
          @click="load(stat, node)"
          @keydown.enter.prevent="load(stat, node)"
          :class="{ hidden: !node.has && !stat.children.length }"
          :icon="stat.open ? mdiMenuDown : mdiMenuRight"
          :title="$gettext('Toggle child nodes')"
          variant="text"
        />

        <v-checkbox-btn v-model="stat._checked" :class="{ draft: !node.published }" />

        <component
          :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
          v-model="menu[node.id]"
          transition="scale-transition"
          location="end center"
          max-width="300"
        >
          <template #activator="{ props }">
            <v-btn
              v-bind="props"
              :title="$gettext('Actions')"
              :icon="mdiDotsVertical"
              variant="text"
            />
          </template>

          <v-card>
            <v-toolbar density="compact">
              <v-toolbar-title>{{ $gettext('Actions') }}</v-toolbar-title>
              <v-btn
                :icon="mdiClose"
                :aria-label="$gettext('Close')"
                @click="menu[node.id] = false"
              />
            </v-toolbar>

            <v-list @click="menu[node.id] = false">
              <v-list-item v-if="!node.deleted_at && !node.published && user.can('page:publish')">
                <v-btn :prepend-icon="mdiPublish" variant="text" @click="publish(stat)">{{
                  $gettext('Publish')
                }}</v-btn>
              </v-list-item>
              <v-list-item v-if="node.status !== 0 && user.can('page:save')">
                <v-btn :prepend-icon="mdiEyeOff" variant="text" @click="status(stat, 0)">{{
                  $gettext('Disable')
                }}</v-btn>
              </v-list-item>
              <v-list-item v-if="node.status !== 1 && user.can('page:save')">
                <v-btn :prepend-icon="mdiEye" variant="text" @click="status(stat, 1)">{{
                  $gettext('Enable')
                }}</v-btn>
              </v-list-item>
              <v-list-item v-if="node.status !== 2 && user.can('page:save')">
                <v-btn :prepend-icon="mdiEyeOffOutline" variant="text" @click="status(stat, 2)">{{
                  $gettext('Hide')
                }}</v-btn>
              </v-list-item>

              <v-divider></v-divider>

              <v-list-item v-if="user.can('page:move')">
                <v-btn :prepend-icon="mdiContentCut" variant="text" @click="cut(stat, node)">{{
                  $gettext('Cut')
                }}</v-btn>
              </v-list-item>
              <v-list-item v-if="!embed && user.can('page:add')">
                <v-btn :prepend-icon="mdiContentCopy" variant="text" @click="copy(stat, node)">{{
                  $gettext('Copy')
                }}</v-btn>
              </v-list-item>

              <v-list-group v-if="clip?.type == 'copy' && !this.embed && user.can('page:add')">
                <template v-slot:activator="{ props }">
                  <v-list-item v-bind="props" @click.stop>
                    <v-btn :prepend-icon="mdiContentPaste" variant="text">{{
                      $gettext('Paste')
                    }}</v-btn>
                  </v-list-item>
                </template>
                <v-list-item>
                  <v-btn :prepend-icon="mdiArrowUp" variant="text" @click="paste(stat, 0)">{{
                    $gettext('Before')
                  }}</v-btn>
                </v-list-item>
                <v-list-item>
                  <v-btn :prepend-icon="mdiArrowRight" variant="text" @click="paste(stat)">{{
                    $gettext('Into')
                  }}</v-btn>
                </v-list-item>
                <v-list-item>
                  <v-btn :prepend-icon="mdiArrowDown" variant="text" @click="paste(stat, 1)">{{
                    $gettext('After')
                  }}</v-btn>
                </v-list-item>
              </v-list-group>

              <v-list-group v-if="clip?.type == 'cut' && !this.embed && user.can('page:move')">
                <template v-slot:activator="{ props }">
                  <v-list-item v-bind="props" @click.stop>
                    <v-btn :prepend-icon="mdiContentPaste" variant="text">{{
                      $gettext('Paste')
                    }}</v-btn>
                  </v-list-item>
                </template>
                <v-list-item>
                  <v-btn :prepend-icon="mdiArrowUp" variant="text" @click="move(stat, 0)">{{
                    $gettext('Before')
                  }}</v-btn>
                </v-list-item>
                <v-list-item>
                  <v-btn :prepend-icon="mdiArrowRight" variant="text" @click="move(stat)">{{
                    $gettext('Into')
                  }}</v-btn>
                </v-list-item>
                <v-list-item>
                  <v-btn :prepend-icon="mdiArrowDown" variant="text" @click="move(stat, 1)">{{
                    $gettext('After')
                  }}</v-btn>
                </v-list-item>
              </v-list-group>

              <v-list-group v-if="!this.embed && user.can('page:add')">
                <template v-slot:activator="{ props }">
                  <v-list-item v-bind="props" @click.stop>
                    <v-btn :prepend-icon="mdiContentPaste" variant="text">{{
                      $gettext('Insert')
                    }}</v-btn>
                  </v-list-item>
                </template>
                <v-list-item>
                  <v-btn :prepend-icon="mdiArrowUp" variant="text" @click="insert(stat, 0)">{{
                    $gettext('Before')
                  }}</v-btn>
                </v-list-item>
                <v-list-item>
                  <v-btn :prepend-icon="mdiArrowRight" variant="text" @click="insert(stat)">{{
                    $gettext('Into')
                  }}</v-btn>
                </v-list-item>
                <v-list-item>
                  <v-btn :prepend-icon="mdiArrowDown" variant="text" @click="insert(stat, 1)">{{
                    $gettext('After')
                  }}</v-btn>
                </v-list-item>
              </v-list-group>

              <v-divider></v-divider>

              <v-list-item v-if="!node.deleted_at && user.can('page:drop')">
                <v-btn :prepend-icon="mdiDelete" variant="text" @click="drop(stat)">{{
                  $gettext('Delete')
                }}</v-btn>
              </v-list-item>
              <v-list-item v-if="node.deleted_at && user.can('page:keep')">
                <v-btn :prepend-icon="mdiDeleteRestore" variant="text" @click="keep(stat)">{{
                  $gettext('Restore')
                }}</v-btn>
              </v-list-item>
              <v-list-item v-if="user.can('page:purge')">
                <v-btn :prepend-icon="mdiDeleteForever" variant="text" @click="purge(stat)">{{
                  $gettext('Purge')
                }}</v-btn>
              </v-list-item>
            </v-list>
          </v-card>
        </component>
      </div>
      <div
        class="item-content"
        :class="{
          'status-hidden': node.status == 2,
          'status-enabled': node.status == 1,
          'status-disabled': !node.status,
          trashed: node.deleted_at,
          cut: stat.cut
        }"
        :title="title(node)"
      >
        <a href="#" class="item-text" @click.prevent="$emit('select', node)">
          <div class="item-head">
            <span class="item-lang" v-if="node.lang">{{ node.lang }}</span>
            <v-icon v-if="node.publish_at" class="publish-at" :icon="mdiClockOutline" />
            <v-icon v-if="node.status > 1" class="item-status" :icon="mdiEyeOffOutline" />
            <span class="item-title">{{ node.name || $gettext('New') }}</span>
          </div>
          <div v-if="node.title" class="item-subtitle">{{ node.title }}</div>
        </a>
        <a class="item-aux" :href="url(node)" target="_blank" draggable="false">
          <div class="item-domain">{{ node.domain }}</div>
          <span class="item-path item-subtitle">{{ '/' + node.path }}</span>
          <span v-if="node.to" class="item-to item-subtitle"> ➔ {{ node.to }}</span>
        </a>
      </div>
    </template>
  </Draggable>

  <p v-if="loading" class="loading">
    {{ $gettext('Loading') }}
    <svg
      class="spinner"
      width="32"
      height="32"
      viewBox="0 0 24 24"
      fill="currentColor"
      xmlns="http://www.w3.org/2000/svg"
    >
      <circle class="spin1" cx="4" cy="12" r="3" />
      <circle class="spin1 spin2" cx="12" cy="12" r="3" />
      <circle class="spin1 spin3" cx="20" cy="12" r="3" />
    </svg>
  </p>

  <p v-if="!loading && !items.length" class="notfound">
    {{ $gettext('No entries found') }}
  </p>

  <div v-if="!this.embed && this.user.can('page:add')" class="btn-group">
    <v-btn
      @click="add()"
      :disabled="loading"
      :title="$gettext('Add page')"
      :icon="mdiPlus"
      color="primary"
      variant="tonal"
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

.tree-node:focus {
  outline: none;
}

.tree-node:focus > .tree-node-inner,
.tree-node-inner:focus-within {
  background-color: rgb(var(--v-theme-surface-light));
}

.tree-node-inner .actions {
  display: flex;
  flex-wrap: wrap;
  max-width: 48px;
  flex-shrink: 0;
  justify-content: end;
  margin-inline-end: 8px;
}

.tree-node-inner .spinner {
  transform: rotate(90deg);
  padding: 14px;
  height: 48px;
  width: 48px;
}

.tree-node-inner .item-content {
  flex-wrap: wrap;
  flex-direction: column;
  justify-content: start;
}

.tree-node-inner .item-content.cut {
  opacity: 0.7;
}

.tree-node-inner .item-text {
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  outline: none;
}

.tree-node-inner .item-text:not(:focus) {
  text-decoration: none;
}

.tree-node-inner .item-domain {
  color: initial;
  display: block;
}

.tree-node-inner .item-domain,
.tree-node-inner .item-to {
  text-overflow: ellipsis;
  overflow: hidden;
}

.tree-node-inner .status-disabled .item-title {
  text-decoration: line-through;
}

.tree-node-inner .item-aux {
  min-height: 3rem;
  min-width: 3rem;
  text-align: end;
  width: 100%;
}

@media (min-width: 360px) {
  .tree-node-inner .actions {
    max-width: 33%;
  }

  .tree-node-inner .item-content {
    flex-direction: row;
  }

  .tree-node-inner .item-aux {
    width: unset;
  }
}

@media (min-width: 600px) {
  .tree-node-inner {
    padding: 4px 0;
  }
}
</style>
