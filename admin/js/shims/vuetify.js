/**
 * @license MIT, https://opensource.org/license/mit
 */

// Re-export Vuetify composables (useTheme, useDisplay, ...) so plugins share the
// host's single Vuetify instance (theme, locale, display) instead of bundling
// their own.
export * from 'vuetify'
