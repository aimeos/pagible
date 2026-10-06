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

/** Field components supporting private file storage controlled by page access. */
export const protectTypes = new Set(['Audio', 'File', 'Image', 'Images', 'Media', 'Video'])

/** Field components rendering the `hint` config option themselves as Vuetify input hint. */
export const hintTypes = new Set([
  'Autocomplete', 'Checkbox', 'Color', 'Combobox', 'Date', 'Html', 'Number', 'Plaintext',
  'Radio', 'Range', 'Select', 'Slider', 'String', 'Switch', 'Url'
])

/** AI text generation context for the field config with the given code and the surrounding data. */
export const aiContext = (field, code, data) => [
  'generate for field "' + (field.label || code) + '"',
  'required output format is "' + field.type + '"',
  field.min ? 'minimum characters: ' + field.min : null,
  field.max ? 'maximum characters: ' + field.max : null,
  field.placeholder ? 'hint text: ' + field.placeholder : null,
  field.hint ? 'field description: ' + field.hint : null,
  'context information as JSON: ' + JSON.stringify(data)
]

/** Registered field component name for the schema field type, "Hidden" for unknown types. */
export const toName = (type) => {
  const name = type ? type.charAt(0).toUpperCase() + type.slice(1) : ''
  return fieldTypes.has(name) ? name : 'Hidden'
}
