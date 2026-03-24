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
import { Ckeditor } from '@ckeditor/ckeditor5-vue'
import 'ckeditor5/ckeditor5.css'

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
      editor: ClassicEditor,
      visible: false,
      translations: undefined
    }
  },

  async created() {
    const locale = this.$vuetify.locale.current
    const mod = await import(`../../node_modules/ckeditor5/dist/translations/${locale}.js`)
    if (this.destroyed) return
    this.translations = [mod.default]
  },

  beforeUnmount() {
    this.destroyed = true
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
        ],
        toolbar: [
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
        ],
        translations: this.translations,
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
