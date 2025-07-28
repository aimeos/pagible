<script>
  import gql from 'graphql-tag'
  import User from '../components/User.vue'
  import PageDetail from '../views//PageDetail.vue'
  import AsideList from '../components/AsideList.vue'
  import Navigation from '../components/Navigation.vue'
  import PageListItems from '../components/PageListItems.vue'
  import { useAuthStore, useDrawerStore, useMessageStore } from '../stores'

  export default {
    components: {
      PageListItems,
      PageDetail,
      Navigation,
      AsideList,
      User
    },

    inject: ['locales', 'openView'],

    data: () => ({
      chat: '',
      response: '',
      short: true,
      sending: false,
      sendicon: 'mdi-send',
      filter: {
        view: 'tree',
        trashed: 'WITHOUT',
        publish: null,
        status: null,
        editor: null,
        cache: null,
        lang: null,
      },
    }),

    setup() {
      const messages = useMessageStore()
      const drawer = useDrawerStore()
      const auth = useAuthStore()

      return { auth, drawer, messages }
    },

    computed: {
      message() {
        if(!this.response) {
          return this.chat
        }

        const idx = this.response.indexOf(`\n---\n`)

        return this.short
          ? this.$pgettext('ai', this.response.slice(0, idx))
          : this.response.substring(idx > 0 ? idx + 5 : 0)
      },
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
        this.openView(PageDetail, {item: item})
      },


      same(item1, item2) {
        if(!item1 || !item2) {
          return false
        }

        const keys1 = Object.keys(item1);
        const keys2 = Object.keys(item2);

        return keys1.length === keys2.length && keys1.every(key => item1[key] === item2[key])
      },


      send() {
        const prompt = this.chat.trim()

        if(!this.chat) {
          return
        }

        this.sending = true
          this.sendicon = 'mdi-power-off'

        this.$apollo.mutate({
          mutation: gql`mutation($prompt: String!) {
            manage(prompt: $prompt)
          }`,
          variables: {
            prompt: prompt
          }
        }).then(result => {
          if(result.errors) {
            throw result
          }

          this.sendicon = 'mdi-check'
          this.response = result.data?.manage || ''
          this.chat = this.message

          const filter = {
            view: 'list',
            publish: 'DRAFT',
            trashed: 'WITHOUT',
            editor: this.auth.me?.email,
            cache: null,
            lang: null,
            status: 0,
          }

          // compare current filter to check reload is required
          if(this.same(filter, this.filter)) {
            this.$refs.pagelist.reload()
          } else {
            this.filter = filter
          }
        }).catch(error => {
          this.messages.add(this.$gettext('Error sending request'), 'error')
          this.$log(`PageList::send(): Error sending request`, error)
        }).finally(() => {
          this.sending = false
          setTimeout(() => {
            this.sendicon = 'mdi-send'
          }, 3000)
        })
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

    <v-app-bar-title>{{ $gettext('Pages') }} </v-app-bar-title>

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

  <v-main class="page-list">
    <v-container>
      <v-sheet class="box">
        <v-textarea
          v-model="chat"
          :loading="sending"
          :append-icon="sendicon"
          :placeholder="$gettext('What kind of page and content should I create?')"
          @dblclick="short = !short; chat = message"
          @click:append="sending || send()"
          variant="outlined"
          rounded="lg"
          hide-details
          autofocus
          auto-grow
          clearable
          outlined
          rows="1"
        ></v-textarea>
      </v-sheet>

      <v-sheet class="box">
        <PageListItems ref="pagelist" @select="open($event)" :filter="filter" />
      </v-sheet>
    </v-container>
  </v-main>

  <AsideList v-model:filter="filter" :content="[{
      key: 'view',
      title: $gettext('view'),
      items: [
        { title: $gettext('Tree'), icon: 'mdi-file-tree', value: {'view': 'tree'} },
        { title: $gettext('List'), icon: 'mdi-format-list-bulleted-square', value: {'view': 'list'} },
      ]
    }, {
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
      key: 'status',
      title: $gettext('status'),
      items: [
        { title: $gettext('All'), icon: 'mdi-playlist-check', value: {'status': null} },
        { title: $gettext('Enabled'), icon: 'mdi-eye', value: {'status': 1} },
        { title: $gettext('Hidden'), icon: 'mdi-eye-off-outline', value: {'status': 2} },
        { title: $gettext('Disabled'), icon: 'mdi-eye-off', value: {'status': 0} }
      ]
    }, {
      key: 'cache',
      title: $gettext('cache'),
      items: [
        { title: $gettext('All'), icon: 'mdi-playlist-check', value: {'cache': null} },
        { title: $gettext('No cache'), icon: 'mdi-clock-alert-outline', value: {'cache': 0} }
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
