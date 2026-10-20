/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import {
  mdiClockAlertOutline,
  mdiEye,
  mdiEyeOff,
  mdiEyeOffOutline,
  mdiFileTree,
  mdiFormatListBulletedSquare,
  mdiPlaylistCheck,
  mdiPlusCircleOutline,
  mdiRobotOutline,
  mdiSync
} from '@mdi/js'
import PageListItems from '../components/PageListItems.vue'
import { listViewBase, useListView } from '../listview'
import { useChangeStore, useLanguageStore } from '../stores'
import { debounce } from '../utils'

// minimum time between counts refreshed due to frequent changes, e.g. finished translations
const THROTTLE = 15000

const FETCH_TRANSLATIONS = gql`
  query ($langs: [String!], $cached: Boolean) {
    pageTranslationStates(langs: $langs, cached: $cached) {
      stale
      missing
      ai
    }
  }
`

export default {
  name: 'PageList',

  extends: listViewBase,

  // vue-router only reads route guards from the component itself, not from "extends"
  beforeRouteLeave: listViewBase.beforeRouteLeave,

  components: {
    ...listViewBase.components,
    PageListItems
  },

  data() {
    const defaults = {
      view: 'tree',
      trashed: 'WITHOUT',
      publish: null,
      status: null,
      editor: null,
      cache: null,
      translation: null
    }

    return {
      defaults: defaults,
      filter: this.settings.filter('page', defaults),
      translations: null,
      // translation counts must be fetched again when the aside opens
      outdated: false,
      // number of the latest translation count query, older responses are ignored
      translationSeq: 0
    }
  },

  setup() {
    return { ...useListView('page'), changes: useChangeStore(), languages: useLanguageStore() }
  },

  created() {
    // collapses bursts of changes into one query of the translation counts
    this.fetchTranslations = debounce(this.queryTranslations, 400)
    // non-reactive state of the translation count queries, not used for rendering
    this.fetchedAt = 0
    this.inactive = false
    this.throttled = null
    this.changed()
  },

  beforeUnmount() {
    this.fetchTranslations.cancel()
    this.translationSeq++
    clearTimeout(this.throttled)
  },

  // counts are only fetched again if pages were changed while the cached list view was inactive
  activated() {
    this.inactive = false
    this.outdated && this.drawer.aside && this.fetchTranslations()
  },

  deactivated() {
    this.inactive = true
    this.fetchTranslations.cancel()
    clearTimeout(this.throttled)
    this.throttled = null
  },

  computed: {
    // pages saved or published in the detail views, the list patches and removes them when activated
    pendingChanges() {
      return this.changes.get('page')
    },

    asideContent() {
      const aside = this.aside()

      return [
        {
          key: 'view',
          title: this.$gettext('view'),
          items: [
            { title: this.$gettext('Tree'), icon: mdiFileTree, value: { view: 'tree' } },
            { title: this.$gettext('List'), icon: mdiFormatListBulletedSquare, value: { view: 'list' } }
          ]
        },
        aside.publish,
        aside.trashed,
        {
          key: 'status',
          title: this.$gettext('status'),
          items: [
            { title: this.$gettext('All'), icon: mdiPlaylistCheck, value: { status: null } },
            { title: this.$gettext('Enabled'), icon: mdiEye, value: { status: 1 } },
            { title: this.$gettext('Hidden'), icon: mdiEyeOffOutline, value: { status: 2 } },
            { title: this.$gettext('Disabled'), icon: mdiEyeOff, value: { status: 0 } }
          ]
        },
        {
          key: 'cache',
          title: this.$gettext('cache'),
          items: [
            { title: this.$gettext('All'), icon: mdiPlaylistCheck, value: { cache: null } },
            { title: this.$gettext('No cache'), icon: mdiClockAlertOutline, value: { cache: 0 } }
          ]
        },
        aside.editor,
        ...(this.languages.available.length > 1
          ? [
              {
                key: 'translation',
                title: this.$gettext('translation'),
                items: [
                  { title: this.$gettext('All'), icon: mdiPlaylistCheck, value: { translation: null } },
                  {
                    title: this.$gettext('Needs update'),
                    icon: mdiSync,
                    count: this.translations?.stale,
                    value: { translation: 'stale' }
                  },
                  {
                    title: this.$gettext('Missing'),
                    icon: mdiPlusCircleOutline,
                    count: this.translations?.missing,
                    value: { translation: 'missing' }
                  },
                  {
                    title: this.$gettext('AI draft'),
                    icon: mdiRobotOutline,
                    count: this.translations?.ai,
                    value: { translation: 'ai' }
                  }
                ]
              }
            ]
          : [])
      ]
    },

    // current language of the page list, chosen in its language selector
    lang() {
      return this.settings.getData('page', 'lang', this.languages.default())
    }
  },

  watch: {
    lang() {
      this.changed()
    },

    'drawer.aside'(open) {
      open && this.outdated && this.fetchTranslations()
    },

    // saving or publishing pages elsewhere can change the translation states of their variants
    pendingChanges(list) {
      list.length && this.changed(true)
    }
  },

  methods: {
    // counts the pages in each translation state of the current language for the filter
    queryTranslations(cached = false) {
      if (this.languages.available.length < 2 || !this.user.can('page:view')) {
        return
      }

      const lang = this.lang
      // only the latest query updates the counts, older responses may arrive later
      const seq = ++this.translationSeq
      this.outdated = false
      this.fetchedAt = Date.now()

      return this.$apollo
        .query({
          query: FETCH_TRANSLATIONS,
          variables: { langs: [lang], cached },
          fetchPolicy: 'no-cache'
        })
        .then((result) => {
          if (seq === this.translationSeq && lang === this.lang) {
            this.translations = result.data?.pageTranslationStates?.[0] || null
          }
        })
        .catch((error) => {
          if (seq !== this.translationSeq) return
          this.outdated = true
          this.messages.error(this.$gettext('Error fetching translation states'), error)
        })
    },

    // translation states changed, the counts are only fetched when they are visible; they stay
    // outdated until the query runs, so a fetch cancelled by deactivating is repeated afterwards.
    // Frequent changes, e.g. by other editors or finished translations, refresh the counts at
    // most every few seconds and may use counts cached by the server
    changed(frequent = false) {
      this.outdated = true

      if (!this.drawer.aside || this.inactive) {
        return
      }

      if (!frequent) {
        clearTimeout(this.throttled)
        this.throttled = null
        this.fetchTranslations()
        return
      }

      if (!this.throttled) {
        const wait = Math.max(400, this.fetchedAt + THROTTLE - Date.now())

        this.throttled = setTimeout(() => {
          this.throttled = null
          this.outdated && this.queryTranslations(true)
        }, wait)
      }
    },

    // opens the page in the shown language, missing variants open the source language and the editor offers to create them
    open(item) {
      this.$router.push({
        name: 'page:detail',
        params: { id: item.id },
        query: item.lang ? { lang: item.lang } : {}
      })
    }
  }
}
</script>

