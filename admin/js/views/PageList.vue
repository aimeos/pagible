/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import {
  mdiPlaylistCheck,
  mdiTranslate,
  mdiClose,
  mdiMenu,
  mdiChevronRight,
  mdiChevronLeft,
  mdiFileTree,
  mdiFormatListBulletedSquare,
  mdiPublish,
  mdiClockOutline,
  mdiPencil,
  mdiDeleteOff,
  mdiDelete,
  mdiEye,
  mdiEyeOffOutline,
  mdiEyeOff,
  mdiClockAlertOutline,
  mdiAccount,
  mdiHelpCircleOutline,
  mdiCheckBold,
  mdiArrowRightCircle,
  mdiMicrophone,
  mdiMicrophoneOutline
} from '@mdi/js'
import { markRaw } from 'vue'
import User from '../components/User.vue'
import PageDetail from '../views//PageDetail.vue'
import AsideList from '../components/AsideList.vue'
import Navigation from '../components/Navigation.vue'
import PageListItems from '../components/PageListItems.vue'
import { useUserStore, useDrawerStore, useMessageStore, useViewStack } from '../stores'
import { languageFilter } from '../utils'

const SYNTHESIZE = gql`
  mutation ($prompt: String!) {
    synthesize(prompt: $prompt)
  }
`

