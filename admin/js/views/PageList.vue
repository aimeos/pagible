/** @license MIT, https://opensource.org/license/mit */

<script>
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
  mdiArrowRightCircle,
  mdiMicrophone,
  mdiMicrophoneOutline
} from '@mdi/js'
import { markRaw } from 'vue'
import User from '../components/User.vue'
import AsideList from '../components/AsideList.vue'
import Navigation from '../components/Navigation.vue'
import PageListItems from '../components/PageListItems.vue'
import ChatDialog from '../components/ChatDialog.vue'
import { useUserStore, useDrawerStore, useMessageStore } from '../stores'
import { languageFilter } from '../utils'

export default {
  name: 'PageList',

  components: {
    PageListItems,
    Navigation,
    AsideList,
    ChatDialog,
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
      chatOpen: false,
      chatPending: false,
      audio: null,
      help: false,
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
    },

    chatOpen(val) {
      // Refresh the list once when the chat closes after a turn (it may have created/changed pages).
      // Reload the current filter rather than overwriting the editor's saved filter.
      if (!val && this.chatPending) {
        this.chatPending = false
        this.$refs.pagelist?.reload()
      }
    }
  },

  setup() {
    const messages = useMessageStore()
    const drawer = useDrawerStore()
    const user = useUserStore()

    return {
      user,
      drawer,
      messages,
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
    }
  },

  methods: {
    chatDone() {
      this.chatPending = true // a turn completed; reload the list when the dialog closes
    },

    onEnter(e) {
      if (e.isComposing || e.shiftKey) {
        return // let IME compose, and Shift+Enter insert a newline instead of opening the chat
      }
      e.preventDefault()
      this.openChat()
    },

    open(item) {
      this.$router.push({ name: 'page:detail', params: { id: item.id } })
    },

    openChat() {
      if (!this.user.can('page:chat')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      const prompt = this.chat.trim()
      this.chatOpen = true

      if (prompt) {
        this.chat = ''
        this.$nextTick(() => this.$refs.chat?.send(prompt))
      }
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
          v-if="user.can('page:chat')"
          v-model="chat"
          :placeholder="$gettext('Describe the page and content you want to create')"
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

        <PageListItems ref="pagelist" @select="open($event)" :filter="filter" />
      </v-sheet>
    </v-container>
  </v-main>

  <AsideList
    v-model:filter="filter"
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

.help {
  color: rgb(var(--v-theme-on-surface));
  background-color: rgb(var(--v-theme-surface-light));
  padding: 16px 24px 16px 32px;
  margin-bottom: 16px;
  border-radius: 8px;
}
</style>