<template>
  <ListAppBar :title="$gettext('Pages')" />

  <Navigation />

  <v-main class="page-list" :aria-label="$gettext('Pages')">
    <v-container>
      <v-sheet ref="scroll" class="box scroll">
        <AiPrompt
          v-if="user.can('page:chat')"
          v-model="chat"
          type="page"
          :send-title="$gettext('Generate page based on prompt')"
          @submit="openChat()"
        >
          <li>{{ $gettext('AI can create a page and content based on your input and add it to the page tree') }}</li>
          <li>{{ $gettext('Press Enter or the arrow to open the AI assistant and refine in a chat') }}</li>
        </AiPrompt>

        <PageListItems
          ref="pagelist"
          @select="open($event)"
          @changed="changed()"
          :filter="filter"
          @update:filter="Object.assign(filter, $event)"
          :defaults="defaults"
        />
      </v-sheet>
    </v-container>
  </v-main>

  <AsideList
    :filter="filter"
    @update:filter="Object.assign(filter, $event)"
    :defaults="defaults"
    :content="asideContent"
  />

  <ChatDialog
    ref="chat"
    v-model="chatOpen"
    context="The user is viewing the page tree. Focus on finding, creating and managing pages using the page tools unless the user explicitly asks for another task."
    @done="chatDone"
  />
</template>

<style scoped>
.v-main {
  overflow-y: auto;
}
</style>
