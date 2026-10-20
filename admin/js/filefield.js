/**
 * @license MIT, https://opensource.org/license/mit
 */

import {
  mdiButtonCursor,
  mdiCreation,
  mdiLinkVariantPlus,
  mdiTrayArrowDown,
  mdiUpload
} from '@mdi/js'
import { defineAsyncComponent } from 'vue'
import { useMessageStore, useUserStore, useViewStack } from './stores'
import { filesrcset } from './urls'
import { createFile, fetchFileDisks, previewUrl, relocateFiles } from './files'
import { invalidateList } from './graphql'
import FileActionMenu from './components/FileActionMenu.vue'
import FileProtect from './components/FileProtect.vue'

/**
 * Components used by the file fields, spread into each field's own `components`
 * option because test stubs only match components registered there
 */
export const components = {
  FileActionMenu,
  FileAiDialog: defineAsyncComponent(() => import('./components/FileAiDialog.vue')),
  FileDialog: defineAsyncComponent(() => import('./components/FileDialog.vue')),
  FileProtect,
  FileUrlDialog: defineAsyncComponent(() => import('./components/FileUrlDialog.vue'))
}

/**
 * Returns if the file matches the accept pattern of file inputs, e.g. "image/*,video/mp4,.pdf"
 *
 * @param {File} file Dropped or selected file
 * @param {string} accept Comma separated MIME types, wildcard MIME types or file extensions
 * @returns {boolean} TRUE if the file is accepted
 */
export function accepts(file, accept) {
  const types = String(accept || '*').split(',').map((type) => type.trim().toLowerCase()).filter(Boolean)
  const mime = (file?.type || '').toLowerCase()
  const name = (file?.name || '').toLowerCase()

  return types.some((type) => {
    if (type === '*' || type === '*/*') return true
    if (type.startsWith('.')) return name.endsWith(type)
    if (type.endsWith('/*')) return mime.startsWith(type.slice(0, -1))
    return mime === type
  })
}

/**
 * Setup state shared by the file fields, setup() isn't merged by "extends"
 */
export function useFileField() {
  return {
    messages: useMessageStore(),
    user: useUserStore(),
    viewStack: useViewStack(),
    filesrcset,
    previewUrl,
    mdiButtonCursor,
    mdiCreation,
    mdiLinkVariantPlus,
    mdiTrayArrowDown,
    mdiUpload
  }
}

/**
 * Base component for the File and Images fields
 */
export default {
  inheritAttrs: false,

  components,

  props: {
    config: { type: Object, default: () => ({}) },
    assets: { type: Object, default: () => ({}) },
    label: { type: String, default: '' },
    readonly: { type: Boolean, default: false },
    context: { type: Object }
  },

  emits: ['update:modelValue', 'error', 'addFile', 'removeFile'],

  inject: {
    update: { default: null }
  },

  data() {
    return {
      dragging: false,
      protect: false,
      protecting: false,
      vcreate: false,
      vfiles: false,
      vurls: false
    }
  },

  computed: {
    // accepted file types like in the file inputs of the File and Images fields
    accept() {
      return this.config.accept || this.profile?.accept || (this.images ? 'image/*' : '*')
    }
  },

  methods: {
    drop(event) {
      this.dragging = false

      const dropped = Array.from(event.dataTransfer?.files || [])
      const files = dropped.filter((file) => accepts(file, this.accept))

      if (files.length < dropped.length) {
        this.messages.add(this.$gettext('Unsupported file type'), 'error')
      }

      if (files.length) {
        this.add(this.images ? files : files[0])
      }
    },

    async open(item) {
      // Editing a file in the stacked FileDetail only updates the file's own
      // (already persisted) draft, not the page content, so just refresh the
      // preview when FileDetail saves.
      const { default: FileDetail } = await import('./views/FileDetail.vue')

      this.viewStack.openView(FileDetail, {
        initial: item,
        stacked: true,
        onSaved: () => this.update?.()
      })
    },

    /**
     * Moves the files to the private or public disk
     *
     * Returns the updated files, or their current state from the server if moving failed.
     *
     * @param {Array} files File objects with ID
     * @param {Boolean} protect True to move the files to the private disk
     * @returns {Promise<{items: Array, failed: Boolean}>}
     */
    async relocate(files, protect) {
      const ids = files.map((item) => item.id)

      this.protecting = true

      try {
        return { items: await relocateFiles(this.$apollo, ids, protect ? 'private' : 'public'), failed: false }
      } catch (error) {
        this.messages.error(this.$gettext(`Error saving file`), error)

        try {
          return { items: await fetchFileDisks(this.$apollo, ids), failed: true }
        } catch (reloadError) {
          this.$log(`FileField::relocate(): Error reloading files`, reloadError)
          return { items: [], failed: true }
        }
      } finally {
        this.protecting = false
      }
    },

    /**
     * Uploads the file to the disk selected by the protect switch
     *
     * Returns a placeholder for showing the upload and a promise resolving to the
     * created file, or to null if the upload failed.
     *
     * @param {File} file Selected or dropped file
     * @returns {{pending: Object, promise: Promise<Object|null>}}
     */
    upload(file) {
      const path = URL.createObjectURL(file)
      const disk = this.protect ? 'private' : 'public'

      const promise = createFile(this.$apollo, { disk, file })
        .then((item) => {
          invalidateList('files')
          return item
        })
        .catch((error) => {
          this.messages.error(this.$gettext(`Error adding file %{path}`, { path: file.name }), error, file)
          return null
        })
        .finally(() => {
          URL.revokeObjectURL(path)
        })

      return { pending: { disk, path, uploading: true }, promise }
    }
  }
}
