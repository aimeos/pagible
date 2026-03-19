/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import { ClassicEditor } from '@ckeditor/ckeditor5-editor-classic'
import { Markdown } from '@ckeditor/ckeditor5-markdown-gfm'
import { Essentials } from '@ckeditor/ckeditor5-essentials'
import { PasteFromOffice } from '@ckeditor/ckeditor5-paste-from-office'
import { Fullscreen } from '@ckeditor/ckeditor5-fullscreen'
import { Clipboard } from '@ckeditor/ckeditor5-clipboard'
import { FindAndReplace } from '@ckeditor/ckeditor5-find-and-replace'
import { RemoveFormat } from '@ckeditor/ckeditor5-remove-format'
import { Paragraph } from '@ckeditor/ckeditor5-paragraph'
import { Bold, Italic, Strikethrough, Code } from '@ckeditor/ckeditor5-basic-styles'
import { AutoLink, Link } from '@ckeditor/ckeditor5-link'
import { Ckeditor } from '@ckeditor/ckeditor5-vue'
import { getTranslations } from '@/ckeditor-translations.js'
import '@ckeditor/ckeditor5-theme-lark/dist/index.css'

export default {
  components: {
    Ckeditor
  },

  props: {
    modelValue: { type: String },
    config: { type: Object, default: () => {} },
    assets: { type: Object, default: () => {} },
    readonly: { type: Boolean, default: false },
    context: { type: Object }
  },

  emits: ['update:modelValue', 'error'],

  data() {
    return {
      editor: ClassicEditor,
      visible: false
    }
  },

  beforeUnmount() {
    this.visible = false // avoid CKEditor DOM issues
  },

  computed: {
    ckconfig() {
      return {
        licenseKey: 'GPL',
        plugins: [
          Markdown,
          Essentials,
          PasteFromOffice,
          Fullscreen,
          Clipboard,
          FindAndReplace,
          RemoveFormat,
          Paragraph,
          Bold,
          Italic,
          Strikethrough,
          Code,
          AutoLink,
          Link
        ],
        toolbar: [
          'undo',
          'redo',
          'removeFormat',
          '|',
          'bold',
          'italic',
          'strikethrough',
          'link',
          'code',
          '|',
          'fullscreen'
        ],
        translations: getTranslations(this.$vuetify.locale.current),
        language: {
          ui: this.$vuetify.locale.current
        }
      }
    },

    rules() {
      return [
        (v) =>
          !this.config.min ||
          +v?.length >= +this.config.min ||
          this.$gettext(`Minimum length is %{num} characters`, { num: this.config.min }),
        (v) =>
          !this.config.max ||
          +v?.length <= +this.config.max ||
          this.$gettext(`Maximum length is %{num} characters`, { num: this.config.max })
      ]
    }
  },

  methods: {
    show(isVisible) {
      this.visible = isVisible
    },

    update(value) {
      if (this.modelValue != value) {
        this.$emit('update:modelValue', value)
      }
    }
  },

  watch: {
    modelValue: {
      immediate: true,
      handler(val) {
        this.$emit(
          'error',
          !this.rules.every((rule) => {
            return rule(val ?? this.config.default ?? '') === true
          })
        )
      }
    }
  }
}
</script>

<template>
  <div v-observe-visibility="show">
    <div v-if="visible">
      <ckeditor
        :config="ckconfig"
        :editor="editor"
        :disabled="readonly"
        :modelValue="modelValue ?? config.default ?? ''"
        @update:modelValue="update($event)"
      ></ckeditor>
    </div>
  </div>
</template>

<style></style>
