/** @license MIT, https://opensource.org/license/mit */

<script>
import {
  mdiClockAlertOutline,
  mdiEye,
  mdiEyeOff,
  mdiEyeOffOutline,
  mdiFileTree,
  mdiFormatListBulletedSquare,
  mdiPlaylistCheck
} from '@mdi/js'
import PageListItems from '../components/PageListItems.vue'
import { listViewBase, useListView } from '../listview'

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
      cache: null
    }

    return {
      defaults: defaults,
      filter: this.user.filter('page', defaults)
    }
  },

  setup() {
    return useListView('page')
  },

  computed: {
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
        aside.editor
      ]
    }
  },

  methods: {
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

        <PageListItems ref="pagelist" @select="open($event)" :filter="filter" :defaults="defaults" />
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
