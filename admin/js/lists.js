/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { getCurrentInstance } from 'vue'
import { mdiPlus, mdiClockOutline } from '@mdi/js'
import { setupEcho } from './echo'
import { listEcho } from './livesync'
import { invalidateList, listFetchPolicy } from './graphql'
import { command, useShortcuts } from './shortcuts'
import {
  useChangeStore,
  useConfirmStore,
  useDrawerStore,
  useMessageStore,
  useSettingStore,
  useUserStore
} from './stores'
import { debounce, REQUEST_DELAY, ucfirst } from './utils'

/**
 * Returns the number of items in total and those affected by publishing, deleting and restoring
 */
export function tally(items) {
  const counts = { all: 0, draft: 0, live: 0, trashed: 0 }

  for (const item of items) {
    counts.all++
    counts.draft += item.published ? 0 : 1
    counts[item.deleted_at ? 'trashed' : 'live']++
  }

  return counts
}

/**
 * Registers the list view actions unless the list is embedded, e.g. in a dialog
 * "create" gets the component instance and adds a new item of the given type if the user is allowed to
 */
export function useListShortcuts(type, create) {
  useShortcuts((vm) =>
    vm.embed
      ? null
      : {
          aside: () => useDrawerStore().toggle('aside'),
          ...(vm.user.can(`${type}:add`) && { create: () => create(vm) }),
          search: () => vm.$refs.search?.focus()
        }
  )
}

/**
 * Returns a keydown handler for lists whose items have a data-id attribute:
 * Space toggles the selection of the focused item and Delete drops it
 * Uses the "items", "toggleCheck" and "drop" members of the component
 */
function useListKeys() {
  const vm = getCurrentInstance().proxy

  return function listKey(ev) {
    const name = command(ev, 'list')
    const id = name && ev.target.closest?.('[data-id]')?.dataset.id
    const item = id && vm.items.find((item) => String(item.id) === id)

    if (!item) {
      return
    }

    ev.preventDefault() // don't scroll the page or navigate back

    if (name === 'select') {
      vm.toggleCheck(item)
    } else if (!item.deleted_at && !vm.embed) {
      vm.drop(item)
    }
  }
}

// copies the values of the item to the properties that already exist in the node
function assign(node, item) {
  for (const key in item) {
    if (key in node) node[key] = item[key]
  }
}

/**
 * Shows the "Moved to trash" message for the number of items with an undo action if "undo" is passed
 */
export function trashMessage(vm, count, undo = null) {
  vm.messages.add(
    vm.$ngettext('Moved to trash', '%{num} entries moved to trash', count, { num: count }),
    'success',
    null,
    undo ? { label: vm.$gettext('Undo'), handler: undo } : null
  )
}

const mutations = new Map()

/**
 * Returns the cached mutation document for an action like "drop" or "bulk" of the given type
 * "lang" adds a $lang variable for actions applied to the page variants of one language
 * "at" adds an $at variable for scheduled publishing
 */
export function mutation(action, type, lang = false, at = false) {
  const model = ucfirst(type)
  const name = action + model
  const key = name + (lang ? ':lang' : '') + (at ? ':at' : '')

  if (!mutations.has(key)) {
    if (action === 'bulk') {
      mutations.set(key, gql`mutation ($id: [ID!]!, $input: ${model}Input!) { ${name}(id: $id, input: $input) { ids } }`)
    } else {
      const vars = ['$id: [ID!]!', at && '$at: DateTime', lang && '$lang: String'].filter(Boolean)
      const args = ['id: $id', at && 'at: $at', lang && 'lang: $lang'].filter(Boolean)

      mutations.set(key, gql`mutation (${vars.join(', ')}) { ${name}(${args.join(', ')}) { id } }`)
    }
  }

  return mutations.get(key)
}

/**
 * Returns the setup bindings of the flat file and element lists
 * "create" gets the component instance and adds a new item when the user presses the shortcut
 */
export function useList(type, query, create) {
  useListShortcuts(type, create)

  return {
    type,
    query,
    listKey: useListKeys(),
    settings: useSettingStore(),
    user: useUserStore(),
    changes: useChangeStore(),
    confirm: useConfirmStore(),
    messages: useMessageStore(),
    mdiPlus,
    mdiClockOutline
  }
}

/**
 * Shared options of the flat file and element lists, used with "extends"
 * The components return useList() from setup() and implement add(), hydrate(entry) and failed(action)
 */
