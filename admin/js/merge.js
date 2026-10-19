/**
 * @license MIT, https://opensource.org/license/mit
 */


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
    Object.assign(vm.item, changed.latest?.data ?? {})
    vm.changed = changed
    vm.vchanged = vm.hasConflict
    vm.messages.add(
      vm.$gettext('Merged with changes from %{editor}', { editor: changed.editor || '' }),
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


/**
 * Applies the chosen value of a conflict to the target and remembers the replaced value
 *
 * @param {Object} changed Conflict info object
 * @param {Object} targets Lists or objects of the sections the values are written to
 * @param {string} section Section name of the conflict
 * @param {string} key Key or element ID of the conflict
 * @param {*} value Chosen value
 */
export function resolveChange(changed, targets, section, key, value) {
  const info = changed[section][key]
  const [target, idx] = changeTarget(targets, section, key)

  if (target) {
    info.snapshot = target[idx]
    target[idx] = value
  }

  // a flag because the chosen value may be falsy, e.g. an empty title
  info.resolved = true
  info.chosen = value
}


/**
 * Restores the value replaced by resolveChange() and marks the conflict as unresolved again
 *
 * @param {Object} changed Conflict info object
 * @param {Object} targets Lists or objects of the sections the values are written to
 * @param {string} section Section name of the conflict
 * @param {string} key Key or element ID of the conflict
 */
export function unresolveChange(changed, targets, section, key) {
  const info = changed[section][key]
  const [target, idx] = changeTarget(targets, section, key)

  if (target && 'snapshot' in info) {
    target[idx] = info.snapshot
  }

  delete info.snapshot
  delete info.resolved
  delete info.chosen
}


// target list or object of the section and the index or key of the entry, no target if not found
function changeTarget(targets, section, key) {
  const target = targets?.[section]

  if (Array.isArray(target)) {
    const idx = target.findIndex((b) => (b.id || b.refid) === key)
    return idx >= 0 ? [target, idx] : [null, -1]
  }

  return [target, key]
}
