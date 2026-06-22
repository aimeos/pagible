/** @license MIT, https://opensource.org/license/mit */

/**
 * Loads the latest version of a content item into a detail view and applies it.
 *
 * Runs the GraphQL fetch plus the shared destroyed / errors / no-version guards and error
 * handling; the per-model field mapping is the caller's apply(model) callback, which receives
 * the queried item (model.latest is the version). Resolves true on success so the caller can
 * defer its websocket subscription until the load completed.
 *
 * @param {object} vm Detail-view component (uses $apollo, item.id, destroyed, loading, messages, reset, $log)
 * @param {object} query GraphQL query document
 * @param {string} key Result key on result.data ('page' | 'element' | 'file')
 * @param {string} message Translated, user-facing error message
 * @param {(model: object) => void} apply Applies the fetched item to the component
 * @param {(() => boolean)|null} keep Optional guard re-checked after the fetch; if it returns
 *   false (e.g. the user started editing while the request was in flight) the result is discarded
 *   so unsaved edits are not overwritten
 * @returns {Promise<boolean>}
 */
export function reloadVersion(vm, query, key, message, apply, keep = null) {
  return vm.$apollo
    .query({ query, fetchPolicy: 'no-cache', variables: { id: vm.item.id } })
    .then((result) => {
      if (vm.destroyed) return false

      if (result.errors || !result.data[key]) {
        throw result
      }

      if (!result.data[key].latest) {
        throw new Error('No version data available')
      }

      // the fetch is async: re-check now that applying is still safe - the user may have started
      // editing while it was in flight, and reset()+apply would silently wipe those edits
      if (keep && !keep()) return false

      vm.reset()
      apply(result.data[key])
      vm.loading = false
      return true
    })
    .catch((error) => {
      vm.loading = false
      vm.messages.add(message + ':\n' + error, 'error')
      vm.$log('reloadVersion(' + key + ')', error)
      return false
    })
}
