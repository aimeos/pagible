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
      help: false,
      short: true,
      sending: false,
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

          this.sending = null
        }).catch(error => {
          this.messages.add(this.$gettext('Error sending request'), 'error')
          this.$log(`PageList::send(): Error sending request`, error)
        }).finally(() => {
          setTimeout(() => {
            this.sending = false
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
      <v-sheet class="box scroll">
        <v-textarea
          v-model="chat"
          :loading="sending"
          :placeholder="$gettext('Describe the page and content you want to create')"
          @dblclick="short = !short; chat = message"
          variant="outlined"
          class="prompt"
          rounded="lg"
          hide-details
          autofocus
          auto-grow
          clearable
          outlined
          rows="1"
        >
          <template #prepend>
            <v-icon @click="help = !help">
              <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                <path d="M15.07,11.25L14.17,12.17C13.45,12.89 13,13.5 13,15H11V14.5C11,13.39 11.45,12.39 12.17,11.67L13.41,10.41C13.78,10.05 14,9.55 14,9C14,7.89 13.1,7 12,7A2,2 0 0,0 10,9H8A4,4 0 0,1 12,5A4,4 0 0,1 16,9C16,9.88 15.64,10.67 15.07,11.25M13,19H11V17H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12C22,6.47 17.5,2 12,2Z" />
              </svg>
            </v-icon>
          </template>
          <template #append>
            <v-icon @click="sending || send()">
              <svg v-if="sending === false" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2A10,10 0 0,1 22,12M6,13H14L10.5,16.5L11.92,17.92L17.84,12L11.92,6.08L10.5,7.5L14,11H6V13Z" />
              </svg>
              <svg v-if="sending === true" class="spinner" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <circle class="spin1" cx="4" cy="12" r="3"/>
                <circle class="spin1 spin2" cx="12" cy="12" r="3"/>
                <circle class="spin1 spin3" cx="20" cy="12" r="3"/>
              </svg>
              <svg v-if="sending === null" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M9,20.42L2.79,14.21L5.62,11.38L9,14.77L18.88,4.88L21.71,7.71L9,20.42Z" />
              </svg>
            </v-icon>
          </template>
        </v-textarea>
        <div v-if="help" class="help">
          <ul>
            <li>{{ $gettext('AI can create a page and content based on your input and add it to the page tree') }}</li>
            <li>{{ $gettext('Double click on the response in the input field to display full response') }}</li>
          </ul>
        </div>

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

  .prompt {
    margin-bottom: 16px;
  }

  .help {
    color: rgb(var(--v-theme-on-surface));
    background-color: rgb(var(--v-theme-surface-light));
    padding: 16px 24px 16px 32px ;
    margin-bottom: 16px;
    border-radius: 8px;
  }
</style>
