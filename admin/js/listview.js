/**
 * @license MIT, https://opensource.org/license/mit
 */

import {
  mdiAccount,
  mdiArrowRightCircle,
  mdiChevronLeft,
  mdiChevronRight,
  mdiClockOutline,
  mdiClose,
  mdiDelete,
  mdiDeleteOff,
  mdiHelpCircleOutline,
  mdiMenu,
  mdiMicrophone,
  mdiMicrophoneOutline,
  mdiPencil,
  mdiPlaylistCheck,
  mdiPublish,
  mdiTranslate
} from '@mdi/js'
import AsideList from './components/AsideList.vue'
import ChatDialog from './components/ChatDialog.vue'
import Navigation from './components/Navigation.vue'
import User from './components/User.vue'
import { useDrawerStore, useMessageStore, useUserStore } from './stores'
import { dictate, languageFilter } from './utils'

/**
 * Returns the setup bindings of the page, element and file list views
 */
export function useListView(type) {
  return {
    type,
    drawer: useDrawerStore(),
    messages: useMessageStore(),
    user: useUserStore(),
    mdiArrowRightCircle,
    mdiChevronLeft,
    mdiChevronRight,
    mdiClose,
    mdiHelpCircleOutline,
    mdiMenu,
    mdiMicrophone,
    mdiMicrophoneOutline
  }
}

/**
 * Shared options of the page, element and file list views, used with "extends"
 * The views return useListView() from setup(), add "defaults" and "filter" to data()
 * and use "{type}list" as ref name for the list component
 */
export const listViewBase = {
  components: {
    AsideList,
    ChatDialog,
    Navigation,
    User
  },

  data() {
    return {
      audio: null,
      chat: '',
      chatOpen: false,
      chatPending: false,
      dictating: false,
      help: false,
      scrollTop: 0
    }
  },

  activated() {
    this.$nextTick(() => {
      this.$refs.scroll.$el.scrollTop = this.scrollTop
    })
  },

  // referenced by the views because vue-router ignores guards in "extends"
  beforeRouteLeave() {
    this.scrollTop = this.$refs.scroll.$el.scrollTop
  },

  beforeUnmount() {
    this.user.flush()
  },

  computed: {
    asideContent() {
      const aside = this.aside()
      return [aside.publish, aside.trashed, aside.editor, aside.lang]
    }
  },

  methods: {
    // filter sections shared by all lists, the views arrange them in asideContent
    aside() {
      return {
        publish: {
          key: 'publish',
          title: this.$gettext('publish'),
          items: [
            { title: this.$gettext('All'), icon: mdiPlaylistCheck, value: { publish: null } },
            { title: this.$gettext('Published'), icon: mdiPublish, value: { publish: 'PUBLISHED' } },
            { title: this.$gettext('Scheduled'), icon: mdiClockOutline, value: { publish: 'SCHEDULED' } },
            { title: this.$gettext('Drafts'), icon: mdiPencil, value: { publish: 'DRAFT' } }
          ]
        },
        trashed: {
          key: 'trashed',
          title: this.$gettext('trashed'),
          items: [
            { title: this.$gettext('All'), icon: mdiPlaylistCheck, value: { trashed: 'WITH' } },
            { title: this.$gettext('Available only'), icon: mdiDeleteOff, value: { trashed: 'WITHOUT' } },
            { title: this.$gettext('Only trashed'), icon: mdiDelete, value: { trashed: 'ONLY' } }
          ]
        },
        editor: {
          key: 'editor',
          title: this.$gettext('editor'),
          items: [
            { title: this.$gettext('All'), icon: mdiPlaylistCheck, value: { editor: null } },
            { title: this.$gettext('Edited by me'), icon: mdiAccount, value: { editor: this.user.me?.email } }
          ]
        },
        lang: {
          key: 'lang',
          title: this.$gettext('languages'),
          items: languageFilter(mdiPlaylistCheck, mdiTranslate)
        }
      }
    },

    chatDone() {
      if (this.chatOpen) {
        this.chatPending = true // a turn completed; reload the list when the dialog closes
      } else {
        // a stopped stream can finish after the dialog has already closed
        this.$refs[this.type + 'list']?.reload()
      }
    },

    onEnter(e) {
      if (e.isComposing || e.shiftKey) {
        return // let IME compose, and Shift+Enter insert a newline instead of opening the chat
      }
      e.preventDefault()
      this.openChat()
    },

    open(item) {
      this.$router.push({ name: this.type + ':detail', params: { id: item.id } })
    },

    openChat() {
      if (!this.user.can(this.type + ':chat')) return this.messages.denied()

      const prompt = (this.chat || '').trim()
      this.chatOpen = true

      if (prompt) {
        this.chat = ''
        this.$nextTick(() => this.$refs.chat?.send(prompt))
      }
    },

    record() {
      this.audio = dictate(this.audio, (busy) => (this.dictating = busy), (text) => (this.chat = text))
    }
  },

  watch: {
    // refresh the list once when the chat closes after a turn (it may have changed items) and
    // reload the current filter rather than overwriting the editor's saved filter
    chatOpen(val) {
      if (!val && this.chatPending) {
        this.chatPending = false
        this.$refs[this.type + 'list']?.reload()
      }
    }
  }
}
