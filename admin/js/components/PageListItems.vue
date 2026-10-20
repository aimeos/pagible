/** @license MIT, https://opensource.org/license/mit */

<script>
import {
  mdiDotsVertical,
  mdiPublish,
  mdiEye,
  mdiEyeOff,
  mdiDelete,
  mdiDeleteRestore,
  mdiDeleteForever,
  mdiPlus,
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
  mdiCheckAll,
  mdiSync,
  mdiTranslate,
  mdiTranslateOff
} from '@mdi/js'
import { Draggable } from '@he-tree/vue'
import ActionItem from './ActionItem.vue'
import ActionMenu from './ActionMenu.vue'
import CmsDialog from './Dialog.vue'
import ListReload from './ListReload.vue'
import ListSearch from './ListSearch.vue'
import ListStatus from './ListStatus.vue'
import LoadingSpinner from './LoadingSpinner.vue'
import PageAccess from './PageAccess.vue'
import PageBulkDialog from './PageBulkDialog.vue'
import PageBulkMenu from './PageBulkMenu.vue'
import PageLangSelect from './PageLangSelect.vue'
import TranslateDialog from './TranslateDialog.vue'
import ListSort from './ListSort.vue'
import {
  useSettingStore,
  useUserStore,
  useConfirmStore,
  useLanguageStore,
  useMessageStore,
  useChangeStore
} from '../stores'
import { listBase, useListShortcuts } from '../lists'
import { pageActions } from '../pageactions'
import { ADD_PAGE_VARIANT, FETCH_PAGES, INSERT_PAGE, hydrate, patchData } from '../pages'
import { pageTree } from '../pagetree'
import { pageVariants, translateNew } from '../variants'
import { debounce, formatDate, INPUT_DELAY, uid } from '../utils'
import { pageUrl } from '../urls'
import { setupEcho } from '../echo'
import { listEcho } from '../livesync'
import { invalidateList, listFetchPolicy } from '../graphql'

const SORT_OPTIONS = Object.freeze([
  { column: 'LFT', order: 'ASC', label: 'Tree' },
  { column: 'ID', order: 'DESC', label: 'Latest' },
  { column: 'ID', order: 'ASC', label: 'Oldest' },
  { column: 'LATEST_ID', order: 'DESC', label: 'Latest edit' },
  { column: 'LATEST_ID', order: 'ASC', label: 'Oldest edit' },
  { column: 'NAME', order: 'ASC', label: 'Name' },
  { column: 'EDITOR', order: 'ASC', label: 'Editor' }
])

const CACHE_MINUTES = 5 // default cache lifetime of new pages
const MAX_TO_LENGTH = 50 // characters of redirect targets shown in the list

