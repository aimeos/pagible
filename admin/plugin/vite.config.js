/**
 * @license MIT, https://opensource.org/license/mit
 */

/**
 * Shared Vite configuration for the admin plugins in the <package>/admin/ directories.
 *
 * Use it in the plugin directory with "vite build --config ../../admin/plugin/vite.config.js".
 * The entry module ./src/index.js is built to ./dist/index.js and the plugin name is the package
 * directory. The host only loads the plugin's JavaScript file, so the CSS is added to the entry
 * module, which inserts it into the page when the plugin is loaded.
 */

import { basename, dirname, resolve } from 'node:path'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

const name = basename(dirname(process.cwd()))

export default defineConfig({
  plugins: [vue(), injectCss(name)],
  build: {
    emptyOutDir: true,
    lib: {
      entry: resolve('src/index.js'),
      formats: ['es'],
      fileName: () => 'index.js'
    },
    rollupOptions: {
      external: ['graphql-tag', 'vue']
    }
  }
})

function injectCss(name) {
  return {
    name: 'cms-inject-css',
    apply: 'build',
    enforce: 'post',
    generateBundle(options, bundle) {
      const css = Object.keys(bundle).filter((file) => file.endsWith('.css'))
      const entry = Object.values(bundle).find((chunk) => chunk.type === 'chunk' && chunk.isEntry)

      if (!css.length || !entry) return

      const styles = css.map((file) => bundle[file].source).join('\n')
      entry.code =
        'if (typeof document !== "undefined") {' +
        'const s = document.createElement("style");' +
        `s.dataset.cms = ${JSON.stringify(name)};` +
        `s.textContent = ${JSON.stringify(styles)};` +
        'document.head.appendChild(s);' +
        '}\n' +
        entry.code

      css.forEach((file) => delete bundle[file])
    }
  }
}
