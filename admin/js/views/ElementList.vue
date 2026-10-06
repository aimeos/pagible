/** @license MIT, https://opensource.org/license/mit */

<script>
import ElementListItems from '../components/ElementListItems.vue'
import { listViewBase, useListView } from '../listview'

export default {
  name: 'ElementList',

  extends: listViewBase,

  // vue-router only reads route guards from the component itself, not from "extends"
  beforeRouteLeave: listViewBase.beforeRouteLeave,

  components: {
    ...listViewBase.components,
    ElementListItems
  },

  data() {
    const defaults = {
      trashed: 'WITHOUT',
      publish: null,
      editor: null,
      lang: null
    }

    return {
      defaults: defaults,
      filter: this.user.filter('element', defaults)
    }
  },

  setup() {
    return useListView('element')
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
      ><h1>{{ $gettext('Shared elements') }}</h1></v-app-bar-title
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

  <v-main class="element-list" :aria-label="$gettext('Elements')">
    <v-container>
      <v-sheet ref="scroll" class="box scroll">
        <v-textarea
          v-if="user.can('element:chat')"
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
              aria-controls="element-help"
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
        <div v-if="help && user.can('element:chat')" id="element-help" class="help">
          <ul :aria-label="$gettext('Help')">
            <li>{{ $gettext('AI can find and manage shared elements based on your input') }}</li>
            <li>{{ $gettext('Press Enter or the arrow to open the AI assistant and refine in a chat') }}</li>
          </ul>
        </div>

        <ElementListItems ref="elementlist" :filter="filter" :defaults="defaults" @select="open($event)" />
      </v-sheet>
    </v-container>
  </v-main>

  <AsideList
    :filter="filter"
    :defaults="defaults"
    :content="asideContent"
  />

  <ChatDialog
    ref="chat"
    v-model="chatOpen"
    permission="element:chat"
    context="The user is viewing the shared element list. Focus on finding and managing shared elements using the element tools unless the user explicitly asks for another task."
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
</style>