export const listBase = {
  props: {
    embed: { type: Boolean, default: false },
    defaults: { type: Object, default: null },
    filter: { type: Object, default: () => ({}) }
  },

  emits: ['select', 'update:filter'],

  data() {
    return {
      items: [],
      checked: new Set(),
      term: '',
      sort: this.settings.setting(this.type, 'sort', { column: 'ID', order: 'DESC' }),
      page: 1,
      last: 1,
      limit: 100,
      editDialog: false,
      editIds: [],
      editSelected: false,
      loading: true,
      outdated: false,
      // sequence number which discards responses of outdated searches
      searchSeq: 0
    }
  },

  created() {
    this.debouncedSearch = debounce(this.search, REQUEST_DELAY)
    this.unsubscribe = null
    this.search()
    this.$watch(() => this.changes.changed[this.type], () => this.sync())

    if (!this.embed) {
      // patch the matching row when another user changes an item; subscribe for the whole
      // lifetime (not per activation) so the list keeps patching in the background while the
      // editor is in a detail or another view and is up to date when they return
      this.unsubscribe = setupEcho(this.type, (event, name) => listEcho(this, event, name))
    }
  },

  beforeUnmount() {
    this.debouncedSearch.cancel()
    this.unsubscribe?.()
    // responses of running searches are discarded
    this.searchSeq++
  },

  activated() {
    this.sync()
    this.revalidate()
  },

  computed: {
    filtered() {
      if (this.term || !this.defaults) {
        return true
      }

      return Object.keys({ ...this.filter, ...this.defaults }).some((key) => {
        return (
          key !== 'view' &&
          JSON.stringify(this.filter[key] ?? null) !== JSON.stringify(this.defaults[key] ?? null)
        )
      })
    },

    counts() {
      return tally(this.selected())
    },

    isChecked() {
      return this.checked.size > 0
    },

    allChecked() {
      return this.items.length > 0 && this.items.every((item) => this.checked.has(item.id))
    }
  },

  methods: {
    allowed(action, type = this.type) {
      if (this.user.can(`${type}:${action}`)) {
        return true
      }

      return this.messages.denied()
    },

    drop(item) {
      const list = this.allowed('drop') ? this.targets(item) : []

      if (list.length) {
        this.mutate('drop', list).then((ok) => {
          ok && trashMessage(this, list.length, this.user.can(`${this.type}:keep`) ? () => this.keep(list) : null)
        })
      }
    },

    edit(item = null) {
      this.editIds = item ? [item.id] : [...this.checked]
      this.editSelected = !item
      this.editDialog = this.editIds.length > 0
    },

    invalidate() {
      invalidateList(this.type + 's')
    },

    keep(item) {
      const list = this.allowed('keep') ? this.targets(item) : []

      if (list.length) {
        this.mutate('keep', list)
      }
    },

    // runs the mutation for the items and reloads the list; resolves true on success
    mutate(action, list) {
      return this.$apollo
        .mutate({
          mutation: mutation(action, this.type),
          variables: { id: list.map((item) => item.id) }
        })
        .then(() => {
          this.invalidate()
          this.search()
          return true
        })
        .catch((error) => {
          this.messages.error(this.failed(action), error, list)
          return false
        })
    },

    options() {
      const publish = this.filter.publish || null
      const trashed = this.filter.trashed || 'WITHOUT'
      const filter = { ...this.filter }

      delete filter.publish
      delete filter.trashed

      for (const key in filter) {
        if (filter[key] === null) {
          delete filter[key]
        }
      }

      if (this.term) {
        filter.any = this.term
      }

      return {
        query: this.query,
        fetchPolicy: listFetchPolicy(),
        variables: {
          filter: filter,
          page: this.page,
          limit: this.limit,
          sort: [this.sort],
          trashed: trashed,
          publish: publish
        }
      }
    },

    patch(item) {
      const node = this.items?.find((node) => node.id === item.id)
      if (node) assign(node, item)
      return !!node
    },

    patchItems(items) {
      // index the patches by id so the bulk update is a single pass over the loaded rows
      const byId = new Map(items.map((item) => [item.id, item]))

      this.items?.forEach((node) => byId.has(node.id) && assign(node, byId.get(node.id)))
    },

    publish(item) {
      const list = this.allowed('publish') ? this.targets(item, (item) => item.id && !item.published) : []

      if (list.length) {
        this.mutate('pub', list)
      }
    },

    async purge(item) {
      const list = this.allowed('purge') ? this.targets(item) : []

      if (
        list.length &&
        (await this.confirm.purge(list.map((item) => ({ name: item.name, info: item.mime ?? item.type }))))
      ) {
        this.mutate('purge', list)
      }
    },

    reload() {
      this.outdated = false
      this.items = []
      // only the cached results of this list are dropped, not the whole cache
      invalidateList(this.type + 's', this.$apollo.provider.defaultClient.cache)
      return this.search()
    },

    // starts at the first page again after the filter, term or sort order changed
    research(debounced = false) {
      if (this.page !== 1) {
        // the page watcher runs the search
        this.debouncedSearch.cancel()
        this.page = 1
        return
      }

      debounced ? this.debouncedSearch() : this.search()
    },

    resetFilter() {
      this.term = ''

      if (this.defaults) {
        const filter = {}

        for (const key in this.filter) {
          if (key !== 'view') {
            filter[key] = this.defaults[key] ?? null
          }
        }

        this.$emit('update:filter', filter)
      }
    },

    revalidate() {
      if (this.loading) return

      const options = this.options()
      const cache = this.$apollo.provider.defaultClient.cache

      if (
        options.fetchPolicy === 'network-only' ||
        !cache.diff({
          query: options.query,
          variables: options.variables,
          returnPartialData: true
        }).complete
      ) {
        return this.search()
      }
    },

    save(lang) {
      if (!this.allowed('save')) {
        return
      }

      const ids = this.editIds
      const selected = this.editSelected ? null : new Set(this.checked)

      if (!ids.length || lang === null) {
        return
      }

      return this.$apollo
        .mutate({
          mutation: mutation('bulk', this.type),
          variables: {
            id: ids,
            input: { lang: lang }
          }
        })
        .then(() => {
          this.editIds = []
          if (this.editSelected) {
            this.checked = new Set()
          }
          this.editSelected = false
          this.invalidate()

          return this.search().then(() => {
            // the edited item may have re-ordered the list, keep only the selected items still shown
            if (selected) {
              this.checked = new Set(this.items.filter((item) => selected.has(item.id)).map((item) => item.id))
            }
          })
        })
        .catch((error) => {
          this.messages.error(this.failed('save'), error, ids, lang)
        })
    },

    search() {
      // the list stays empty without showing a message for each search
      if (!this.user.can(this.type + ':view')) {
        this.loading = false
        return Promise.resolve([])
      }

      // only the response of the latest request is applied
      const seq = ++this.searchSeq
      this.loading = true

      return this.$apollo
        .query(this.options())
        .then((result) => {
          if (seq !== this.searchSeq) return this.items

          const list = result.data[this.type + 's'] || {}

          this.last = list.paginatorInfo?.lastPage || 1
          this.items = (list.data || []).map((entry) => this.hydrate(entry))

          // the page watcher fetches the new last page if the items of the last page were removed
          if (this.page > this.last) {
            this.page = this.last
          }
          this.checked = new Set()
          this.outdated = false

          return this.items
        })
        .catch((error) => {
          if (seq === this.searchSeq) {
            this.messages.error(this.failed('search'), error)
          }
        })
        .finally(() => {
          if (seq === this.searchSeq) {
            this.loading = false
          }
        })
    },

    selected(fn = () => true) {
      return this.items.filter((item) => this.checked.has(item.id) && fn(item))
    },

    // returns the passed item or items, otherwise the selected ones matching the filter
    targets(item = null, fn = () => true) {
      if (Array.isArray(item)) {
        return item
      }

      return item ? [item] : this.selected(fn)
    },

    sync() {
      const ids = this.changes
        .get(this.type)
        .filter((item) => this.patch(item))
        .map((item) => item.id)

      this.changes.patched(this.type, ids)
    },

    title(item) {
      return item.publish_at
        ? this.$gettext('Scheduled for %{date}', { date: new Date(item.publish_at).toLocaleDateString() })
        : ''
    },

    // checks all items if one is unchecked, otherwise unchecks all
    toggle() {
      this.checked = new Set(this.allChecked ? [] : this.items.map((item) => item.id))
    },

    toggleCheck(item) {
      const next = new Set(this.checked)
      if (!next.delete(item.id)) next.add(item.id)
      this.checked = next
    }
  },

  watch: {
    filter: {
      deep: true,
      handler() {
        this.research()
      }
    },

    term() {
      this.research(true)
    },

    page() {
      this.search()
    },

    sort() {
      this.research()
    }
  }
}
