/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import {
  mdiDotsVertical,
  mdiPublish,
  mdiEye,
  mdiEyeOff,
  mdiDelete,
  mdiDeleteRestore,
  mdiDeleteForever,
  mdiPlus,
  mdiMagnify,
  mdiRefresh,
  mdiMenuRight,
  mdiEyeOffOutline,
  mdiContentCut,
  mdiContentCopy,
  mdiContentPaste,
  mdiArrowUp,
  mdiArrowRight,
  mdiArrowDown,
  mdiClockOutline,
  mdiCached,
  mdiLock,
  mdiKeyVariant,
  mdiPencil,
  mdiSync,
  mdiTranslateOff
} from '@mdi/js'
import { Draggable } from '@he-tree/vue'
import { dragContext } from '@he-tree/vue'
import ActionItem from './ActionItem.vue'
import ActionMenu from './ActionMenu.vue'
import CmsDialog from './Dialog.vue'
import ListStatus from './ListStatus.vue'
import LoadingSpinner from './LoadingSpinner.vue'
import PageAccess from './PageAccess.vue'
import PageBulkDialog from './PageBulkDialog.vue'
import ListSort from './ListSort.vue'
import {
  useAppStore,
  useUserStore,
  useConfirmStore,
  useLanguageStore,
  useMessageStore,
  useChangeStore
} from '../stores'
import { listBase, mutation, tally, useListShortcuts } from '../lists'
import { command } from '../shortcuts'
import { debounce, safeParse, sanitize } from '../utils'
import { setupEcho, listEcho } from '../echo'
import { invalidateList, listFetchPolicy } from '../graphql'

const PAGE_TREE_FIELDS = new Set([
  'access',
  'cache',
  'created_at',
  'deleted_at',
  'domain',
  'editor',
  'has',
  'id',
  'lang',
  'latest_id',
  'name',
  'parent_id',
  'path',
  'publish_at',
  'published',
  'restricted',
  'source',
  'stale',
  'status',
  'tag',
  'theme',
  'title',
  'to',
  'type',
  'updated_at',
  'variant_deleted_at'
])
function patchData(data, item) {
  for (const key in item) {
    if (key in data || PAGE_TREE_FIELDS.has(key)) {
      data[key] = item[key]
    }
  }
}

const CLEAR_CACHE = gql`
  mutation ($ids: [ID!]!) {
    clearCache(ids: $ids)
  }
`

const INSERT_PAGE = gql`
  mutation ($input: PageInput!, $parent: ID, $ref: ID) {
    addPage(input: $input, parent: $parent, ref: $ref) {
      id
    }
  }
`

const MOVE_PAGE = gql`
  mutation ($id: ID!, $parent: ID, $ref: ID) {
    movePage(id: $id, parent: $parent, ref: $ref) {
      id
    }
  }
`

const SAVE_PAGES = gql`
  mutation ($id: [ID!]!, $input: PageInput!, $descendants: Boolean, $lang: String) {
    bulkPage(id: $id, input: $input, descendants: $descendants, lang: $lang) {
      ids
      latest
      data
      failed
    }
  }
`

const PAGE_FIELDS = `id
          access @include(if: $access)
          parent_id
          lang
          source
          stale
          created_at
          deleted_at
          variant_deleted_at
          editor
          has
          restricted
          latest {
            id
            published
            publish_at
            data
            editor
            created_at
          }`

const ADD_VARIANT = gql`
  mutation ($id: ID!, $lang: String!, $access: Boolean!) {
    addVariant(id: $id, lang: $lang) {
      ${PAGE_FIELDS}
    }
  }
`

const COPY_PAGE = gql`
  mutation ($id: ID!, $parent: ID, $ref: ID) {
    copyPage(id: $id, parent: $parent, ref: $ref) {
      id
    }
  }
`

const FETCH_PAGES = gql`
  query(
    $filter: PageFilter,
    $sort: [QueryPagesSortOrderByClause!],
    $limit: Int!,
    $page: Int!,
    $trashed: Trashed,
    $publish: Publish,
    $lang: String,
    $access: Boolean!
  ) {
    pages(
      filter: $filter,
      sort: $sort,
      first: $limit,
      page: $page,
      trashed: $trashed,
      publish: $publish,
      lang: $lang
    ) {
      data {
        ${PAGE_FIELDS}
      }
      paginatorInfo {
        currentPage
        lastPage
      }
    }
  }
`

const SORT_OPTIONS = Object.freeze([
  { column: 'LFT', order: 'ASC', label: 'Tree' },
  { column: 'ID', order: 'DESC', label: 'Latest' },
  { column: 'ID', order: 'ASC', label: 'Oldest' },
  { column: 'LATEST_ID', order: 'DESC', label: 'Latest edit' },
  { column: 'LATEST_ID', order: 'ASC', label: 'Oldest edit' },
  { column: 'NAME', order: 'ASC', label: 'Name' },
  { column: 'EDITOR', order: 'ASC', label: 'Editor' }
])

