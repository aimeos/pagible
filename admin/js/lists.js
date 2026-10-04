/**
 * @license MIT, https://opensource.org/license/mit
 */

import { getCurrentInstance } from 'vue'
import { command, useShortcuts } from './shortcuts'
import { useDrawerStore } from './stores'

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
export function useListKeys() {
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
