/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { FILE_FIELDS, normalizeFile } from './files'
import { invalidateList } from './graphql'
import { frozenParse } from './json'

const ADD_ELEMENT = gql`
  ${FILE_FIELDS}
  mutation ($input: ElementInput!) {
    addElement(input: $input) {
      id
      type
      lang
      name
      data
      editor
      updated_at
      files {
        ...CmsFileFields
      }
    }
  }
`

/**
 * Turns content elements of a page into shared elements and shared elements back into
 * page content
 *
 * Emits "add-assets", "add-elements" and "update:content".
 * Requires the item, content and elements props, the user and messages properties and the
 * entry() and title() methods.
 */
export const contentShare = {
  emits: ['add-assets', 'add-elements', 'update:content'],

  created() {
    // content elements whose shared element is being created
    this.sharing = new Set()
  },

  methods: {
    share(idx) {
      if (!this.user.can('element:add')) return this.messages.denied()

      const entry = this.content[idx]

      if (!entry) {
        this.messages.add(this.$gettext('Element not found'), 'error')
        return
      }

      if (entry.type === 'reference') {
        this.messages.add(this.$gettext('Element is already shared'), 'error')
        return
      }

      // a second click before the response would create another, orphaned element
      const key = entry.id ?? entry

      if (this.sharing.has(key)) {
        return
      }

      this.sharing.add(key)

      return this.$apollo
        .mutate({
          mutation: ADD_ELEMENT,
          variables: {
            input: {
              type: entry.type,
              lang: this.item.lang,
              name: this.title(entry),
              data: JSON.stringify(entry.data || {})
            }
          }
        })
        .then((result) => {
          const element = result.data.addElement

          const files = (element.files || []).map(normalizeFile)

          element.data = frozenParse(element.data)
          element.files = Object.freeze(files)

          this.$emit('add-assets', Object.fromEntries(files.map((file) => [file.id, file])))
          this.$emit('add-elements', { [element.id]: element })
          invalidateList('elements')

          // the content may have changed while waiting for the response
          // the reference keeps the element ID, so the translations and the preview still find it
          if (this.content.includes(entry)) {
            const ref = { ...this.entry('reference', { refid: element.id }), id: entry.id }
            this.$emit('update:content', this.content.map((el) => (el === entry ? ref : el)))
          }
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Unable to make element shared'), error, idx)
        })
        .finally(() => {
          this.sharing.delete(key)
        })
    },

    unshare(idx) {
      if (!this.content[idx]) {
        this.messages.add(this.$gettext('Content element not found'), 'error')
        return
      }

      const entry = this.content[idx]

      if (entry.type !== 'reference' || !this.elements[entry.refid]) {
        this.messages.add(this.$gettext('Element is not shared'), 'error')
        return
      }

      const element = this.elements[entry.refid]
      const copy = {
        ...entry,
        type: element.type || null,
        data: { ...(element.data || {}) },
        refid: undefined
      }

      this.$emit('add-assets', Object.fromEntries((element.files || []).map((file) => [file.id, file])))
      this.$emit('update:content', this.content.map((el, i) => (i === idx ? copy : el)))
    }
  }
}