export default {
  components: {
    ActionItem,
    ActionMenu,
    CmsDialog,
    Draggable,
    ListSort,
    ListStatus,
    LoadingSpinner,
    PageAccess,
    PageBulkDialog
  },

  props: listBase.props,

  emits: listBase.emits,

  data() {
    // the list always shows the pages in a current language, the last one chosen or the default
    const lang = this.user.setting('page', 'lang', this.languages.default())

    if (!this.languages.available.includes(lang.value)) {
      lang.value = this.languages.default()
    }

    return {
      items: [],
      lang,
      accessDialog: false,
      accessDescendants: 0,
      accessIds: [],
      accessSelected: false,
      propsDialog: false,
      propsCount: 0,
      propsDescendants: 0,
      propsIds: [],
      propsSelected: false,
      loading: true,
      checked: null,
      clip: null,
      counts: tally([]),
      sort: this.user.setting('page', 'sort', { column: 'LFT', order: 'ASC' }),
      term: '',
      destroyed: false,
      loadId: 0,
      origin: null,
      outdated: false
    }
  },

  setup() {
    useListShortcuts('page', (vm) => vm.newPage())

    const languages = useLanguageStore()
    const messages = useMessageStore()
    const user = useUserStore()
    const app = useAppStore()
    const changes = useChangeStore()
    const confirm = useConfirmStore()

    return {
      type: 'page',
      app,
      user,
      changes,
      confirm,
      languages,
      messages,
      mdiDotsVertical,
      mdiPublish,
      mdiEye,
      mdiEyeOff,
      mdiDelete,
      mdiDeleteRestore,
      mdiDeleteForever,
      mdiPlus,
      mdiMagnify,
      mdiRefresh,
      mdiMenuRight,
      mdiEyeOffOutline,
      mdiContentCut,
      mdiContentCopy,
      mdiContentPaste,
      mdiClockOutline,
      mdiCached,
      mdiLock,
      mdiKeyVariant,
      mdiPencil,
      mdiSync,
      mdiTranslateOff,
      sortOptions: SORT_OPTIONS
    }
  },

  created() {
    this.reloadd = debounce(() => this.reload(false), 300)

    const initial = this.refresh()

    if (!this.embed) {
      // patch the matching node when a page changes elsewhere; subscribe for
      // the whole lifetime (not per activation) so the tree keeps patching in
      // the background while the editor is in a detail or another view and is
      // up to date when they return. The tab that made the change is excluded
      // server-side via toOthers(), so no editor filter is needed here
      this.unsubscribe = setupEcho('page', (event, name) => listEcho(this, event, name))

      // Reconcile once when a reconnect or structural event invalidates the tree while its
      // initial query is still in flight. Evict only the page lists so the follow-up cache-first
      // query reaches the server without discarding unrelated detail data.
      initial.finally(() => {
        if (this.outdated && !this.destroyed) {
          invalidateList('pages')
          return this.reload(false)
        }
      })
    }
  },

  mounted() {
    this.checked = false // required for isChecked() to work correctly
  },

  activated() {
    this.sync()

    if (!this.loading && listFetchPolicy() === 'network-only') {
      return this.refresh()
    }
  },

  beforeUnmount() {
    this.destroyed = true
    this.unsubscribe?.()
  },

  computed: {
    filtered: listBase.computed.filtered,

    langs() {
      return this.languages.available.map((code) => ({ value: code, title: this.languages.translate(code) }))
    },

    isChecked() {
      return this.checked || this.$refs.tree?.statsFlat.some((stat) => stat._checked)
    },

    // submenus of a page node, each applied before, into or after the page
    menus() {
      const list = []

      if (this.embed) {
        return list
      }

      if (this.clip?.type === 'copy' && this.user.can('page:add')) {
        list.push({ label: this.$gettext('Paste'), fn: this.paste })
      } else if (this.clip?.type === 'cut' && this.user.can('page:move')) {
        list.push({ label: this.$gettext('Paste'), fn: this.move })
      }

      if (this.user.can('page:add')) {
        list.push({ label: this.$gettext('Insert'), fn: this.insert })
      }

      return list
    },

    places() {
      return [
        { icon: mdiArrowUp, label: this.$gettext('Before'), idx: 0 },
        { icon: mdiArrowRight, label: this.$gettext('Into'), idx: null },
        { icon: mdiArrowDown, label: this.$gettext('After'), idx: 1 }
      ]
    }
  },

  methods: {
    accessApplied(access, descendants = false) {
      const stats = this.$refs.tree?.statsFlat || []
      const ids = new Set(this.accessIds)
      const selected = new Set(stats.filter((stat) => ids.has(stat.data?.id)))

      stats.forEach((stat) => {
        if (selected.has(stat) || (descendants && this.checkedAncestor(stat, selected))) {
          stat.data.access = Array.isArray(access) ? [...access] : null
          stat.data.restricted = access !== null
        }

        if (this.accessSelected) {
          stat._checked = false
        }
      })

      this.accessDialog = false
      this.accessIds = []
      if (this.accessSelected) {
        this.checked = false
      }
      this.accessSelected = false
    },

    allowed: listBase.methods.allowed,

    accessTitle(access) {
      if (!Array.isArray(access)) return this.$gettext('Restricted')

      return access.length
        ? this.$gettext('Access') + ': ' + access.join(', ')
        : this.$gettext('Authenticated users')
    },

    add() {
      if (this.embed || !this.allowed('add')) {
        return
      }

      this.$apollo
        .mutate({
          mutation: INSERT_PAGE,
          variables: { input: this.create(), parent: null, ref: null }
        })
        .then((result) => {
          if (!result.data.addPage) {
            throw new Error('No data in addPage mutation result')
          }

          const page = { ...result.data.addPage, lang: this.lang, source: this.lang }

          this.$refs.tree.add(page)
          this.invalidate()
          this.$emit('select', page)
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error adding root page'), error)
        })
    },

    position(stat) {
      const siblings = this.$refs.tree.getSiblings(stat)
      const next = siblings[siblings.indexOf(stat) + 1]

      return {
        id: stat.data.id,
        parent: stat.parent?.data.id || null,
        ref: next?.data.id || null
      }
    },

    moved(origin) {
      if (!origin) {
        return
      }

      this.messages.add(this.$gettext('Page moved'), 'success', null, {
        label: this.$gettext('Undo'),
        handler: () => {
          this.movePage(origin.id, origin.parent, origin.ref).then((success) => {
            if (success) {
              this.reload(false)
            }
          })
        }
      })
    },

    updateHas(stat, delta) {
      // optimistically adjust the immediate parent's descendant count (`has`); only feeds the
      // "apply recursively (N)" hint, so grandparents stay approximate until the next reload
      if (stat?.data) {
        stat.data.has = Math.max(0, (stat.data.has || 0) + delta)
      }
    },

    change() {
      if (!dragContext?.targetInfo) return

      const origin = this.origin
      this.origin = null

      const parent = dragContext.targetInfo.parent
      const siblings = dragContext.targetInfo.siblings
      const ref = siblings[dragContext.targetInfo.indexBeforeDrop + 1] || null

      this.movePage(
        dragContext.startInfo.dragNode.data.id,
        parent ? parent.data.id : null,
        ref ? ref.data.id : null
      ).then((success) => {
        if (!success) {
          return
        }

        const srcparent = dragContext.startInfo.parent
        const moved = (dragContext.startInfo.dragNode.data.has || 0) + 1

        this.updateHas(srcparent, -moved)
        this.updateHas(parent, moved)
        this.moved(origin)
      })
    },

    clear(stat = null) {
      if (!this.allowed('clear', 'cache')) {
        return
      }

      const list = stat ? [stat] : this.selected()
      const ids = list.map((stat) => stat.data.id)

      if (!list.length) {
        return
      }

      return this.$apollo
        .mutate({
          mutation: CLEAR_CACHE,
          variables: {
            ids: ids
          }
        })
        .then((result) => {
          const done = result.data?.clearCache || ids.length
          this.messages.add(
            done === 1
              ? this.$gettext('Cache cleared')
              : this.$ngettext(
                  'Cache cleared for %{num} page.',
                  'Cache cleared for %{num} pages.',
                  done,
                  { num: done }
                ),
            'success'
          )
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error clearing cache'), error, list)
        })
    },

    copy(stat, node) {
      this.clip = { type: 'copy', node: node, stat: stat }
    },

    create(attr = {}) {
      return Object.assign(
        {
          path: '_' + Math.floor(Math.random() * 10000),
          lang: this.lang,
          status: 0,
          cache: 5
        },
        attr
      )
    },

    count() {
      const items = this.selected().map((stat) => stat.data)
      const own = items.filter((item) => !this.missing(item))

      // variant actions only count the pages which have a variant in the current language
      this.counts = Object.assign(tally(items), {
        draft: own.filter((item) => !item.published).length,
        own: own.length,
        langLive: own.filter((item) => item.lang !== item.source && !item.variant_deleted_at).length,
        langSource: own.filter((item) => item.lang === item.source).length,
        langTrashed: own.filter((item) => item.variant_deleted_at).length
      })
    },

    // adds an untranslated copy of the source variant in the current language
    createLang(stat) {
      if (!this.allowed('add') || !this.missing(stat.data)) {
        return
      }

      return this.$apollo
        .mutate({
          mutation: ADD_VARIANT,
          variables: { id: stat.data.id, lang: this.lang, access: this.user.can('page:access') }
        })
        .then((result) => {
          if (!result.data.addVariant) {
            throw new Error('No data in addVariant mutation result')
          }

          Object.assign(stat.data, this.hydrate(result.data.addVariant))
          this.invalidate()
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error adding language'), error, stat.data.id)
        })
    },

    // moves the variants in the current language to the trash, source variants are skipped
    async dropLang(stat = null) {
      const all = this.allowed('drop') ? (stat ? [stat] : this.selected((page) => !this.missing(page))) : []
      const list = all.filter((item) => item.data.lang !== item.data.source && !item.data.variant_deleted_at)
      const skipped = all.filter((item) => item.data.lang === item.data.source).length

      if (!list.length) {
        if (skipped) {
          this.messages.add(this.$gettext('Pages in their source language are deleted as whole pages only'), 'info')
        }
        return
      }

      if (
        !stat &&
        !(await this.confirm.ask(
          this.$gettext('Delete language'),
          this.$ngettext(
            'Move %{num} page in "%{lang}" to the trash?',
            'Move %{num} pages in "%{lang}" to the trash?',
            list.length,
            { num: list.length, lang: this.languages.translate(this.lang) }
          ),
          [],
          skipped
            ? this.$ngettext(
                '%{num} page in its source language is skipped.',
                '%{num} pages in their source language are skipped.',
                skipped,
                { num: skipped }
              )
            : ''
        ))
      ) {
        return
      }

      const ids = list.map((item) => item.data.id)

      return this.mutate('drop', ids, this.$gettext('Error trashing language'), this.lang).then((ok) => {
        if (!ok) {
          return
        }

        const action = this.user.can('page:keep')
          ? {
              label: this.$gettext('Undo'),
              handler: () => {
                this.mutate('keep', ids, this.$gettext('Error restoring language'), this.lang).then((ok) => {
                  ok && this.reload(false)
                })
              }
            }
          : null

        this.messages.add(
          this.$ngettext('Moved to trash', '%{num} entries moved to trash', ids.length, { num: ids.length }),
          'success',
          null,
          action
        )

        return this.refetch(list)
      })
    },

    // restores the trashed variants in the current language
    keepLang(stat = null) {
      const list = this.allowed('keep') ? (stat ? [stat] : this.selected((page) => page.variant_deleted_at && !this.missing(page))) : []

      if (!list.length) {
        return
      }

      this.mutate('keep', list.map((item) => item.data.id), this.$gettext('Error restoring language'), this.lang).then((ok) => {
        ok && this.refetch(list)
      })
    },

    // permanently removes the variants in the current language, source variants are skipped
    async purgeLang(stat = null) {
      const list = (this.allowed('purge') ? (stat ? [stat] : this.selected((page) => !this.missing(page))) : [])
        .filter((item) => item.data.lang !== item.data.source)

      if (
        !list.length ||
        !(await this.confirm.purge(
          list.map((stat) => ({
            name: stat.data.name + ' (' + this.languages.translate(this.lang) + ')',
            info: '/' + (stat.data.path || '')
          }))
        ))
      ) {
        return
      }

      this.mutate('purge', list.map((item) => item.data.id), this.$gettext('Error purging language'), this.lang).then((ok) => {
        ok && this.refetch(list)
      })
    },

    cut(stat, node) {
      this.$refs.tree.statsFlat.forEach((stat) => {
        delete stat.cut
      })
      stat.cut = true

      this.clip = { type: 'cut', node: node, stat: stat }
    },

    drop(stat) {
      const list = this.allowed('drop') ? (stat ? [stat] : this.selected()) : []
      const ids = list.map((item) => item.data.id)

      if (!list.length) {
        return
      }

      this.mutate('drop', ids, this.$gettext('Error trashing page')).then((ok) => {
        if (!ok) {
          return
        }

        const date = new Date().toISOString().replace(/T/, ' ').substring(0, 19)

        for (const item of this.tops(list)) {
          this.update(item, (item) => {
            item.data.deleted_at = date
            item._checked = false
          })

          if (this.filter.trashed === 'WITHOUT') {
            this.$refs.tree.remove(item)
          }
        }

        this.trashed(ids)
      })
    },

    trashed(ids) {
      const action = this.user.can('page:keep')
        ? {
            label: this.$gettext('Undo'),
            handler: () => {
              this.mutate('keep', ids, this.$gettext('Error restoring page')).then((ok) => {
                ok && this.reload(false)
              })
            }
          }
        : null

      this.messages.add(
        this.$ngettext('Moved to trash', '%{num} entries moved to trash', ids.length, {
          num: ids.length
        }),
        'success',
        null,
        action
      )
    },

    checkedAncestor(stat, checked) {
      for (let parent = stat.parent; parent; parent = parent.parent) {
        if (checked.has(parent)) {
          return true
        }
      }
      return false
    },

    editAccess(stat = null) {
      const list = stat ? [stat] : this.selected()

      this.accessIds = list.map((stat) => stat.data.id)
      this.accessDescendants = list.length === 1 ? list[0].data.has || 0 : 0
      this.accessSelected = !stat
      this.accessDialog = this.accessIds.length > 0
    },

    editProps(stat = null) {
      const list = stat ? [stat] : this.selected((page) => !this.missing(page))
      const set = new Set(list)

      this.propsCount = list.length
      // pages a recursive apply reaches beyond the selection (0 for leaf-only selections); skip
      // checked-ancestor-covered pages so overlapping selections aren't counted twice
      const affected = list
        .filter((item) => !this.checkedAncestor(item, set))
        .reduce((sum, item) => sum + (item.data.has || 0) + 1, 0)
      this.propsDescendants = affected - list.length
      this.propsIds = list.map((item) => item.data.id)
      this.propsSelected = !stat
      this.propsDialog = this.propsCount > 0
    },

    expand() {
      const stat = this.$refs.tree.activeDescendant

      if (stat && stat.data.has && !stat.children.length) {
        this.load(stat, stat.data)
      }
    },

    fetch(parent = null, page = 1, limit = 100) {
      return this.pages({ parent_id: parent }, page, limit, undefined, this.$gettext('Error fetching pages'))
    },

    hydrate(entry) {
      const item = entry.latest?.data ? safeParse(entry.latest.data) : { ...entry }

      return Object.assign(item, {
        access: entry.access,
        id: entry.id,
        latest_id: entry.latest?.id || null,
        has: entry.has,
        restricted: entry.restricted,
        parent_id: entry.parent_id,
        deleted_at: entry.deleted_at,
        created_at: entry.created_at,
        updated_at: entry.latest?.created_at || entry.updated_at,
        editor: entry.latest?.editor || entry.editor,
        published: entry.latest?.published ?? true,
        publish_at: entry.latest?.publish_at || null,
        lang: entry.lang ?? item.lang ?? null,
        source: entry.source ?? null,
        stale: !!entry.stale,
        variant_deleted_at: entry.variant_deleted_at || null
      })
    },

    insert(stat, idx = null) {
      if (!this.allowed('add')) {
        return
      }

      const { parent, pos, ref } = this.target(stat, idx)
      const node = this.create(parent ? { theme: parent.data.theme, type: parent.data.type } : {})

      if (idx === null && !stat.open) {
        this.load(stat, stat.data)
      }

      return this.$apollo
        .mutate({
          mutation: INSERT_PAGE,
          variables: { input: node, parent: parent?.data.id ?? null, ref }
        })
        .then((result) => {
          node.id = result.data.addPage.id
          node.source = node.lang

          if (idx !== null || stat.open) {
            this.$refs.tree.add(node, parent, idx !== null ? pos + idx : 0)
          }

          this.updateHas(parent, 1)

          this.invalidate()
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error inserting page'), error)
        })
    },

    invalidate() {
      invalidateList('pages')
    },

    newPage() {
      const stat = this.$refs.tree?.activeDescendant

      // add a subpage to the focused page in the tree, otherwise a new root page
      if (stat && this.filter.view !== 'list' && this.$refs.tree.$el?.contains(document.activeElement)) {
        this.insert(stat)
      } else {
        this.add()
      }
    },

    keep(stat) {
      const list = this.allowed('keep') ? this.tops(stat ? [stat] : this.selected((page) => page.deleted_at)) : []

      if (!list.length) {
        return
      }

      this.mutate('keep', list.map((item) => item.data.id), this.$gettext('Error restoring page')).then((ok) => {
        for (const item of ok ? list : []) {
          const deleted_at = item.data.deleted_at || null

          this.update(item, (item) => {
            if (deleted_at >= item.data.deleted_at) {
              item.data.deleted_at = null
              item._checked = false
            }
          })
        }
      })
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
      const clip = this.clip
      const origin = this.position(clip.stat)
      const { parent, pos, ref } = this.target(stat, idx)

      return this.movePage(clip.node.id, parent?.data.id ?? null, ref).then((success) => {
        if (!success) {
          return false
        }

        const clipIdx = this.$refs.tree.getSiblings(stat).indexOf(clip.stat)
        const index = idx !== null ? (clipIdx >= 0 && clipIdx <= pos ? pos : pos + idx) : 0

        const oldparent = clip.stat.parent
        const moved = (clip.stat.data.has || 0) + 1

        this.$refs.tree.move(clip.stat, parent, index)
        delete clip.stat.cut

        if (this.clip === clip) {
          this.clip = null
        }

        this.updateHas(oldparent, -moved)
        this.updateHas(parent, moved)
        this.moved(origin)

        return true
      })
    },

    movePage(id, parentId, refId) {
      if (!this.allowed('move')) {
        return Promise.resolve(false)
      }

      return this.$apollo
        .mutate({
          mutation: MOVE_PAGE,
          variables: { id, parent: parentId, ref: refId }
        })
        .then((result) => {
          this.invalidate()
          return true
        })
        .catch((error) => {
          if (error) {
            this.messages.error(this.$gettext('Error moving page'), error)
          }

          return false
        })
    },

    // returns if the page has no variant in the current language and the source variant is shown
    missing(node) {
      return !!node?.lang && node.lang !== this.lang
    },

    // returns if an event patch belongs to the shown variant: patches without a language are
    // bulk edits of the source variants, rows without a known language accept all patches
    matches(data, item) {
      if (!data.lang) return true
      return item.lang ? data.lang === item.lang : !data.source || data.lang === data.source
    },

    // runs the mutation for the page IDs (of the variants in the language) and resolves true on success
    mutate(action, ids, msg, lang = null) {
      return this.$apollo
        .mutate({
          mutation: mutation(action, 'page', !!lang),
          variables: lang ? { id: ids, lang } : { id: ids }
        })
        .then(() => {
          this.invalidate()
          return true
        })
        .catch((error) => {
          this.messages.error(msg, error, ids)
          return false
        })
    },

    // queries the pages matching the list filters and the given filter values
    pages(values, page, limit, sort, msg, trashed = null) {
      if (!this.allowed('view')) {
        return Promise.resolve([])
      }

      const filter = {}

      for (const key in this.filter) {
        if (!['lang', 'publish', 'trashed', 'view'].includes(key) && this.filter[key] !== null) {
          filter[key] = this.filter[key]
        }
      }

      return this.$apollo
        .query({
          query: FETCH_PAGES,
          fetchPolicy: listFetchPolicy(),
          variables: {
            filter: Object.assign(filter, values),
            sort,
            page,
            limit,
            trashed: trashed || this.filter.trashed || 'WITHOUT',
            publish: this.filter.publish || null,
            lang: this.lang,
            access: this.user.can('page:access')
          }
        })
        .then((result) => this.transform(result.data.pages))
        .catch((error) => {
          this.messages.error(msg, error, filter, page, limit)
        })
    },

    paste(stat, idx = null) {
      if (!this.allowed('add')) {
        return
      }

      const { parent, pos, ref } = this.target(stat, idx)
      // collapsed nodes load their children incl. the copy when pasting into them
      const append = idx !== null || stat.open

      // the server copies the page, its sub-pages and all language variants
      return this.$apollo
        .mutate({
          mutation: COPY_PAGE,
          variables: { id: this.clip.node.id, parent: parent?.data.id ?? null, ref }
        })
        .then((result) => {
          const id = result.data.copyPage?.id

          if (!id) {
            throw new Error('No page data returned')
          }

          this.invalidate()
          return this.pages({ id: [id] }, 1, 1, undefined, this.$gettext('Error fetching page'), 'WITH')
        })
        .then((result) => {
          const item = result?.data?.[0]

          if (item && append) {
            this.$refs.tree.add(item, parent, idx !== null ? pos + idx : 0)
          } else if (!append) {
            this.load(stat, stat.data)
          }

          this.updateHas(parent, (item?.has || 0) + 1)
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error copying page'), error, stat, idx)
        })
    },

    pasteKey(stat) {
      if (this.clip?.type === 'copy' && !this.embed && this.user.can('page:add')) {
        this.paste(stat, 1)
      } else if (this.clip?.type === 'cut' && !this.embed && this.user.can('page:move')) {
        this.move(stat, 1)
      }
    },

    patch(item) {
      const stat = this.$refs.tree?.statsFlat.find((stat) => stat.data?.id === item.id)

      if (!stat || !this.matches(stat.data, item)) {
        return false
      }

      patchData(stat.data, item)

      return true
    },

    patchItems(items) {
      // index the patches by id so the bulk update is a single pass over the loaded rows
      const byId = new Map(items.map((item) => [item.id, item]))

      this.$refs.tree?.statsFlat.forEach((stat) => {
        const item = byId.get(stat.data?.id)

        if (item && this.matches(stat.data, item)) {
          patchData(stat.data, item)
        }
      })
    },

    publish(stat) {
      const list = this.allowed('publish')
        ? stat ? [stat] : this.selected((page) => !page.published && !this.missing(page))
        : []

      if (!list.length) {
        return
      }

      return this.mutate('pub', list.map((item) => item.data.id), this.$gettext('Error publishing page'), this.lang).then((ok) => {
        for (const item of ok ? list : []) {
          item.data.published = true
          item._checked = false
        }
      })
    },

    async purge(stat) {
      const list = this.allowed('purge') ? (stat ? [stat] : this.selected()) : []

      if (
        !list.length ||
        !(await this.confirm.purge(
          list.map((stat) => ({
            name: stat.data.name,
            info: '/' + (stat.data.path || '')
          })),
          list.some((stat) => stat.data.has)
            ? this.$gettext('All subpages of these pages will be purged as well.')
            : ''
        ))
      ) {
        return
      }

      const ids = list.map((item) => item.data.id).reverse()

      this.mutate('purge', ids, this.$gettext('Error purging page')).then((ok) => {
        for (const item of ok ? this.tops(list) : []) {
          const parent = item.parent
          const removed = (item.data.has || 0) + 1

          this.$refs.tree.remove(item)
          this.updateHas(parent, -removed)
        }
      })
    },

    // reloads the rows after their variant changed, e.g. the source variant is shown after
    // trashing the variant in the current language; rows not matching the filter anymore are removed
    refetch(list) {
      const ids = list.map((stat) => stat.data.id)

      return this.pages({ id: ids }, 1, ids.length, undefined, this.$gettext('Error fetching pages')).then((result) => {
        if (!result) {
          return
        }

        const byId = new Map(result.data.map((item) => [item.id, item]))

        for (const stat of list) {
          const item = byId.get(stat.data.id)

          if (item) {
            Object.assign(stat.data, item, { has: stat.data.has })
            stat._checked = false
          } else if (this.$refs.tree?.statsFlat.includes(stat)) {
            this.$refs.tree.remove(stat)
          }
        }
      })
    },

    refresh() {
      const id = ++this.loadId
      const open = new Set(
        this.filter.view === 'tree'
          ? (this.$refs.tree?.statsFlat || [])
              .filter((stat) => stat.open && stat.data?.id)
              .map((stat) => stat.data.id)
          : []
      )

      this.items = []
      this.loading = true

      const promise = this.filter.view === 'list' ? this.search() : this.tree(open)

      return promise
        .then(async (result) => {
          if (id === this.loadId) {
            this.items = result?.data || []

            await this.$nextTick()

            if (id === this.loadId) {
              this.$refs.tree?.statsFlat.forEach((stat) => {
                stat.open = open.has(stat.data?.id)
              })
            }
          }

          return result
        })
        .finally(() => {
          if (id === this.loadId) {
            this.loading = false
          }
        })
    },

    reload(cache = true) {
      this.outdated = false

      if (cache) {
        return this.$apollo.provider.defaultClient.clearStore().then(() => this.refresh())
      }

      return this.refresh()
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

    saveProps({ input, descendants }) {
      if (!this.allowed('save')) {
        return
      }

      const ids = this.propsIds

      if (!ids.length || !Object.keys(input).length) {
        return
      }

      return this.$apollo
        .mutate({
          mutation: SAVE_PAGES,
          variables: {
            id: ids,
            input: input,
            descendants: descendants,
            lang: this.lang
          }
        })
        .then((result) => {
          const res = result.data.bulkPage || {}
          const ids = new Set(res.ids || [])
          // data/latest are JSON scalar strings; sanitize drops prototype-pollution keys
          const data = sanitize(safeParse(res.data))
          const latest = safeParse(res.latest)

          this.$refs.tree?.statsFlat.forEach((stat) => {
            const id = stat.data?.id

            if (ids.has(id) && !this.missing(stat.data)) {
              for (const key in data) {
                if (key in stat.data) {
                  stat.data[key] = data[key]
                }
              }

              if (latest[id]) {
                stat.data.latest_id = latest[id]
              }
            }

            if (this.propsSelected) {
              stat._checked = false
            }
          })

          this.propsIds = []
          if (this.propsSelected) {
            this.checked = false
          }
          this.propsSelected = false
          this.invalidate()

          // best effort: the server reports how many attempted pages could not be saved
          if (res.failed > 0) {
            this.messages.add(
              this.$ngettext(
                '%{num} page could not be updated',
                '%{num} pages could not be updated',
                res.failed,
                { num: res.failed }
              ),
              'info'
            )
          }
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error saving page'), error, ids, input)
        })
    },

    search(page = 1, limit = 100) {
      return this.pages(
        this.term ? { any: this.term } : {},
        page,
        limit,
        this.sort ? [this.sort] : null,
        this.$gettext('Error searching pages')
      )
    },

    selected(fn = () => true) {
      return (this.$refs.tree?.statsFlat || []).filter((stat) => {
        return stat._checked && stat.data?.id && fn(stat.data)
      })
    },

    status(stat, val) {
      if (!this.allowed('save')) {
        return
      }

      const list = stat ? [stat] : this.selected((page) => !this.missing(page))

      if (!list.length) {
        return
      }

      return this.$apollo
        .mutate({
          mutation: SAVE_PAGES,
          variables: {
            id: list.map((stat) => stat.data.id),
            input: {
              status: val
            },
            lang: this.lang
          }
        })
        .then((result) => {
          const ids = new Set(result.data.bulkPage?.ids || [])

          list.forEach((stat) => {
            if (ids.has(stat.data.id)) {
              stat.data.status = val
            }
          })

          this.invalidate()
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error saving page'), error, list, val)
        })
    },

    sync: listBase.methods.sync,

    // returns the parent, the position and the reference page for adding before (0), into (null) or after (1) the page
    target(stat, idx) {
      const siblings = this.$refs.tree.getSiblings(stat)
      const pos = siblings.indexOf(stat)
      const next = idx === null ? stat.children?.[0] : idx ? siblings[pos + 1] : stat

      return { parent: idx === null ? stat : stat.parent, pos, ref: next?.data.id || null }
    },

    title(item) {
      const list = []

      if (item.publish_at) {
        list.push(this.$gettext('Scheduled for %{date}', { date: new Date(item.publish_at).toLocaleDateString() }))
      }

      if (item.theme) {
        list.push(this.$gettext('Theme') + ': ' + item.theme)
      }

      if (item.type) {
        list.push(this.$gettext('Page type') + ': ' + item.type)
      }

      if (item.tag) {
        list.push(this.$gettext('Page tag') + ': ' + item.tag)
      }

      if (item.cache) {
        list.push(this.$gettext('Cache time') + ': ' + this.$ngettext('%{num} minute', '%{num} minutes', item.cache, { num: item.cache }))
      }

      return list.join('\n')
    },

    toggle() {
      this.$refs.tree.statsFlat.forEach((stat) => {
        stat._checked = !stat._checked
      })
      this.$forceUpdate()
    },

    // returns the pages without those whose ancestors are in the list too
    tops(list) {
      const set = new Set(list)
      return list.filter((stat) => !this.checkedAncestor(stat, set))
    },

    tree(open, parent = null) {
      return this.fetch(parent).then(async (result) => {
        const data = result?.data || []

        await Promise.all(
          data.map(async (item) => {
            if (open.has(item.id)) {
              const children = await this.tree(open, item.id)
              item.children = children.data
            }
          })
        )

        return { ...result, data }
      })
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

    treeKey(ev) {
      const stat = this.$refs.tree.activeDescendant
      const actions = {
        select: () => (stat._checked = !stat._checked),
        // like the page menu, don't trash pages again or from embedded lists
        drop: () => !stat.data?.deleted_at && !this.embed && this.drop(stat),
        copy: () => this.copy(stat, stat.data),
        cut: () => this.cut(stat, stat.data),
        paste: () => this.pasteKey(stat)
      }
      const name = stat && command(ev, 'list', 'tree')

      if (actions[name]) {
        ev.preventDefault() // don't scroll the page or navigate back
        actions[name]()
      }
    },

    update(stat, fcn) {
      fcn(stat)
      stat.children?.forEach((child) => this.update(child, fcn))
    },

    url(node) {
      return this.app.urlpage
        .replace(/_domain_/, node.domain || '')
        .replace(/_path_/, node.path || '')
        .replace(/\/+$/, '')
    }
  },

  watch: {
    'changes.changed.page'() {
      this.sync()
    },

    filter: {
      deep: true,
      handler() {
        this.refresh()
      }
    },

    lang() {
      this.reload(false)
    },

    sort() {
      this.reload(false)
    },

    term() {
      this.reloadd()
    }
  }
}
</script>

<template>
  <div class="header">
    <div class="bulk">
      <v-checkbox-btn
        v-model="checked"
        @click.stop="toggle()"
        :aria-label="$gettext('Toggle selection')"
      />

      <span class="btn-actions">
        <ActionMenu>
          <template #activator="{ props, label }">
            <v-btn
              v-bind="props"
              @click="count()"
              :disabled="!isChecked || embed"
              :title="label"
              :icon="mdiDotsVertical"
              variant="text"
            />
          </template>
          <ActionItem v-if="counts.draft && user.can('page:publish')" :prepend-icon="mdiPublish" @click="publish()">
            {{ $gettext('Publish') }} ({{ counts.draft }})
          </ActionItem>
          <ActionItem v-if="counts.own && user.can('page:save')" :prepend-icon="mdiEye" @click="status(null, 1)">
            {{ $pgettext('page status', 'Enable') }} ({{ counts.own }})
          </ActionItem>
          <ActionItem v-if="counts.own && user.can('page:save')" :prepend-icon="mdiEyeOff" @click="status(null, 0)">
            {{ $pgettext('page status', 'Disable') }} ({{ counts.own }})
          </ActionItem>
          <v-divider></v-divider>
          <ActionItem v-if="counts.own && user.can('page:save')" :prepend-icon="mdiPencil" @click="editProps()">
            {{ $gettext('Edit properties') }} ({{ counts.own }})
          </ActionItem>
          <ActionItem v-if="isChecked && user.can('page:access')" :prepend-icon="mdiKeyVariant" @click="editAccess()">
            {{ $gettext('Access') }} ({{ counts.all }})
          </ActionItem>
          <ActionItem v-if="isChecked && user.can('cache:clear')" :prepend-icon="mdiCached" @click="clear()">
            {{ $gettext('Clear cache') }} ({{ counts.all }})
          </ActionItem>

          <v-divider></v-divider>

          <ActionItem v-if="counts.live && user.can('page:drop')" :prepend-icon="mdiDelete" @click="drop()">
            {{ $gettext('Delete') }} ({{ counts.live }})
          </ActionItem>
          <ActionItem v-if="counts.trashed && user.can('page:keep')" :prepend-icon="mdiDeleteRestore" @click="keep()">
            {{ $gettext('Restore') }} ({{ counts.trashed }})
          </ActionItem>
          <ActionItem v-if="isChecked && user.can('page:purge')" :prepend-icon="mdiDeleteForever" @click="purge()">
            {{ $gettext('Purge') }} ({{ counts.all }})
          </ActionItem>

          <template v-if="counts.langLive || counts.langTrashed">
            <v-divider></v-divider>

            <ActionItem
              v-if="counts.langLive && user.can('page:drop')"
              :prepend-icon="mdiTranslateOff"
              class="action-drop-lang"
              @click="dropLang()"
            >
              {{ $gettext('Delete this language') }} ({{ counts.langLive }})
            </ActionItem>
            <ActionItem
              v-if="counts.langTrashed && user.can('page:keep')"
              :prepend-icon="mdiDeleteRestore"
              class="action-keep-lang"
              @click="keepLang()"
            >
              {{ $gettext('Restore this language') }} ({{ counts.langTrashed }})
            </ActionItem>
            <ActionItem
              v-if="counts.langTrashed && user.can('page:purge')"
              :prepend-icon="mdiDeleteForever"
              class="action-purge-lang"
              @click="purgeLang()"
            >
              {{ $gettext('Purge this language') }} ({{ counts.langTrashed }})
            </ActionItem>
          </template>
        </ActionMenu>
      </span>

      <v-btn
        v-if="!this.embed && this.user.can('page:add')"
        @click="add()"
        :disabled="loading"
        :title="$gettext('Add page')"
        :icon="mdiPlus"
        class="btn-add"
        color="primary"
        variant="tonal"
      />
    </div>

    <div class="search">
      <v-select
        v-if="langs.length > 1"
        v-model="lang"
        :items="langs"
        :label="$gettext('Language')"
        class="lang-select"
        variant="underlined"
        hide-details
      />
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

    <v-btn
      @click="reload()"
      :loading="loading"
      :color="outdated ? 'warning' : ''"
      :title="$gettext('Reload page tree')"
      :variant="outdated ? 'tonal' : 'text'"
      class="btn-reload"
      size="small"
      rounded="xl"
    >
      {{ outdated ? $gettext('Refresh') + '&nbsp;' : '' }}
      <v-icon :icon="mdiRefresh" />
    </v-btn>

    <ListSort v-if="filter.view === 'list'" v-model="sort" :options="sortOptions" />
  </div>

  <Draggable
    ref="tree"
    v-model="items"
    @change="change()"
    @before-drag-start="origin = position($event)"
    @keydown.alt="reorder"
    @keydown.right.exact="expand"
    @keydown="treeKey"
    :defaultOpen="false"
    :disableDrag="$vuetify.display.smAndDown || !user.can('page:move')"
    :i18n="{
      instructions: $gettext('Use arrow keys to navigate. Alt plus arrow keys to reorder.'),
      movedToPosition: (position, total) =>
        $gettext('Moved to position %{position} of %{total}', { position, total }),
      outdentedToLevel: (level, position, total) =>
        $gettext('Outdented to level %{level}, position %{position} of %{total}', {
          level,
          position,
          total
        }),
      indentedToLevel: (level, position, total) =>
        $gettext('Indented to level %{level}, position %{position} of %{total}', {
          level,
          position,
          total
        })
    }"
    :rtl="$vuetify.locale.isRtl"
    :watermark="false"
    virtualization
  >
    <template #default="{ node, stat }">
      <div class="actions">
        <LoadingSpinner v-if="stat.loading" />
        <v-btn
          v-else
          @click="load(stat, node)"
          @keydown.enter.prevent="load(stat, node)"
          :icon="mdiMenuRight"
          :class="{ hidden: !node.has && !stat.children.length, open: stat.open }"
          class="btn-toggle"
          :title="$gettext('Toggle child nodes')"
          variant="text"
        />

        <v-checkbox-btn v-model="stat._checked" :class="{ draft: !node.published }" />

        <span class="btn-actions">
          <ActionMenu list-class="page-action-menu">
            <template #activator="{ props, label }">
              <v-btn v-bind="props" :title="label" :icon="mdiDotsVertical" variant="text" />
            </template>
            <ActionItem
              v-if="missing(node) && !node.deleted_at && user.can('page:add')"
              :prepend-icon="mdiPlus"
              class="action-create-lang"
              @click="createLang(stat)"
              >{{ $gettext('Create in %{lang}', { lang: languages.translate(lang) }) }}</ActionItem
            >

            <ActionItem
              v-if="!missing(node) && !node.deleted_at && !node.published && user.can('page:publish')"
              :prepend-icon="mdiPublish"
              @click="publish(stat)"
              >{{ $gettext('Publish') }}</ActionItem
            >

            <ActionItem
              v-if="!missing(node) && !node.deleted_at && user.can('page:save') && !node.status"
              :prepend-icon="mdiEye"
              @click="status(stat, 1)"
              >{{ $pgettext('page status', 'Enable') }}</ActionItem
            >
            <ActionItem
              v-if="!missing(node) && !node.deleted_at && user.can('page:save') && node.status"
              :prepend-icon="mdiEyeOff"
              @click="status(stat, 0)"
              >{{ $pgettext('page status', 'Disable') }}</ActionItem
            >

            <v-divider
              v-if="!node.deleted_at && (missing(node) ? user.can('page:add') : !node.published && user.can('page:publish'))"
            ></v-divider>

            <ActionItem v-if="!missing(node) && user.can('page:save')" :prepend-icon="mdiPencil" @click="editProps(stat)">
              {{ $gettext('Edit properties') }}
            </ActionItem>
            <ActionItem v-if="user.can('page:access')" :prepend-icon="mdiKeyVariant" @click="editAccess(stat)">
              {{ $gettext('Access') }}
            </ActionItem>
            <ActionItem v-if="user.can('cache:clear')" :prepend-icon="mdiCached" @click="clear(stat)">
              {{ $gettext('Clear cache') }}
            </ActionItem>

            <v-divider></v-divider>

            <ActionItem v-if="user.can('page:move')" :prepend-icon="mdiContentCut" @click="cut(stat, node)">
              {{ $pgettext('clipboard', 'Cut') }}
            </ActionItem>
            <ActionItem v-if="!embed && user.can('page:add')" :prepend-icon="mdiContentCopy" @click="copy(stat, node)">
              {{ $pgettext('clipboard', 'Copy') }}
            </ActionItem>

            <v-list-group v-for="(menu, i) in menus" :key="i">
              <template v-slot:activator="{ props }">
                <v-list-item v-bind="props" @click.stop>
                  <v-btn :prepend-icon="mdiContentPaste" variant="text">{{ menu.label }}</v-btn>
                </v-list-item>
              </template>
              <v-list-item v-for="place in places" :key="place.label">
                <v-btn :prepend-icon="place.icon" variant="text" @click="menu.fn(stat, place.idx)">{{
                  place.label
                }}</v-btn>
              </v-list-item>
            </v-list-group>

            <v-divider></v-divider>

            <ActionItem v-if="!node.deleted_at && user.can('page:drop')" :prepend-icon="mdiDelete" @click="drop(stat)">
              {{ $gettext('Delete') }}
            </ActionItem>
            <ActionItem
              v-if="node.deleted_at && user.can('page:keep')"
              :prepend-icon="mdiDeleteRestore"
              @click="keep(stat)"
              >{{ $gettext('Restore') }}</ActionItem
            >
            <ActionItem v-if="user.can('page:purge')" :prepend-icon="mdiDeleteForever" @click="purge(stat)">
              {{ $gettext('Purge') }}
            </ActionItem>

            <template v-if="!missing(node) && node.lang !== node.source">
              <v-divider></v-divider>

              <ActionItem
                v-if="!node.variant_deleted_at && user.can('page:drop')"
                :prepend-icon="mdiTranslateOff"
                class="action-drop-lang"
                @click="dropLang(stat)"
              >
                {{ $gettext('Delete this language') }}
              </ActionItem>
              <ActionItem
                v-if="node.variant_deleted_at && user.can('page:keep')"
                :prepend-icon="mdiDeleteRestore"
                class="action-keep-lang"
                @click="keepLang(stat)"
              >
                {{ $gettext('Restore this language') }}
              </ActionItem>
              <ActionItem
                v-if="user.can('page:purge')"
                :prepend-icon="mdiDeleteForever"
                class="action-purge-lang"
                @click="purgeLang(stat)"
              >
                {{ $gettext('Purge this language') }}
              </ActionItem>
            </template>
          </ActionMenu>
        </span>
      </div>
      <div
        class="item-content"
        :class="{
          'status-hidden': node.status == 2,
          'status-enabled': node.status == 1,
          'status-disabled': !node.status,
          trashed: node.deleted_at || node.variant_deleted_at,
          missing: missing(node),
          cut: stat.cut
        }"
        :title="title(node)"
      >
        <a href="#" class="item-text" @click.prevent="$emit('select', node)">
          <div class="item-head">
            <span class="item-lang" v-if="node.lang">{{ node.lang }}</span>
            <v-icon
              v-if="node.stale && node.lang !== node.source && !missing(node)"
              class="item-stale"
              :icon="mdiSync"
              :aria-label="$gettext('Needs update')"
            />
            <v-icon v-if="node.publish_at" class="publish-at" :icon="mdiClockOutline" />
            <v-icon
              v-if="node.restricted"
              class="item-access"
              :icon="mdiLock"
              :title="accessTitle(node.access)"
            />
            <v-icon v-if="node.status > 1" class="item-status" :icon="mdiEyeOffOutline" />
            <span class="item-title">{{ node.name || $gettext('New') }}</span>
          </div>
          <div v-if="node.title" class="item-subtitle">{{ node.title }}</div>
        </a>
        <div v-if="missing(node) && !node.deleted_at && !embed && user.can('page:add')" class="item-create">
          <v-btn
            @click="createLang(stat)"
            :prepend-icon="mdiPlus"
            class="btn-create-lang"
            variant="tonal"
            size="small"
          >
            {{ $gettext('Create') }}
          </v-btn>
        </div>
        <a
          class="item-aux"
          :href="url(node)"
          target="_blank"
          rel="noopener noreferrer"
          draggable="false"
        >
          <div class="item-domain">{{ node.domain }}</div>
          <span class="item-path item-subtitle">{{ '/' + (node.path || '') }}</span>
          <span v-if="node.to" class="item-to item-subtitle">
            ➔ {{ node.to.substring(0, 50) + (node.to.length > 50 ? '...' : '') }}</span
          >
        </a>
      </div>
    </template>
  </Draggable>

  <ListStatus
    :empty="!items?.length"
    :filtered="filtered"
    :loading="loading"
    :resettable="!!(term || defaults)"
    @reset="resetFilter()"
  />

  <div v-if="!this.embed && this.user.can('page:add')" class="btn-group">
    <v-btn
      @click="add()"
      :disabled="loading"
      :title="$gettext('Add page')"
      :icon="mdiPlus"
      class="btn-add"
      color="primary"
      variant="tonal"
    />
  </div>

  <PageBulkDialog
    v-model="propsDialog"
    :count="propsCount"
    :descendants="propsDescendants"
    @apply="saveProps"
  />

  <CmsDialog v-model="accessDialog" :title="$gettext('Access')" max-width="600">
    <p class="hint">
      {{
        $ngettext(
          'Apply access settings to %{num} page.',
          'Apply access settings to %{num} pages.',
          accessIds.length,
          { num: accessIds.length }
        )
      }}
    </p>
    <PageAccess
      v-if="accessDialog"
      :ids="accessIds"
      :descendants="accessDescendants"
      @applied="accessApplied"
    />
  </CmsDialog>
