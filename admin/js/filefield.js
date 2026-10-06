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
import { filesrcset } from './utils'
import { previewUrl } from './files'
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
    config: { type: Object, default: () => {} },
    assets: { type: Object, default: () => {} },
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

  methods: {
    drop(event) {
      this.dragging = false

      const files = event.dataTransfer?.files

      if (files?.length) {
        this.add(this.images ? files : files[0])
      }
    },

    async open(item) {
      // Editing a file in the stacked FileDetail only updates the file's own
      // (already persisted) draft, not the page content, so just refresh the
      // preview when FileDetail saves.
      const { default: FileDetail } = await import('./views/FileDetail.vue')

      this.viewStack.openView(FileDetail, {
        item: item,
        stacked: true,
        onSaved: () => this.update?.()
      })
    }
  }
}
