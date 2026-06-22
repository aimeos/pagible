/**
 * @license MIT, https://opensource.org/license/mit
 */

// Lazy glob of all field SFCs. The keys (paths) are available synchronously
// without importing the components, so they can be used to build the registry
// and the allowlist below at module-evaluation time.
const modules = import.meta.glob('@/fields/*.vue')

/**
 * Map of registered field component name (e.g. "Text") to its async loader.
 *
 * Used by main.js to register the field components globally.
 */
export const fieldComponents = Object.fromEntries(
  Object.entries(modules).map(([path, loader]) => [
    path.split('/').at(-1).replace(/\.vue$/, ''),
    loader
  ])
)

/**
 * Set of valid field component names, derived from the same glob that registers
 * them. Used to allowlist the dynamic `<component :is="...">` field rendering so
 * an unknown or hostile field type cannot resolve to a native HTML element.
 */
export const fieldTypes = new Set(Object.keys(fieldComponents))
