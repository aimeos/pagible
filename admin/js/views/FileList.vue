/** @license MIT, https://opensource.org/license/mit */

<script>
import FileListItems from '../components/FileListItems.vue'
import { listViewBase, useListView } from '../listview'

export default {
  name: 'FileList',

  extends: listViewBase,

  // vue-router only reads route guards from the component itself, not from "extends"
  beforeRouteLeave: listViewBase.beforeRouteLeave,

  components: {
    ...listViewBase.components,
    FileListItems
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
      filter: this.settings.filter('file', defaults)
    }
  },

  setup() {
    return useListView('file')
  }
}
</script>

<template>
  <ListAppBar :title="$gettext('Media')" />

  <Navigation />

  <v-main class="file-list" :aria-label="$gettext('Media')">
    <v-container>
      <v-sheet ref="scroll" class="box scroll">
        <AiPrompt v-if="user.can('file:chat')" v-model="chat" type="file" @submit="openChat()">
          <li>{{ $gettext('AI can find and manage media files based on your input') }}</li>
          <li>{{ $gettext('Press Enter or the arrow to open the AI assistant and refine in a chat') }}</li>
        </AiPrompt>

        <FileListItems ref="filelist" @select="open($event)" :filter="filter" @update:filter="Object.assign(filter, $event)" :defaults="defaults" />
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
    permission="file:chat"
    context="The user is viewing the media file list. Focus on finding and managing files using the file tools unless the user explicitly asks for another task."
    @done="chatDone"
  />
</template>

<style scoped>
.v-main {
  overflow-y: auto;
}
</style>