export default {
  components: {
    PageListItems,
    PageDetail, // eslint-disable-line vue/no-unused-components -- used programmatically via openView()
    Navigation,
    AsideList,
    User
  },

  data() {
    const defaults = {
      view: 'tree',
      trashed: 'WITHOUT',
      publish: null,
      status: null,
      editor: null,
      cache: null,
      lang: null
    }

    return {
      chat: '',
      response: '',
      audio: null,
      help: false,
      shortmsg: true,
      synthesizing: false,
      dictating: false,
      defaults: defaults,
      filter: { ...defaults, ...this.user?.getData('page', 'filter') }
    }
  },

  watch: {
    filter: {
      deep: true,
      handler(val) {
        this.user.saveData('page', 'filter', val)
        this.response = ''
      }
    }
  },

  setup() {
    const viewStack = useViewStack()
    const messages = useMessageStore()
    const drawer = useDrawerStore()
    const user = useUserStore()

    return {
      user,
      drawer,
      messages,
      viewStack,
      mdiPlaylistCheck,
      mdiTranslate,
      mdiClose,
      mdiMenu,
      mdiChevronRight,
      mdiChevronLeft,
      mdiFileTree,
      mdiFormatListBulletedSquare,
      mdiPublish,
      mdiClockOutline,
      mdiPencil,
      mdiDeleteOff,
      mdiDelete,
      mdiEye,
      mdiEyeOffOutline,
      mdiEyeOff,
      mdiClockAlertOutline,
      mdiAccount,
      mdiHelpCircleOutline,
      mdiCheckBold,
      mdiArrowRightCircle,
      mdiMicrophone,
      mdiMicrophoneOutline,
      languageFilter
    }
  },

  beforeUnmount() {
    this.user.flush()
  },

  computed: {
    asideContent() {
      return [
        {
          key: 'view',
          title: this.$gettext('view'),
          items: [
            { title: this.$gettext('Tree'), icon: mdiFileTree, value: { view: 'tree' } },
            { title: this.$gettext('List'), icon: mdiFormatListBulletedSquare, value: { view: 'list' } }
          ]
        },
        {
          key: 'publish',
          title: this.$gettext('publish'),
          items: [
            { title: this.$gettext('All'), icon: mdiPlaylistCheck, value: { publish: null } },
            { title: this.$gettext('Published'), icon: mdiPublish, value: { publish: 'PUBLISHED' } },
            { title: this.$gettext('Scheduled'), icon: mdiClockOutline, value: { publish: 'SCHEDULED' } },
            { title: this.$gettext('Drafts'), icon: mdiPencil, value: { publish: 'DRAFT' } }
          ]
        },
        {
          key: 'trashed',
          title: this.$gettext('trashed'),
          items: [
            { title: this.$gettext('All'), icon: mdiPlaylistCheck, value: { trashed: 'WITH' } },
            { title: this.$gettext('Available only'), icon: mdiDeleteOff, value: { trashed: 'WITHOUT' } },
            { title: this.$gettext('Only trashed'), icon: mdiDelete, value: { trashed: 'ONLY' } }
          ]
        },
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
        {
          key: 'editor',
          title: this.$gettext('editor'),
          items: [
            { title: this.$gettext('All'), icon: mdiPlaylistCheck, value: { editor: null } },
            { title: this.$gettext('Edited by me'), icon: mdiAccount, value: { editor: this.user.me?.email } }
          ]
        },
        {
          key: 'lang',
          title: this.$gettext('languages'),
          items: languageFilter(mdiPlaylistCheck, mdiTranslate)
        }
      ]
    },

    message() {
      if (!this.response) {
        return this.chat
      }

      const idx = this.response.indexOf(`\n---\n`)

      return this.shortmsg
        ? this.$pgettext('ai', this.response.slice(0, idx))
        : this.response.substring(idx > 0 ? idx + 5 : 0)
    }
  },

  methods: {
    open(item) {
      this.viewStack.openView(PageDetail, { item: item })
    },

    record() {
      if (!this.audio) {
        return (this.audio = markRaw(import('../audio').then((mod) => mod.recording().start())))
      }

      this.audio.then((rec) => {
        this.dictating = true
        this.audio = null

        rec.stop()?.then((buffer) => {
          import('../ai')
            .then((mod) => mod.transcribe(buffer))
            .then((transcription) => {
              this.chat = transcription.asText()
            })
            .finally(() => {
              this.dictating = false
            })
        })
      })
    },

    same(item1, item2) {
      if (!item1 || !item2) {
        return false
      }

      const keys1 = Object.keys(item1)
      const keys2 = Object.keys(item2)

      return keys1.length === keys2.length && keys1.every((key) => item1[key] === item2[key])
    },

    synthesize() {
      if (!this.user.can('page:synthesize')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      const prompt = this.chat.trim()

      if (!this.chat) {
        return
      }

      this.synthesizing = true

      this.$apollo
        .mutate({
          mutation: SYNTHESIZE,
          variables: {
            prompt: prompt
          }
        })
        .then((result) => {
          if (result.errors) {
            throw result
          }

          this.response = result.data?.synthesize || ''
          this.chat = this.message

          const filter = {
            view: 'list',
            publish: 'DRAFT',
            trashed: 'WITHOUT',
            editor: this.user.me?.email,
            cache: null,
            lang: null,
            status: 0
          }

          // compare current filter to check reload is required
          if (this.same(filter, this.filter)) {
            this.$refs.pagelist.reload()
          } else {
            this.filter = filter
          }

          this.synthesizing = null
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error synthesizing content') + ':\n' + error, 'error')
          this.$log(`PageDetailContentList::synthesize(): Error synthesizing content`, error)
        })
        .finally(() => {
          setTimeout(() => {
            this.synthesizing = false
          }, 3000)
        })
    },

    toggleChat() {
      this.shortmsg = !this.shortmsg
      this.chat = this.message
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
      <v-sheet class="box scroll">
        <v-textarea
          v-if="user.can('page:synthesize')"
          v-model="chat"
          :loading="synthesizing"
          :placeholder="$gettext('Describe the page and content you want to create')"
          @dblclick="toggleChat"
          variant="outlined"
          class="prompt"
          rounded="lg"
          hide-details
          auto-grow
          clearable
          outlined
          rows="1"
        >
          <template #prepend>
            <v-btn
              @click="help = !help"
              :icon="mdiHelpCircleOutline"
              :title="help ? $gettext('Hide help') : $gettext('Show help')"
              :aria-expanded="help"
              aria-controls="page-help"
              variant="text"
            />
          </template>
          <template #append>
            <v-btn
              v-if="chat"
              @click="synthesizing || synthesize()"
              @keydown.enter="synthesizing || synthesize()"
              :icon="
                synthesizing === false
                  ? mdiArrowRightCircle
                  : synthesizing === null
                    ? mdiCheckBold
                    : null
              "
              :title="
                synthesizing
                  ? $gettext('Generating ...')
                  : $gettext('Generate page based on prompt')
              "
              :loading="synthesizing"
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
                $gettext('Double click on the response in the input field to display full response')
              }}
            </li>
          </ul>
        </div>

        <PageListItems ref="pagelist" @select="open($event)" :filter="filter" />
      </v-sheet>
    </v-container>
  </v-main>

  <AsideList
    v-model:filter="filter"
    :defaults="defaults"
    :content="asideContent"
  />
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

.help {
  color: rgb(var(--v-theme-on-surface));
  background-color: rgb(var(--v-theme-surface-light));
  padding: 16px 24px 16px 32px;
  margin-bottom: 16px;
  border-radius: 8px;
}
</style>
