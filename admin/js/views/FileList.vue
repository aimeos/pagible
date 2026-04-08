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
  mdiPublish,
  mdiClockOutline,
  mdiPencil,
  mdiDeleteOff,
  mdiDelete,
  mdiAccount
} from '@mdi/js'
import User from '../components/User.vue'
import FileDetail from '../views//FileDetail.vue'
import AsideList from '../components/AsideList.vue'
import Navigation from '../components/Navigation.vue'
import FileListItems from '../components/FileListItems.vue'
import { useUserStore, useDrawerStore, useViewStack } from '../stores'
import { locales } from '../utils'

export default {
  components: {
    FileListItems,
    FileDetail, // eslint-disable-line vue/no-unused-components -- used programmatically via openView()
    Navigation,
    AsideList,
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
    }
  },

  setup() {
    const viewStack = useViewStack()
    const drawer = useDrawerStore()
    const user = useUserStore()

    return {
      user,
      drawer,
      viewStack,
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
      locales
    }
  },

  beforeUnmount() {
    this.user.flush()
  },

  methods: {
    languages() {
      const list = [
        {
          title: this.$gettext('All'),
          icon: mdiPlaylistCheck,
          value: { lang: null }
        }
      ]

      for (const entry of this.locales()) {
        list.push({
          title: entry.title,
          icon: mdiTranslate,
          value: { lang: entry.value }
        })
      }

      return list
    },

    open(item) {
      this.viewStack.openView(FileDetail, { item: item })
    }
  }
}
</script>

<template>
  <v-app-bar :elevation="0" density="compact">
    <template #prepend>
      <v-btn
        @click="drawer.toggle('nav')"
        :title="drawer.nav ? $gettext('Close navigation') : $gettext('Open navigation')"
        :icon="drawer.nav ? mdiClose : mdiMenu"
      />
    </template>

    <v-app-bar-title
      ><h1>{{ $gettext('Files') }}</h1></v-app-bar-title
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

  <v-main class="file-list">
    <v-container>
      <v-sheet class="box scroll">
        <FileListItems @select="open($event)" :filter="filter" />
      </v-sheet>
    </v-container>
  </v-main>

  <AsideList
    v-model:filter="filter"
    :defaults="defaults"
    :content="[
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
        items: languages()
      }
    ]"
  />
</template>

<style scoped>
.v-main {
  overflow-y: auto;
}
</style>
