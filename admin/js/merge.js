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
 * @param {boolean} edited If true, the item was edited while saving and stays dirty
 * @param {Object|null} sent Copy of the item when the save started, protects the fields edited while saving
 */
export function applyResult(vm, changed, successMsg, quiet, edited = false, sent = null) {
  vm.item.published = false

  if (edited) {
    vm.changed = null
  } else {
    vm.reset()
  }

  if (changed) {
    assignMerged(vm.item, changed.latest?.data ?? {}, edited ? sent : null)
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
 * Applies the merged data of a save response to the item
 *
 * Fields edited while saving (they differ from the sent copy) keep the editor's values and are
 * sent by the next save, all other fields get the merged values so the next save doesn't revert
 * the changes of other editors.
 *
 * @param {Object} item Item to update
 * @param {Object} data Merged data from the server
 * @param {Object|null} sent Copy of the item when the save started or null to apply all fields
 */
export function assignMerged(item, data, sent = null) {
  for (const [key, value] of Object.entries(data || {})) {
    if (!sent || JSON.stringify(item[key]) === JSON.stringify(sent[key])) {
      item[key] = value
    }
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
