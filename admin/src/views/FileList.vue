<script>
  import gql from 'graphql-tag'
  import User from '../components/User.vue'
  import FileDetail from '../views//FileDetail.vue'
  import AsideList from '../components/AsideList.vue'
  import Navigation from '../components/Navigation.vue'
  import FileListItems from '../components/FileListItems.vue'
  import { useAuthStore, useDrawerStore } from '../stores'

  export default {
    components: {
      FileListItems,
      FileDetail,
      Navigation,
      AsideList,
      User
    },

    inject: ['locales', 'openView'],

    data: () => ({
      filter: {
        trashed: 'WITHOUT',
        publish: null,
        editor: null,
        lang: null,
      },
    }),

    setup() {
      const drawer = useDrawerStore()
      const auth = useAuthStore()

      return { auth, drawer }
    },

    methods: {
      languages() {
        const list = [{
          title: this.$gettext('All'),
          icon: 'mdi-playlist-check',
          value: {lang: null}
        }]

        for(const entry of this.locales()) {
          list.push({
            title: entry.title,
            icon: 'mdi-translate',
            value: {lang: entry.value} }
          )
        }

        return list
      },


      open(item) {
        this.openView(FileDetail, {item: item})
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
        :icon="drawer.nav ? 'mdi-close' : 'mdi-menu'"
      />
    </template>

    <v-app-bar-title>{{ $gettext('Files') }}</v-app-bar-title>

    <template #append>
      <User />

      <v-btn
        @click="drawer.toggle('aside')"
        :title="$gettext('Toggle side menu')"
        :icon="drawer.aside ? 'mdi-chevron-right' : 'mdi-chevron-left'"
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

  <AsideList v-model:filter="filter" :content="[{
      key: 'publish',
      title: $gettext('publish'),
      items: [
        { title: $gettext('All'), icon: 'mdi-playlist-check', value: {'publish': null} },
        { title: $gettext('Published'), icon: 'mdi-publish', value: {'publish': 'PUBLISHED'} },
        { title: $gettext('Scheduled'), icon: 'mdi-clock-outline', value: {'publish': 'SCHEDULED'} },
        { title: $gettext('Drafts'), icon: 'mdi-pencil', value: {'publish': 'DRAFT'} }
      ]
    }, {
      key: 'trashed',
      title: $gettext('trashed'),
      items: [
        { title: $gettext('All'), icon: 'mdi-playlist-check', value: {'trashed': 'WITH'} },
        { title: $gettext('Available only'), icon: 'mdi-delete-off', value: {'trashed': 'WITHOUT'} },
        { title: $gettext('Only trashed'), icon: 'mdi-delete', value: {'trashed': 'ONLY'} }
      ]
    }, {
      key: 'editor',
      title: $gettext('editor'),
      items: [
        { title: $gettext('All'), icon: 'mdi-playlist-check', value: {'editor': null} },
        { title: $gettext('Edited by me'), icon: 'mdi-account', value: {'editor': this.auth.me?.email} },
      ]
    }, {
      key: 'lang',
      title: $gettext('languages'),
      items: languages()
    }]"
  />
</template>

<style scoped>
  .v-main {
    overflow-y: auto;
  }
</style>
