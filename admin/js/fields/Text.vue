/** @license MIT, https://opensource.org/license/mit */

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
  Paragraph,
  Bold,
  Italic,
  Strikethrough,
  Code,
  AutoLink,
  Link
} from 'ckeditor5'
import gql from 'graphql-tag'
import { markRaw } from 'vue'
import { Ckeditor } from '@ckeditor/ckeditor5-vue'
import { minChars, maxChars, required } from '../rules'
import 'ckeditor5/ckeditor5.css'
import { fieldBase } from '../field'

const translationCache = {}
const TRANSLATION_CACHE_MAX = 1

const plugins = [
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
]

const toolbar = [
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
]

/**
 * Configuration:
 * - `hint`: string, description shown below the field while it has focus
 * - `max`: int, maximum number of characters allowed
 * - `min`: int, minimum number of characters required if the field isn't empty
 * - `required`: boolean, if true, the field must not be empty
 *
 * Pages picked from the "Pages" list of the link form are stored as `page:<id>` links
 * which are rendered as URL of the page in the language of the current page.
 */
export default {
  extends: fieldBase,

  components: {
    Ckeditor
  },

  setup() {
    return { plugins, toolbar }
  },

  data() {
    return {
      destroyed: false,
      editor: markRaw(ClassicEditor),
      visible: false,
      translations: undefined,
      linkPages: []
    }
  },

  async created() {
    const locale = this.$vuetify.locale.current

    if (!translationCache[locale]) {
      const keys = Object.keys(translationCache)

      if (keys.length >= TRANSLATION_CACHE_MAX) {
        delete translationCache[keys[0]]
      }

      const mod = await import(`ckeditor5/translations/${locale}.js`)

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
        plugins: this.plugins,
        toolbar: this.toolbar,
        translations: this.translations,
        language: {
          ui: this.$vuetify.locale.current
        },
        link: {
          allowedProtocols: ['https?', 'ftps?', 'mailto', 'tel', 'page']
        },
        extraPlugins: [this.pageLinks()]
      })
    },

    rules() {
      return [
        required(this.$gettext, this.config.required),
        minChars(this.$ngettext, this.config.min),
        maxChars(this.$ngettext, this.config.max)
      ]
    }
  },

  methods: {
    // CKEditor plugin adding the pages in the current language to the link form
    pageLinks() {
      const vm = this

      return class PageLinks {
        constructor(editor) {
          this.editor = editor
        }

        afterInit() {
          if (!this.editor.plugins.has('LinkUI')) return

          vm.loadPages()
          this.editor.plugins.get('LinkUI').registerLinksListProvider({
            label: vm.$gettext('Pages'),
            emptyListPlaceholder: vm.$gettext('No pages found'),
            getListItems: () =>
              vm.linkPages.map((page) => ({
                id: page.id,
                href: `page:${page.id}`,
                label: `${page.name || page.path} (/${page.path || ''})`
              })),
            getItem: (href) => {
              const id = /^page:([A-Za-z0-9-]+)$/.exec(href || '')?.[1]
              const page = id && vm.linkPages.find((item) => item.id === id)

              return id ? { href, label: page ? page.name || page.path : href, tooltip: page ? `/${page.path || ''}` : '' } : null
            },
            // page links have no URL in the editor
            navigate: (item) => /^page:/.test(item.href)
          })
        }
      }
    },

    loadPages() {
      this.$apollo
        .query({
          query: gql`
            query ($lang: String) {
              pages(first: 100, lang: $lang) {
                data {
                  id
                  name
                  path
                }
              }
            }
          `,
          variables: { lang: this.$route?.query?.lang || null }
        })
        .then((result) => {
          if (!this.destroyed) {
            this.linkPages = result.data?.pages?.data || []
          }
        })
        .catch((error) => {
          this.$log('Text::loadPages(): Error fetching pages', error)
        })
    },

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
