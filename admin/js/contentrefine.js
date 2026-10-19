/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { safeParse } from './json'

// how long the success state of the last refinement is shown in milliseconds
const REFINED_DELAY = 3000

// compares content elements independent of the key order and without the UI state keys like "_changed"
function stable(value) {
  if (Array.isArray(value)) {
    return '[' + value.map(stable).join(',') + ']'
  }

  if (value && typeof value === 'object') {
    return '{' + Object.keys(value)
      .filter((key) => !key.startsWith('_') && value[key] !== undefined)
      .sort()
      .map((key) => JSON.stringify(key) + ':' + stable(value[key]))
      .join(',') + '}'
  }

  return JSON.stringify(value ?? null)
}

const REFINE_CONTENT = gql`
  mutation ($prompt: String!, $content: JSON!, $type: String, $context: String, $lang: String, $pagetype: String) {
    refine(prompt: $prompt, content: $content, type: $type, context: $context, lang: $lang, pagetype: $pagetype)
  }
`

/**
 * Lets the AI rewrite the content elements of a page section according to a prompt
 *
 * Emits "update:content".
 * Requires the item, content and section props and the user and messages properties.
 */
export const contentRefine = {
  emits: ['update:content'],

  data: () => ({
    chat: '',
    // the prompt is processed
    refining: false,
    // the last prompt was processed successfully, reset after REFINED_DELAY
    refined: false,
    refinedTimer: null
  }),

  beforeUnmount() {
    clearTimeout(this.refinedTimer)
  },

  methods: {
    refine() {
      if (!this.user.can('page:refine')) return this.messages.denied()

      const prompt = this.chat.trim()

      if (!prompt || this.refining) {
        return
      }

      clearTimeout(this.refinedTimer)
      this.refined = false
      this.refining = true

      // state of the elements when the request started to detect edits made in the meantime
      const sent = new Map(this.content.map((item) => [item.id, stable(item)]))

      return this.$apollo
        .mutate({
          mutation: REFINE_CONTENT,
          variables: {
            prompt: prompt,
            content: JSON.stringify(this.content),
            type: 'content',
            context: null,
            lang: this.item.lang,
            pagetype: this.item.type
          }
        })
        .then((result) => {
          const refined = safeParse(result.data?.refine || '[]', [])

          if (refined.length) {
            const current = new Map(this.content.map((item) => [item.id, item]))
            // elements edited, added or removed during the request aren't overwritten
            let kept = false

            const content = refined.flatMap((item) => {
              if (sent.has(item.id)) {
                if (!current.has(item.id)) {
                  kept = true
                  return []
                }

                if (sent.get(item.id) !== stable(current.get(item.id))) {
                  kept = true
                  return [current.get(item.id)]
                }
              }

              item.group = this.section

              if (stable(item) !== (sent.get(item.id) ?? stable({}))) {
                item._changed = true
              }

              return [item]
            })

            for (const [id, item] of current) {
              if (!sent.has(id)) {
                kept = true
                content.push(item)
              }
            }

            this.$emit('update:content', content)

            if (kept) {
              this.messages.add(this.$gettext('Elements changed during the refinement were kept unchanged'), 'info')
            }
          }

          this.refined = true
          this.refinedTimer = setTimeout(() => (this.refined = false), REFINED_DELAY)
          this.chat = ''
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error refining content'), error)
        })
        .finally(() => {
          this.refining = false
        })
    }
  }
}
