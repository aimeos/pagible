/**
 * @license MIT, https://opensource.org/license/mit
 */

import { getCurrentInstance } from 'vue'
import { command, useShortcuts } from './shortcuts'
import { useDrawerStore } from './stores'

/**
 * Registers the list view actions unless the list is embedded, e.g. in a dialog
 * "create" gets the component instance and adds a new item
 */
export function useListShortcuts(create) {
  useShortcuts((vm) =>
    vm.embed
      ? null
      : {
          aside: () => useDrawerStore().toggle('aside'),
          create: () => create(vm),
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
