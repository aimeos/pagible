/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

<script>
  import gql from 'graphql-tag'
  import User from '../components/User.vue'
  import PageDetail from '../views//PageDetail.vue'
  import AsideList from '../components/AsideList.vue'
  import Navigation from '../components/Navigation.vue'
  import PageListItems from '../components/PageListItems.vue'
  import { useAuthStore, useDrawerStore, useMessageStore } from '../stores'
  import { recording } from '../audio'

  export default {
    components: {
      PageListItems,
      PageDetail,
      Navigation,
      AsideList,
      User
    },

    inject: ['locales', 'transcribe', 'openView'],

    data: () => ({
      chat: '',
      response: '',
      audio: null,
      help: false,
      shortmsg: true,
      synthesizing: false,
      dictating: false,
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

        return this.shortmsg
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


      record() {
        if(!this.audio) {
          return this.audio = recording().start()
        }

        this.audio.then(rec => {
          this.dictating = true
          this.audio = null

          rec.stop().then(buffer => {
            this.transcribe(buffer).then(transcription => {
              this.chat = transcription.asText()
            }).finally(() => {
              this.dictating = false
            })
          })
        })
      },


      same(item1, item2) {
        if(!item1 || !item2) {
          return false
        }

        const keys1 = Object.keys(item1);
        const keys2 = Object.keys(item2);

        return keys1.length === keys2.length && keys1.every(key => item1[key] === item2[key])
      },


      synthesize() {
        if(!this.auth.can('page:synthesize')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const prompt = this.chat.trim()

        if(!this.chat) {
          return
        }

        this.synthesizing = true

        this.$apollo.mutate({
          mutation: gql`mutation($prompt: String!) {
            synthesize(prompt: $prompt)
          }`,
          variables: {
            prompt: prompt
          }
        }).then(result => {
          if(result.errors) {
            throw result
          }

          this.response = result.data?.synthesize || ''
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

          this.synthesizing = null
        }).catch(error => {
          this.messages.add(this.$gettext('Error synthesizing content') + ":\n" + error, 'error')
          this.$log(`PageDetailContentList::synthesize(): Error synthesizing content`, error)
        }).finally(() => {
          setTimeout(() => {
            this.synthesizing = false
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
        <v-textarea v-if="auth.can('page:synthesize')"
          v-model="chat"
          :loading="synthesizing"
          :placeholder="$gettext('Describe the page and content you want to create')"
          @dblclick="shortmsg = !shortmsg; chat = message"
          variant="outlined"
          class="prompt"
          rounded="lg"
          hide-details
          auto-grow
          clearable
          outlined
          rows="1"
        >
          <template #prepend>
            <v-btn @click="help = !help"
              :title="help ? $gettext('Hide help') : $gettext('Show help')"
              variant="text"
              icon>
              <svg fill="currentColor" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M11,18H13V16H11V18M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M12,6A4,4 0 0,0 8,10H10A2,2 0 0,1 12,8A2,2 0 0,1 14,10C14,12 11,11.75 11,15H13C13,12.75 16,12.5 16,10A4,4 0 0,0 12,6Z" />
              </svg>
            </v-btn>
          </template>
          <template #append>
            <v-btn v-if="chat"
              @click="synthesizing || synthesize()"
              @keydown.enter="synthesizing || synthesize()"
              :title="synthesizing ? $gettext('Generating ...') : $gettext('Generate page based on prompt')"
              :loading="synthesizing"
              variant="text"
              icon>
              <svg v-if="synthesizing === false" fill="currentColor" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2A10,10 0 0,1 22,12M6,13H14L10.5,16.5L11.92,17.92L17.84,12L11.92,6.08L10.5,7.5L14,11H6V13Z" />
              </svg>
              <svg v-if="synthesizing === null" fill="currentColor" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M9,20.42L2.79,14.21L5.62,11.38L9,14.77L18.88,4.88L21.71,7.71L9,20.42Z" />
              </svg>
            </v-btn>

            <v-btn v-else-if="auth.can('audio:transcribe')"
              @click="record()"
              :title="$gettext('Dictate')"
              :class="{dictating: audio}"
              :loading="dictating"
              variant="text"
              icon>
              <svg v-if="audio" fill="currentColor" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M17.3,11C17.3,14 14.76,16.1 12,16.1C9.24,16.1 6.7,14 6.7,11H5C5,14.41 7.72,17.23 11,17.72V21H13V17.72C16.28,17.23 19,14.41 19,11M10.8,4.9C10.8,4.24 11.34,3.7 12,3.7C12.66,3.7 13.2,4.24 13.2,4.9L13.19,11.1C13.19,11.76 12.66,12.3 12,12.3C11.34,12.3 10.8,11.76 10.8,11.1M12,14A3,3 0 0,0 15,11V5A3,3 0 0,0 12,2A3,3 0 0,0 9,5V11A3,3 0 0,0 12,14Z" />
              </svg>
              <svg v-else fill="currentColor" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12,2A3,3 0 0,1 15,5V11A3,3 0 0,1 12,14A3,3 0 0,1 9,11V5A3,3 0 0,1 12,2M19,11C19,14.53 16.39,17.44 13,17.93V21H11V17.93C7.61,17.44 5,14.53 5,11H7A5,5 0 0,0 12,16A5,5 0 0,0 17,11H19Z" />
              </svg>
            </v-btn>
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

  .v-input--horizontal :deep(.v-input__prepend),
  .v-input--horizontal :deep(.v-input__append) {
    margin: 0;
  }

  .help {
    color: rgb(var(--v-theme-on-surface));
    background-color: rgb(var(--v-theme-surface-light));
    padding: 16px 24px 16px 32px ;
    margin-bottom: 16px;
    border-radius: 8px;
  }
</style>
