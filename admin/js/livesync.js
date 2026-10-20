/** @license MIT, https://opensource.org/license/mit */

import { PATCH_ACTIONS, RECONNECT, VARIANT_ACTIONS, setupEcho, subscribe } from './echo'
import { sanitize } from './json'
import { debounce, INPUT_DELAY } from './utils'

// How list and detail views apply the change events received from the per-type channels

/**
 * Builds the in-place row update a list/tree view applies from a patch event.
 */
export function eventPatch(event) {
  return {
    ...sanitize(event.data),
    id: event.id,
    published: event.published,
    deleted_at: event.deleted_at,
    publish_at: event.publish_at,
    updated_at: event.updated_at,
    editor: event.editor,
    latest_id: event.latest_id
  }
}

/**
 * Builds the in-place row update for one id of a coalesced bulk-edit event: the already-sanitized
 * shared fields (the same object for every id, so sanitize once and pass it in) plus that id's new
 * latest version id. The bulk payload (ids, latest map, shared data) mirrors what the bulk mutation
 * returns, so this is the broadcast counterpart of the editor's own response patch.
 */
export function bulkPatch(data, event, id) {
  return {
    ...data,
    id,
    editor: event.editor,
    latest_id: event.latest?.[id]
  }
}

/**
 * Routes a per-type channel event for a list/tree view: a reconnect or a structural change flags
 * the list for reload, a single-item patch action updates the row in place, and an event with
 * several ids (except purged ones) patches every row it lists via patchItems() in a single pass
 * over the loaded rows (O(rows), not O(ids x rows)). The originating tab is already excluded server-side via toOthers(), so other tabs
 * (even of the same user) still update. Reaches into vm.{outdated,patch,patchItems}, the shared
 * contract of the list components.
 */
export function listEcho(vm, event, name) {
  if (name === RECONNECT) { vm.outdated = true; return }
  if (event?.ids && name !== 'purged') {
    const data = sanitize(event.data)
    vm.patchItems((event.ids || []).map((id) => bulkPatch(data, event, id)))
    return
  }
  if (!PATCH_ACTIONS.includes(name)) { vm.outdated = true; return }
  vm.patch(eventPatch(event))
}

/**
 * Subscribes a detail view to its per-type channel and reloads (debounced) when its own item is
 * saved elsewhere, or after a reconnect that may have missed a save - guarded by enabled() (e.g.
 * not dirty and permitted). The returned disposer also cancels a pending reload.
 */
export function setupReload(type, id, reload, enabled, connect = subscribe) {
  const debouncedReload = debounce(reload, INPUT_DELAY)
  // a detail view reloads its own item on any patch action, a bulk change including its id, or reconnect
  const dispose = setupEcho(type, (event, name) => {
    if ((name === RECONNECT || event?.id === id || event?.ids?.includes(id)) && enabled()) {
      debouncedReload()
    }
  }, [...PATCH_ACTIONS, 'added', ...VARIANT_ACTIONS], connect)

  return () => {
    debouncedReload.cancel()
    dispose()
  }
}
