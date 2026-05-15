/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import {
  ClassicEditor,
  Markdown,
  Essentials,
  PasteFromOffice,
  Fullscreen,
  Clipboard,
  FindAndReplace,
  RemoveFormat,
  Heading,
  Paragraph,
  Bold,
  Italic,
  Strikethrough,
  BlockQuote,
  Code,
  CodeBlock,
  AutoLink,
  Link,
  List,
  SourceEditing
} from 'ckeditor5'
import { markRaw } from 'vue'
import { Ckeditor } from '@ckeditor/ckeditor5-vue'
import { translationCache, TRANSLATION_CACHE_MAX } from '../ckcache'
import 'ckeditor5/ckeditor5.css'

const ckPlugins = [
  Markdown,
  Essentials,
  PasteFromOffice,
  Fullscreen,
  Clipboard,
  FindAndReplace,
  RemoveFormat,
  Heading,
  Paragraph,
  Bold,
  Italic,
  Strikethrough,
  BlockQuote,
  Code,
  CodeBlock,
  AutoLink,
  Link,
  List,
  SourceEditing
]

const ckToolbar = [
  'undo',
  'redo',
  'removeFormat',
  '|',
  'heading',
  '|',
  'bold',
  'italic',
  'strikethrough',
  'link',
  'code',
  '|',
  'codeBlock',
  'blockQuote',
  '|',
  'bulletedList',
  'numberedList',
  '|',
  'sourceEditing',
  'fullscreen'
]

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
      destroyed: false,
      editor: markRaw(ClassicEditor),
      lastError: null,
      visible: false,
      translations: undefined
    }
  },

  async created() {
    const locale = this.$vuetify.locale.current

    if (!translationCache[locale]) {
      const keys = Object.keys(translationCache)

      if (keys.length >= TRANSLATION_CACHE_MAX) {
        delete translationCache[keys[0]]
      }

      const mod = await import(`ckeditor5/translations/${locale}`)

      translationCache[locale] = [mod.default]
    }

    if (this.destroyed) return

    this.translations = translationCache[locale]
  },

  beforeUnmount() {
    this.destroyed = true
    this.visible = false // avoid CKEditor DOM issues
  },

  computed: {
    ckconfig() {
      return markRaw({
        licenseKey: 'GPL',
        plugins: ckPlugins,
        toolbar: ckToolbar,
        translations: this.translations,
        language: {
          ui: this.$vuetify.locale.current
        }
      })
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
      if (!this.destroyed) {
        this.visible = isVisible
      }
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
        const hasError = !this.rules.every((rule) => rule(val ?? this.config.default ?? '') === true)
        if (hasError !== this.lastError) {
          this.lastError = hasError
          this.$emit('error', hasError)
        }
      }
    }
  }
}
</script>

<template>
  <div v-visible="show">
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
