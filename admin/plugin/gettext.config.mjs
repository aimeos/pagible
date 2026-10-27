/**
 * @license MIT, https://opensource.org/license/mit
 */

/**
 * Shared vue3-gettext configuration for the admin plugins in the <package>/admin/ directories.
 *
 * Use it in the plugin directory with "vue-gettext-extract --config ../../admin/plugin/gettext.config.mjs".
 * Plugins use the same languages as the admin, their PO files are in ./i18n and the compiled
 * JSON files in ./public/i18n which are copied to ./dist/i18n.
 */

import { mkdirSync, readFileSync } from 'node:fs'

mkdirSync('public/i18n', { recursive: true })

export default {
  input: {
    path: './src',
    include: ['**/*.js', '**/*.vue']
  },
  output: {
    locales: readFileSync(new URL('../i18n/LINGUAS', import.meta.url), 'utf8').trim().split(/\s+/),
    path: './i18n',
    jsonPath: '../public/i18n',
    splitJson: true,
    fuzzyMatching: false
  }
}
