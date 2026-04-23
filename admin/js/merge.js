/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


/**
 * Applies conflict info from a save response to a detail view
 *
 * @param {Object} vm Vue component instance with item, changed, vchanged, hasConflict
 * @param {Object} changed Parsed conflict info from save response
 */
export function applyConflict(vm, changed) {
  Object.assign(vm.item, changed.latest?.data ?? {})
  vm.changed = changed
  vm.vchanged = vm.hasConflict
}


/**
 * Handles save result: resets state, applies conflicts or shows success message
 *
 * @param {Object} vm Vue component instance
 * @param {Object|null} changed Parsed conflict info from save response
 * @param {string} successMsg Translated success message
 * @param {boolean} quiet If true, suppress success message
 */
export function applyResult(vm, changed, successMsg, quiet) {
  vm.item.published = false
  vm.reset()

  if (changed) {
    applyConflict(vm, changed)
    vm.messages.add(
      vm.$gettext('Merged with changes from %{editor}', { editor: changed.editor }),
      vm.hasConflict ? 'warning' : 'info'
    )
  } else if (!quiet) {
    vm.messages.add(successMsg, 'success')
  }
}


/**
 * Returns merged/conflict CSS class state for a changed entry
 *
 * @param {Object} changed Changed entries map
 * @param {string} key Entry key to check
 * @returns {{merged: boolean, conflict: boolean}}
 */
export function changedState(changed, key) {
  const info = changed?.[key]
  return {
    merged: info && (!info.overwritten || info.resolved),
    conflict: !!info?.overwritten && !info?.resolved
  }
}


/**
 * Checks if any conflict sections have unresolved entries
 *
 * @param {Object} changed Conflict info object
 * @param {string[]} sections Section names to check
 * @returns {boolean}
 */
export function hasUnresolved(changed, sections = ['data']) {
  return sections.some((s) =>
    Object.values(changed?.[s] || {}).some((v) => v.overwritten && !v.resolved)
  )
}
