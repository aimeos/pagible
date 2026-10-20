/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import { useLanguageStore, useMessageStore, useUserStore } from '../stores'

const FETCH_STATES = gql`
  query {
    pageTranslationStates(cached: true) {
      lang
      stale
      missing
      ai
    }
  }
`

/**
 * Selector for the language of the page tree which shows the translation state of each language.
 */
export default {
  props: {
    modelValue: { type: String, default: '' }
  },

  emits: ['update:modelValue'],

  setup() {
    const languages = useLanguageStore()
    const messages = useMessageStore()
    const user = useUserStore()

    return { languages, messages, user }
  },

  data() {
    return {
      // translation state counts per language
      states: {},
      // number of the latest request, older responses are ignored
      seq: 0
    }
  },

  created() {
    this.destroyed = false
  },

  beforeUnmount() {
    this.destroyed = true
  },

  computed: {
    items() {
      return this.languages.available.map((code) => ({
        value: code,
        title: this.languages.translate(code),
        props: { subtitle: this.describe(this.states[code]) }
      }))
    }
  },

  methods: {
    // summary of the translation state of a language, e.g. "Needs update: 3 · Missing: 1"
    describe(state) {
      if (!state) return undefined

      const parts = [
        [this.$gettext('Needs update'), state.stale],
        [this.$gettext('Missing'), state.missing],
        [this.$gettext('AI draft'), state.ai]
      ].filter(([, num]) => num > 0)

      return parts.length ? parts.map(([label, num]) => `${label}: ${num}`).join(' · ') : this.$gettext('Up to date')
    },

    // loads the translation state counts of all languages when the selector opens, up to a minute old
    fetchStates() {
      if (!this.user.can('page:view')) return

      const seq = ++this.seq

      return this.$apollo
        .query({ query: FETCH_STATES, fetchPolicy: 'no-cache' })
        .then((result) => {
          if (result.errors) throw result
          if (this.destroyed || seq !== this.seq) return

          this.states = Object.fromEntries(
            (result.data?.pageTranslationStates || []).map((state) => [state.lang, state])
          )
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error fetching translation states'), error)
        })
    }
  }
}
</script>

<template>
  <v-select
    v-if="items.length > 1"
    :model-value="modelValue"
    :items="items"
    :label="$gettext('Language')"
    @update:model-value="$emit('update:modelValue', $event)"
    @update:menu="(open) => open && fetchStates()"
    class="lang-select"
    variant="underlined"
    hide-details
  />
</template>
