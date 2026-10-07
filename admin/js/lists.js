/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { getCurrentInstance } from 'vue'
import {
  mdiDotsVertical,
  mdiPublish,
  mdiDelete,
  mdiDeleteRestore,
  mdiDeleteForever,
  mdiPlus,
  mdiMagnify,
  mdiClockOutline,
  mdiRefresh,
  mdiPencil,
  mdiCloseCircleOutline
} from '@mdi/js'
import { setupEcho, listEcho } from './echo'
import { invalidateList, listFetchPolicy } from './graphql'
import { command, useShortcuts } from './shortcuts'
import {
  useChangeStore,
  useConfirmStore,
  useDrawerStore,
  useMessageStore,
  useUserStore
} from './stores'
import { debounce } from './utils'

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

const mutations = new Map()

/**
 * Returns the cached mutation document for an action like "drop" or "bulk" of the given type
 * "lang" adds a $lang variable for actions applied to the page variants of one language
 */
export function mutation(action, type, lang = false) {
  const model = type[0].toUpperCase() + type.slice(1)
  const name = action + model
  const key = name + (lang ? ':lang' : '')

  if (!mutations.has(key)) {
    mutations.set(key, action === 'bulk'
      ? gql`mutation ($id: [ID!]!, $input: ${model}Input!) { ${name}(id: $id, input: $input) { ids } }`
      : lang
        ? gql`mutation ($id: [ID!]!, $lang: String) { ${name}(id: $id, lang: $lang) { id } }`
        : gql`mutation ($id: [ID!]!) { ${name}(id: $id) { id } }`)
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
    user: useUserStore(),
    changes: useChangeStore(),
    confirm: useConfirmStore(),
    messages: useMessageStore(),
    mdiDotsVertical,
    mdiPublish,
    mdiDelete,
    mdiDeleteRestore,
    mdiDeleteForever,
    mdiPlus,
    mdiMagnify,
    mdiClockOutline,
    mdiRefresh,
    mdiPencil,
    mdiCloseCircleOutline
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

  emits: ['select'],

  data() {
    return {
      items: [],
      checked: new Set(),
      term: '',
      sort: this.user.setting(this.type, 'sort', { column: 'ID', order: 'DESC' }),
      page: 1,
      last: 1,
      limit: 100,
      editDialog: false,
      editIds: [],
      editSelected: false,
      loading: true,
      outdated: false
    }
  },

  created() {
    this.searchd = debounce(this.search, 500)
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
    this.unsubscribe?.()

    this.items = null
    this.checked = null
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
      const list = this.allowed('drop') ? (item ? [item] : this.selected()) : []

      if (list.length) {
        this.mutate('drop', list).then((ok) => {
          ok && this.messages.add(
            this.$ngettext('Moved to trash', '%{num} entries moved to trash', list.length, {
              num: list.length
            }),
            'success',
            null,
            this.user.can(`${this.type}:keep`)
              ? { label: this.$gettext('Undo'), handler: () => this.keep(list) }
              : null
          )
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
      const list = this.allowed('keep') ? (Array.isArray(item) ? item : item ? [item] : this.selected()) : []

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
      const list = this.allowed('publish')
        ? item ? [item] : this.selected().filter((item) => item.id && !item.published)
        : []

      if (list.length) {
        this.mutate('pub', list)
      }
    },

    async purge(item) {
      const list = this.allowed('purge') ? (item ? [item] : this.selected()) : []

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
      this.loading = true
      return this.$apollo.provider.defaultClient.clearStore().then(() => this.search())
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

        Object.assign(this.filter, filter)
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
            if (selected) {
              this.checked = selected
            }
          })
        })
        .catch((error) => {
          this.messages.error(this.failed('save'), error, ids, lang)
        })
    },

    search() {
      if (!this.allowed('view')) {
        return Promise.resolve([])
      }

      this.loading = true

      return this.$apollo
        .query(this.options())
        .then((result) => {
          const list = result.data[this.type + 's'] || {}

          this.last = list.paginatorInfo?.lastPage || 1
          this.items = (list.data || []).map((entry) => this.hydrate(entry))
          this.checked = new Set()
          this.outdated = false
          this.loading = false

          return this.items
        })
        .catch((error) => {
          this.messages.error(this.failed('search'), error)
        })
    },

    selected() {
      return this.items.filter((item) => this.checked.has(item.id))
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

    toggle() {
      this.checked = new Set(this.checked.size ? [] : this.items.map((item) => item.id))
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
