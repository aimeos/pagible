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

const FETCH_TRANSLATIONS = gql`
  query ($lang: String!) {
    pageTranslations(lang: $lang) {
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
      filter: this.user.filter('page', defaults),
      translations: null,
      // translation counts must be fetched again when the aside opens
      outdated: false
    }
  },

  setup() {
    return { ...useListView('page'), changes: useChangeStore(), languages: useLanguageStore() }
  },

  created() {
    // collapses bursts of changes into one query of the translation counts
    this.fetchTranslations = debounce(this.queryTranslations, 400)
    this.changed()
  },

  beforeUnmount() {
    this.fetchTranslations.cancel()
  },

  // counts are only fetched again if pages were changed while the cached list view was inactive
  activated() {
    this.inactive = false
    this.outdated && this.drawer.aside && this.fetchTranslations()
  },

  deactivated() {
    this.inactive = true
    this.fetchTranslations.cancel()
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
      return this.user.getData('page', 'lang', this.languages.default())
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
      list.length && this.changed()
    }
  },

  methods: {
    // counts the pages in each translation state of the current language for the filter
    queryTranslations() {
      if (this.languages.available.length < 2 || !this.user.can('page:view')) {
        return
      }

      const lang = this.lang
      this.outdated = false

      return this.$apollo
        .query({
          query: FETCH_TRANSLATIONS,
          variables: { lang },
          fetchPolicy: 'no-cache'
        })
        .then((result) => {
          if (lang === this.lang) {
            this.translations = result.data?.pageTranslations || null
          }
        })
        .catch((error) => {
          this.outdated = true
          this.messages.error(this.$gettext('Error fetching translation states'), error)
        })
    },

    // translation states changed, the counts are only fetched when they are visible; they stay
    // outdated until the query runs, so a fetch cancelled by deactivating is repeated afterwards
    changed() {
      this.outdated = true

      if (this.drawer.aside && !this.inactive) {
        this.fetchTranslations()
      }
    },

    // opens the page in the shown language, the editor offers to create missing ones
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
  <v-app-bar :elevation="0" density="compact" role="sectionheader" :aria-label="$gettext('Menu')">
    <template #prepend>
      <v-btn
        @click="drawer.toggle('nav')"
        :title="drawer.nav ? $gettext('Close navigation') : $gettext('Open navigation')"
        :icon="drawer.nav ? mdiClose : mdiMenu"
      />
    </template>

    <v-app-bar-title
      ><h1>{{ $gettext('Pages') }}</h1></v-app-bar-title
    >

    <template #append>
      <User />

      <v-btn
        @click="drawer.toggle('aside')"
        :title="$gettext('Toggle side menu')"
        :icon="drawer.aside ? mdiChevronRight : mdiChevronLeft"
        class="btn-sidemenu"
      />
    </template>
  </v-app-bar>

  <Navigation />

  <v-main class="page-list" :aria-label="$gettext('Pages')">
    <v-container>
      <v-sheet ref="scroll" class="box scroll">
        <v-textarea
          v-if="user.can('page:chat')"
          v-model="chat"
          :placeholder="$gettext('What shall I do for you?') + ' ' + $gettext('Press Enter to open the chat')"
          @keydown.enter="onEnter"
          variant="outlined"
          class="prompt"
          rounded="lg"
          hide-details
          auto-grow
          clearable
          rows="1"
        >
          <template #prepend>
            <v-btn
              @click="help = !help"
              :icon="mdiHelpCircleOutline"
              class="no-rtl"
              :title="help ? $gettext('Hide help') : $gettext('Show help')"
              :aria-expanded="help"
              aria-controls="page-help"
              variant="text"
            />
          </template>
          <template #append>
            <v-btn
              v-if="chat"
              @click="openChat()"
              :icon="mdiArrowRightCircle"
              :title="$gettext('Generate page based on prompt')"
              variant="text"
            />
            <v-btn
              v-else-if="user.can('audio:transcribe')"
              @click="record()"
              :icon="audio ? mdiMicrophoneOutline : mdiMicrophone"
              :title="$gettext('Dictate')"
              :class="{ dictating: audio }"
              :loading="dictating"
              variant="text"
            />
          </template>
        </v-textarea>
        <div v-if="help" id="page-help" class="help">
          <ul :aria-label="$gettext('Help')">
            <li>
              {{
                $gettext(
                  'AI can create a page and content based on your input and add it to the page tree'
                )
              }}
            </li>
            <li>
              {{
                $gettext('Press Enter or the arrow to open the AI assistant and refine in a chat')
              }}
            </li>
          </ul>
        </div>

        <PageListItems
          ref="pagelist"
          @select="open($event)"
          @changed="changed()"
          :filter="filter"
          :defaults="defaults"
        />
      </v-sheet>
    </v-container>
  </v-main>

  <AsideList
    :filter="filter"
    :defaults="defaults"
    :content="asideContent"
  />

  <ChatDialog ref="chat" v-model="chatOpen" @done="chatDone" />
</template>

<style scoped>
.v-main {
  overflow-y: auto;
}

.prompt {
  margin-bottom: 16px;
}

.v-input--horizontal :deep(.v-input__prepend),
.v-input--horizontal :deep(.v-input__append) {
  margin: 0;
}
</style>
