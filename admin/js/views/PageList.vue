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
  mdiMicrophone,
  mdiMicrophoneOutline
} from '@mdi/js'
import User from '../components/User.vue'
import PageDetail from '../views//PageDetail.vue'
import AsideList from '../components/AsideList.vue'
import Navigation from '../components/Navigation.vue'
import PageListItems from '../components/PageListItems.vue'
import { useUserStore, useDrawerStore, useMessageStore, useViewStack } from '../stores'
import { recording } from '../audio'
import { languageFilter } from '../utils'
import { transcribe } from '../ai'

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
      mdiMicrophone,
      mdiMicrophoneOutline,
      languageFilter,
      transcribe
    }
  },

  beforeUnmount() {
    this.user.flush()
  },

  computed: {
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
        return (this.audio = recording().start())
      }

      this.audio.then((rec) => {
        this.dictating = true
        this.audio = null

        rec.stop()?.then((buffer) => {
          this.transcribe(buffer)
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
          mutation: gql`
            mutation ($prompt: String!) {
              synthesize(prompt: $prompt)
            }
          `,
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
    :content="[
      {
        key: 'view',
        title: $gettext('view'),
        items: [
          { title: $gettext('Tree'), icon: mdiFileTree, value: { view: 'tree' } },
          {
            title: $gettext('List'),
            icon: mdiFormatListBulletedSquare,
            value: { view: 'list' }
          }
        ]
      },
      {
        key: 'publish',
        title: $gettext('publish'),
        items: [
          { title: $gettext('All'), icon: mdiPlaylistCheck, value: { publish: null } },
          { title: $gettext('Published'), icon: mdiPublish, value: { publish: 'PUBLISHED' } },
          {
            title: $gettext('Scheduled'),
            icon: mdiClockOutline,
            value: { publish: 'SCHEDULED' }
          },
          { title: $gettext('Drafts'), icon: mdiPencil, value: { publish: 'DRAFT' } }
        ]
      },
      {
        key: 'trashed',
        title: $gettext('trashed'),
        items: [
          { title: $gettext('All'), icon: mdiPlaylistCheck, value: { trashed: 'WITH' } },
          {
            title: $gettext('Available only'),
            icon: mdiDeleteOff,
            value: { trashed: 'WITHOUT' }
          },
          { title: $gettext('Only trashed'), icon: mdiDelete, value: { trashed: 'ONLY' } }
        ]
      },
      {
        key: 'status',
        title: $gettext('status'),
        items: [
          { title: $gettext('All'), icon: mdiPlaylistCheck, value: { status: null } },
          { title: $gettext('Enabled'), icon: mdiEye, value: { status: 1 } },
          { title: $gettext('Hidden'), icon: mdiEyeOffOutline, value: { status: 2 } },
          { title: $gettext('Disabled'), icon: mdiEyeOff, value: { status: 0 } }
        ]
      },
      {
        key: 'cache',
        title: $gettext('cache'),
        items: [
          { title: $gettext('All'), icon: mdiPlaylistCheck, value: { cache: null } },
          { title: $gettext('No cache'), icon: mdiClockAlertOutline, value: { cache: 0 } }
        ]
      },
      {
        key: 'editor',
        title: $gettext('editor'),
        items: [
          { title: $gettext('All'), icon: mdiPlaylistCheck, value: { editor: null } },
          {
            title: $gettext('Edited by me'),
            icon: mdiAccount,
            value: { editor: this.user.me?.email }
          }
        ]
      },
      {
        key: 'lang',
        title: $gettext('languages'),
        items: languageFilter(mdiPlaylistCheck, mdiTranslate)
      }
    ]"
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