</template>

<style>
.drag-placeholder {
  height: 48px;
}

.drag-placeholder-wrapper .tree-node-inner {
  background-color: rgb(var(--v-theme-surface));
}

.tree-node-inner {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  align-items: start;
  display: flex;
  padding: 4px 0;
  user-select: none;
}

.tree-node:focus {
  outline: none;
}

.tree-node-inner {
  transition: background-color 0.15s ease;
}

.tree-node-inner:hover {
  background-color: rgba(var(--v-theme-primary), 0.06);
}

.tree-node:focus > .tree-node-inner,
.tree-node-inner:focus-within {
  background-color: rgb(var(--v-theme-surface-light));
}

.page-action-menu .v-list-group__header {
  color: inherit;
  font-size: inherit;
}

.tree-node-inner .actions {
  display: flex;
  flex-wrap: wrap;
  max-width: 48px;
  flex-shrink: 0;
  justify-content: end;
  margin-inline-end: 8px;
}

.tree-node-inner .btn-toggle .v-icon {
  transition: transform 0.2s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.tree-node-inner .btn-toggle.open .v-icon {
  transform: rotate(90deg);
}

.v-locale--is-rtl .tree-node-inner .btn-toggle.open .v-icon {
  transform: scaleX(-1) rotate(90deg);
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

.tree-node-inner .item-content.missing .item-text,
.tree-node-inner .item-content.missing .item-aux {
  opacity: 0.5;
}

.tree-node-inner .item-create {
  align-self: center;
  padding: 0 8px;
}

.tree-node-inner .item-text {
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  outline: none;
}

.tree-node-inner .item-text:not(:focus) {
  text-decoration: none;
}

.tree-node-inner .item-domain {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
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
  flex: auto;
}

@media (min-width: 360px) {
  .tree-node-inner .actions {
    max-width: 33%;
  }

  .tree-node-inner .item-content {
    flex-direction: row;
  }
}

@media (min-width: 600px) {
  .tree-node-inner {
    padding: 4px 0;
  }
}

/* screen reader texts rendered by he-tree */
.sr-only {
  position: absolute;
  clip-path: inset(50%);
  width: 1px;
  height: 1px;
  overflow: hidden;
  white-space: nowrap;
}
</style>
