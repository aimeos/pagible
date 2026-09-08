/** @license MIT, https://opensource.org/license/mit */

<script>
import {
  mdiPlaylistCheck,
  mdiTranslate,
  mdiClose,
  mdiMenu,
  mdiChevronRight,
  mdiChevronLeft,
  mdiPublish,
  mdiClockOutline,
  mdiPencil,
  mdiDeleteOff,
  mdiDelete,
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
import FileListItems from '../components/FileListItems.vue'
import ChatDialog from '../components/ChatDialog.vue'
import { useUserStore, useDrawerStore, useMessageStore } from '../stores'
import { languageFilter } from '../utils'

export default {
  name: 'FileList',

  components: {
    FileListItems,
    Navigation,
    AsideList,
    ChatDialog,
    User
  },

  data() {
    const defaults = {
      trashed: 'WITHOUT',
      publish: null,
      editor: null,
      lang: null
    }

    return {
      chat: '',
      chatOpen: false,
      chatPending: false,
      audio: null,
      dictating: false,
      help: false,
      scrollTop: 0,
      defaults: defaults,
      filter: { ...defaults, ...this.user?.getData('file', 'filter') }
    }
  },

  watch: {
    filter: {
      deep: true,
      handler(val) {
        this.user.saveData('file', 'filter', val)
      }
    },

    chatOpen(val) {
      if (!val && this.chatPending) {
        this.chatPending = false
        this.$refs.filelist?.reload()
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
      mdiPublish,
      mdiClockOutline,
      mdiPencil,
      mdiDeleteOff,
      mdiDelete,
      mdiAccount,
      mdiHelpCircleOutline,
      mdiArrowRightCircle,
      mdiMicrophone,
      mdiMicrophoneOutline,
      languageFilter
    }
  },

  activated() {
    this.$nextTick(() => {
      this.$refs.scroll.$el.scrollTop = this.scrollTop
    })
  },

  beforeRouteLeave() {
    this.scrollTop = this.$refs.scroll.$el.scrollTop
  },

  beforeUnmount() {
    this.user.flush()
  },

  computed: {
    asideContent() {
      return [
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
      if (this.chatOpen) {
        this.chatPending = true
      } else {
        // A stopped stream can finish after the dialog has already closed.
        this.$refs.filelist?.reload()
      }
    },

    onEnter(e) {
      if (e.isComposing || e.shiftKey) {
        return
      }
      e.preventDefault()
      this.openChat()
    },

    open(item) {
      this.$router.push({ name: 'file:detail', params: { id: item.id } })
    },

    openChat() {
      if (!this.user.can('file:chat')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      const prompt = (this.chat || '').trim()
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
      ><h1>{{ $gettext('Media') }}</h1></v-app-bar-title
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

  <v-main class="file-list" :aria-label="$gettext('Media')">
    <v-container>
      <v-sheet ref="scroll" class="box scroll">
        <v-textarea
          v-if="user.can('file:chat')"
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
              :title="help ? $gettext('Hide help') : $gettext('Show help')"
              :aria-expanded="help"
              aria-controls="file-help"
              variant="text"
            />
          </template>
          <template #append>
            <v-btn
              v-if="chat"
              @click="openChat()"
              :icon="mdiArrowRightCircle"
              :title="$gettext('Send')"
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
        <div v-if="help && user.can('file:chat')" id="file-help" class="help">
          <ul :aria-label="$gettext('Help')">
            <li>{{ $gettext('AI can find and manage media files based on your input') }}</li>
            <li>{{ $gettext('Press Enter or the arrow to open the AI assistant and refine in a chat') }}</li>
          </ul>
        </div>

        <FileListItems ref="filelist" @select="open($event)" :filter="filter" />
      </v-sheet>
    </v-container>
  </v-main>

  <AsideList
    v-model:filter="filter"
    :defaults="defaults"
    :content="asideContent"
  />

  <ChatDialog
    ref="chat"
    v-model="chatOpen"
    permission="file:chat"
    context="The user is viewing the media file list. Focus on finding and managing files using the file tools unless the user explicitly asks for another task."
    @done="chatDone"
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