export default {
  components: {
    ActionItem,
    ActionMenu,
    CmsDialog,
    Draggable,
    ListSort,
    ListReload,
    ListSearch,
    ListStatus,
    LoadingSpinner,
    PageAccess,
    PageBulkDialog,
    PageBulkMenu,
    PageLangSelect,
    TranslateDialog
  },

  mixins: [pageTree, pageActions, pageVariants],

  props: listBase.props,

  emits: listBase.emits,

  data() {
    // the list always shows the pages in a current language, the last one chosen or the default
    const lang = this.settings.setting('page', 'lang', this.languages.default())

    if (!this.languages.available.includes(lang.value)) {
      lang.value = this.languages.default()
    }

    return {
      items: [],
      lang,
      loading: true,
      treeRef: null,
      sort: this.settings.setting('page', 'sort', { column: 'LFT', order: 'ASC' }),
      term: '',
      destroyed: false,
      loadId: 0,
      outdated: false
    }
  },

  setup() {
    useListShortcuts('page', (vm) => vm.newPage())

    const languages = useLanguageStore()
    const messages = useMessageStore()
    const settings = useSettingStore()
    const user = useUserStore()
    const changes = useChangeStore()
    const confirm = useConfirmStore()

    return {
      type: 'page',
      MAX_TO_LENGTH,
      settings,
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
      mdiCheckAll,
      mdiSync,
      mdiTranslate,
      mdiTranslateOff,
      sortOptions: SORT_OPTIONS
    }
  },

  created() {
    this.debouncedReload = debounce(() => this.reload(false), INPUT_DELAY)

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
    // $refs aren't reactive, the tree must be available as reactive property for isChecked
    this.treeRef = this.$refs.tree
  },

  activated() {
    this.sync()

    if (!this.loading && listFetchPolicy() === 'network-only') {
      return this.refresh()
    }
  },

  beforeUnmount() {
    this.destroyed = true
    this.debouncedReload.cancel()
    this.unsubscribe?.()
  },

  computed: {
    filtered: listBase.computed.filtered,

    allChecked() {
      const stats = this.treeRef?.statsFlat || []
      return stats.length > 0 && stats.every((stat) => stat._checked)
    },

    isChecked() {
      return !!this.treeRef?.statsFlat.some((stat) => stat._checked)
    }
  },

  methods: {
    allowed: listBase.methods.allowed,
    targets: listBase.methods.targets,

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
          this.invalidate(true)
          this.$emit('select', page)
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error adding root page'), error)
        })
    },

    create(attr = {}) {
      return Object.assign(
        {
          path: '_' + uid(),
          lang: this.lang,
          status: 0,
          cache: CACHE_MINUTES
        },
        attr
      )
    },

    // adds an untranslated copy of the source variant in the current language, which is
    // translated in the background if the user is allowed to translate pages
    createLang(stat) {
      if (!this.allowed('add') || !this.missing(stat.data)) {
        return
      }

      return this.$apollo
        .mutate({
          mutation: ADD_PAGE_VARIANT,
          variables: { id: stat.data.id, lang: this.lang, access: this.user.can('page:access') }
        })
        .then((result) => {
          if (!result.data.addPageVariant) {
            throw new Error('No data in addPageVariant mutation result')
          }

          Object.assign(stat.data, hydrate(result.data.addPageVariant))
          this.invalidate(true)

          // the untranslated copy stays if queueing its translation fails
          return translateNew(this.$apollo, this.user, [stat.data.id], this.lang).catch((error) => {
            this.messages.error(this.$gettext('Error translating page'), error, stat.data.id)
          })
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error adding language'), error, stat.data.id)
        })
    },

    fetch(parent = null, page = 1, limit = 100) {
      return this.pages({ parent_id: parent }, page, limit, undefined, this.$gettext('Error fetching pages'))
    },

    // clears the cached page lists, only actions altering translation states refresh their counts
    invalidate(changed = false) {
      invalidateList('pages')
      changed && this.$emit('changed')
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

    // returns if the user can ignore, delete, restore or purge the variant of the page in the current language
    langActions(node) {
      if (!this.purgeable(node)) return false

      const can = (action) => this.user.can('page:' + action)
      return can('purge') || (can('save') && this.isStale(node)) || (node.variant_deleted_at ? can('keep') : !this.restorable(node) && can('drop'))
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

    // list filters with the given filter values for the pages query
    listFilter(values = {}) {
      const filter = {}

      for (const key in this.filter) {
        if (!['lang', 'publish', 'trashed', 'view'].includes(key) && this.filter[key] !== null) {
          filter[key] = this.filter[key]
        }
      }

      return Object.assign(filter, values)
    },

    // queries the pages matching the list filters and the given filter values
    pages(values, page, limit, sort, msg, trashed = null) {
      if (!this.allowed('view')) {
        return Promise.resolve({ data: [], currentPage: 1, lastPage: 1 })
      }

      const filter = this.listFilter(values)

      return this.$apollo
        .query({
          query: FETCH_PAGES,
          fetchPolicy: listFetchPolicy(),
          variables: {
            filter,
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

    // reloads the rows after their variant changed, e.g. the source variant is shown after
    // trashing the variant in the current language; rows not matching the filter anymore are removed.
    // The rows are fetched in parallel chunks of 100 to stay below the GraphQL complexity limit
    refetch(list, size = 100) {
      const chunks = []

      for (let i = 0; i < list.length; i += size) {
        chunks.push(list.slice(i, i + size))
      }

      return Promise.all(
        chunks.map((chunk) => {
          const ids = chunk.map((stat) => stat.data.id)
          return this.pages({ id: ids }, 1, ids.length, undefined, this.$gettext('Error fetching pages'))
        })
      ).then((results) => {
        results.forEach((result, idx) => {
          // rows of failed requests are left unchanged
          if (!result?.data) {
            return
          }

          const byId = new Map(result.data.map((item) => [item.id, item]))

          for (const stat of chunks[idx]) {
            const item = byId.get(stat.data.id)

            if (item) {
              Object.assign(stat.data, item, { has: stat.data.has })
              stat._checked = false
            } else if (this.$refs.tree?.statsFlat.includes(stat)) {
              this.$refs.tree.remove(stat)
            }
          }
        })
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
        return this.$apollo.provider.defaultClient
          .clearStore()
          .catch((error) => this.$log('PageListItems::reload: Error clearing cache', error))
          .then(() => this.refresh())
      }

      return this.refresh()
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

    resetFilter: listBase.methods.resetFilter,

    sync: listBase.methods.sync,

    scheduled(item) {
      return this.$gettext('Scheduled for %{date}', { date: formatDate(item.publish_at, this.$vuetify.locale.current, false) })
    },

    title(item) {
      const list = []

      if (this.translationStore.has(item.id)) {
        list.push(this.translatingLabel(item))
      }

      if (item.publish_at) {
        list.push(this.scheduled(item))
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

    // describes the running translations of the page, e.g. "Translating into DE, FR"
    translatingLabel(node) {
      const langs = this.translationStore.langsOf(node.id).map((code) => code.toUpperCase())
      return this.$gettext('Translating into %{langs}', { langs: langs.join(', ') })
    },

    transform(result) {
      return {
        data: result.data.map(hydrate),
        currentPage: result.paginatorInfo.currentPage,
        lastPage: result.paginatorInfo.lastPage
      }
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

    url(node) {
      return pageUrl(node)
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
      this.debouncedReload()
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

      <PageBulkMenu
        :counts="counts"
        :selected="isChecked"
        :disabled="!isChecked || embed"
        @open="count()"
        @publish="publish()"
        @enable="status(null, 1)"
        @disable="status(null, 0)"
        @edit-props="editProps()"
        @edit-access="editAccess()"
        @clear="clear()"
        @drop="drop()"
        @keep="keep()"
        @purge="purge()"
        @translate="translate()"
        @ignore="ignore()"
        @drop-lang="dropLang()"
        @keep-lang="keepLang()"
        @purge-lang="purgeLang()"
      />

      <v-btn
        v-if="!embed && user.can('page:add')"
        @click="add()"
        :disabled="loading"
        :title="$gettext('Add page')"
        :icon="mdiPlus"
        class="btn-add"
        color="primary"
        variant="tonal"
      />
    </div>

    <ListSearch ref="search" v-model="term">
      <PageLangSelect v-model="lang" />
    </ListSearch>

    <ListReload :loading="loading" :outdated="outdated" :title="$gettext('Reload page tree')" @reload="reload()" />

    <ListSort v-if="filter.view === 'list'" v-model="sort" :options="sortOptions" />
  </div>

  <div v-if="progress" class="translate-progress" role="status">
    <v-progress-linear
      :model-value="progress.total ? ((progress.done + progress.failed) * 100) / progress.total : 0"
      :indeterminate="!progress.done && !progress.failed"
      color="primary"
      rounded
    />
    <span class="text">
      {{ $gettext('Translating %{done} of %{total}', { done: progress.done + progress.failed, total: progress.total }) }}
    </span>
  </div>

  <Draggable
    ref="tree"
    v-model="items"
    @change="change()"
    @before-drag-start="origin = position($event)"
    @keydown.alt.capture="reorderStart"
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

        <v-checkbox-btn
          v-model="stat._checked"
          :class="{ draft: !node.published }"
          :aria-label="$gettext('Toggle selection')"
        />

        <span class="btn-actions">
          <ActionMenu list-class="page-action-menu">
            <template #activator="{ props, label }">
              <v-btn v-bind="props" :title="label" :icon="mdiDotsVertical" variant="text" />
            </template>
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
              v-if="!missing(node) && !node.deleted_at && !node.published && user.can('page:publish')"
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
              <ActionItem v-for="place in places" :key="place.label" :prepend-icon="place.icon" @click="menu.fn(stat, place.idx)">
                {{ place.label }}
              </ActionItem>
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
            <ActionItem v-if="node.source === lang && user.can('page:purge')" :prepend-icon="mdiDeleteForever" @click="purge(stat)">
              {{ $gettext('Purge') }}
            </ActionItem>

            <template v-if="translatable(node)">
              <v-divider></v-divider>

              <ActionItem :prepend-icon="mdiTranslate" class="action-translate" @click="translate(stat)">
                {{ $gettext('Translate') }}
              </ActionItem>
            </template>

            <template v-if="langActions(node)">
              <v-divider></v-divider>

              <ActionItem
                v-if="isStale(node) && user.can('page:save')"
                :prepend-icon="mdiCheckAll"
                class="action-ignore-changes"
                @click="ignore(stat)"
              >
                {{ $gettext('Ignore changes') }}
              </ActionItem>

              <ActionItem
                v-if="!restorable(node) && user.can('page:drop')"
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
                {{ $gettext('Purge language') }}
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
              v-if="isStale(node)"
              class="item-stale"
              :icon="mdiSync"
              :aria-label="$gettext('Needs update')"
            />
            <v-icon
              v-if="translationStore.has(node.id)"
              class="item-translating"
              :icon="mdiTranslate"
              :aria-label="translatingLabel(node)"
            />
            <v-icon
              v-if="node.publish_at"
              class="publish-at"
              :icon="mdiClockOutline"
              :aria-label="scheduled(node)"
            />
            <v-icon
              v-if="node.restricted"
              class="item-access"
              :icon="mdiLock"
              :title="accessTitle(node.access)"
            />
            <v-icon
              v-if="node.status > 1"
              class="item-status"
              :icon="mdiEyeOffOutline"
              :aria-label="$gettext('Hidden in navigation')"
            />
            <span class="item-title">{{ node.name || $gettext('New') }}</span>
          </div>
          <div v-if="node.title" class="item-subtitle">{{ node.title }}</div>
        </a>
        <a
          v-if="!missing(node)"
          class="item-aux"
          :href="url(node)"
          target="_blank"
          rel="noopener noreferrer"
          draggable="false"
        >
          <div class="item-domain">{{ node.domain }}</div>
          <span class="item-path item-subtitle">{{ '/' + (node.path || '') }}</span>
          <span v-if="node.to" class="item-to item-subtitle">
            ➔ {{ node.to.substring(0, MAX_TO_LENGTH) + (node.to.length > MAX_TO_LENGTH ? '...' : '') }}</span
          >
        </a>
        <div v-else-if="!node.deleted_at && !embed && node.variant_trashed && user.can('page:keep')" class="item-create">
          <v-btn
            @click="keepLang(stat)"
            :prepend-icon="mdiDeleteRestore"
            class="btn-keep-lang"
            variant="tonal"
            size="small"
          >
            {{ $gettext('Restore') }}
          </v-btn>
        </div>
        <div v-else-if="!node.deleted_at && !embed && !node.variant_trashed && user.can('page:add')" class="item-create">
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

  <div v-if="!embed && user.can('page:add')" class="btn-group">
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

  <TranslateDialog
    v-model="translateDialog"
    :count="translateItems.length"
    :langs="translateLangs"
    :sources="translateSources"
    @apply="translateApply"
  />

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

<style scoped>
.item-translating {
  animation: item-translating 1.6s ease-in-out infinite;
  color: rgb(var(--v-theme-primary));
}

@keyframes item-translating {
  50% {
    opacity: 0.35;
  }
}

@media (prefers-reduced-motion: reduce) {
  .item-translating {
    animation: none;
  }
}

.translate-progress {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
}

.translate-progress .text {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.875rem;
  white-space: nowrap;
}
</style>

<style>
/* he-tree renders the tree nodes, so their styles must be global */
.drag-placeholder {
  height: 48px;
}

.drag-placeholder-wrapper .tree-node-inner {
  background-color: rgb(var(--v-theme-surface));
}

.tree-node-inner {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  transition: background-color 0.15s ease;
  align-items: start;
  display: flex;
  padding: 4px 0;
  user-select: none;
}

.tree-node:focus {
  outline: none;
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

.tree-node-inner .item-content.missing .item-text {
  opacity: 0.5;
}

.tree-node-inner .item-create {
  padding-inline-start: 8px;
  margin-inline-start: auto;
  align-self: center;
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
