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
      filter: this.settings.filter('element', defaults)
    }
  },

  setup() {
    return useListView('element')
  }
}
</script>

<template>
  <ListAppBar :title="$gettext('Shared elements')" />

  <Navigation />

  <v-main class="element-list" :aria-label="$gettext('Elements')">
    <v-container>
      <v-sheet ref="scroll" class="box scroll">
        <AiPrompt v-if="user.can('element:chat')" v-model="chat" type="element" @submit="openChat()">
          <li>{{ $gettext('AI can find and manage shared elements based on your input') }}</li>
          <li>{{ $gettext('Press Enter or the arrow to open the AI assistant and refine in a chat') }}</li>
        </AiPrompt>

        <ElementListItems ref="elementlist" :filter="filter" @update:filter="Object.assign(filter, $event)" :defaults="defaults" @select="open($event)" />
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
    permission="element:chat"
    context="The user is viewing the shared element list. Focus on finding and managing shared elements using the element tools unless the user explicitly asks for another task."
    @done="chatDone"
  />
</template>

<style scoped>
.v-main {
  overflow-y: auto;
}
</style>
