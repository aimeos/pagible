<script>
  import gql from 'graphql-tag'
  import AsideMeta from '../components/AsideMeta.vue'
  import HistoryDialog from '../components/HistoryDialog.vue'
  import FileDetailRefs from '../components/FileDetailRefs.vue'
  import FileDetailItem from '../components/FileDetailItem.vue'
  import { useAuthStore, useDrawerStore, useMessageStore } from '../stores'


  export default {
    components: {
      AsideMeta,
      HistoryDialog,
      FileDetailItem,
      FileDetailRefs
    },

    inject: ['closeView'],

    props: {
      'item': {type: Object, required: true}
    },

    data: () => ({
      file: null,
      error: false,
      changed: false,
      publishAt: null,
      pubmenu: false,
      vhistory: false,
      tab: 'file',
      savecnt: 0,
    }),

    setup() {
      const messages = useMessageStore()
      const drawer = useDrawerStore()
      const auth = useAuthStore()

      return { auth, drawer, messages }
    },

    methods: {
      publish(at = null) {
        if(!this.auth.can('file:publish')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        this.save(true).then(valid => {
          if(!valid) {
            return
          }

          this.$apollo.mutate({
            mutation: gql`mutation ($id: [ID!]!, $at: DateTime) {
              pubFile(id: $id, at: $at) {
                id
              }
            }`,
            variables: {
              id: [this.item.id],
              at: at?.toISOString()?.substring(0, 19)?.replace('T', ' ')
            }
          }).then(response => {
            if(response.errors) {
              throw response.errors
            }

            if(!at) {
              this.item.published = true
              this.messages.add(this.$gettext('File published successfully'), 'success')
            } else {
              this.item.publish_at = at
              this.messages.add(this.$gettext('File scheduled for publishing at %{date}', {date: at.toLocaleDateString()}), 'info')
            }

            this.closeView()
          }).catch(error => {
            this.messages.add(this.$gettext('Error publishing file'), 'error')
            this.$log(`FileDetail::publish(): Error publishing file`, at, error)
          })
        })
      },


      reset() {
        this.changed = false
        this.error = false
      },


      save(quiet = false) {
        if(!this.auth.can('file:save')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return Promise.resolve(false)
        }

        if(!this.changed) {
          return Promise.resolve(true)
        }

        return this.$apollo.mutate({
          mutation: gql`mutation ($id: ID!, $input: FileInput!, $file: Upload) {
            saveFile(id: $id, input: $input, file: $file) {
              id
              latest {
                id
                data
                created_at
              }
            }
          }`,
          variables: {
            id: this.item.id,
            input: {
              transcription: JSON.stringify(this.item.transcription || {}),
              description: JSON.stringify(this.item.description || {}),
              previews: JSON.stringify(this.item.previews || {}),
              path: this.item.path,
              name: this.item.name,
              lang: this.item.lang,
            },
            file: this.file
          },
          context: {
            hasUpload: true
          }
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          const latest = result.data?.saveFile?.latest

          Object.assign(this.item, JSON.parse(latest?.data || '{}'))
          this.item.updated_at = latest?.created_at
          this.item.published = false
          this.reset()

          if(!quiet) {
            this.messages.add(this.$gettext('File saved successfully'), 'success')
          }

          this.savecnt++
          return true
        }).catch(error => {
          this.messages.add(this.$gettext('Error saving file'), 'error')
          this.$log(`FileDetail::save(): Error saving file`, error)
        })
      },


      use(version) {
        Object.assign(this.item, version.data)
        this.vhistory = false
        this.changed = true
        this.savecnt++
      },


      versions(id) {
        if(!this.auth.can('file:view')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return Promise.resolve([])
        }

        if(!id) {
          return Promise.resolve([])
        }

        return this.$apollo.query({
          query: gql`query($id: ID!) {
            file(id: $id) {
              id
              versions {
                id
                published
                publish_at
                data
                editor
                created_at
              }
            }
          }`,
          variables: {
            id: id
          }
        }).then(result => {
          if(result.errors || !result.data.file) {
            throw result
          }

          const keys = ['previews', 'description', 'transcription']

          return (result.data.file.versions || []).map(v => {
            const item = {...v, data: JSON.parse(v.data || '{}')}
            keys.forEach(key => item[key] ??= {})
            return item
          }).reverse() // latest versions first
        }).catch(error => {
          this.messages.add(this.$gettext('Error fetching file versions'), 'error')
          this.$log(`FileDetail::versions(): Error fetching file versions`, id, error)
        })
      }
    }
  }
</script>

<template>
  <v-app-bar :elevation="0" density="compact">
    <template v-slot:prepend>
      <v-btn
        @click="closeView()"
        :title="$gettext('Back to list view')"
        icon="mdi-keyboard-backspace"
      />
    </template>

    <v-app-bar-title>
      <div class="app-title">
        {{ $gettext('File') }}: {{ item.name }}
      </div>
    </v-app-bar-title>

    <template v-slot:append>
      <v-btn
        @click="vhistory = true"
        :class="{hidden: item.published && !changed && !item.latest}"
        :title="$gettext('View history')"
        icon="mdi-history"
        class="no-rtl"
      />

      <v-btn
        @click="save()"
        :title="$gettext('Save')"
        :class="{error: error}" class="menu-save"
        :disabled="!changed || error || !auth.can('file:save')"
        :variant="!changed || error || !auth.can('file:save') ? 'plain' : 'flat'"
        :color="!changed || error || !auth.can('file:save') ? '' : 'blue-darken-1'"
        icon="mdi-database-arrow-down"
      />

      <v-menu v-model="pubmenu" :close-on-content-click="false">
        <template #activator="{ props }">
          <v-btn v-bind="props" icon
            :title="$gettext('Schedule publishing')"
            :class="{error: error}" class="menu-publish"
            :disabled="item.published && !changed || error || !auth.can('file:publish')"
            :variant="item.published && !changed || error || !auth.can('file:publish') ? 'plain' : 'flat'"
            :color="item.published && !changed || error || !auth.can('file:publish') ? '' : 'blue-darken-2'"
          >
            <v-icon>
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
                <path d="M2,1V3H16V1H2 M2,10H6V19H12V10H16L9,3L2,10Z" />
                <path d="M16.7 11.4C16.7 11.4 16.61 11.4 16.7 11.4C13.19 11.49 10.4 14.28 10.4 17.7C10.4 21.21 13.19 24 16.7 24S23 21.21 23 17.7 20.21 11.4 16.7 11.4M16.7 22.2C14.18 22.2 12.2 20.22 12.2 17.7S14.18 13.2 16.7 13.2 21.2 15.18 21.2 17.7 19.22 22.2 16.7 22.2M15.6 13.1V17.6L18.84 19.58L19.56 18.5L16.95 16.97V13.1H15.6Z" />
              </svg>
            </v-icon>
          </v-btn>
        </template>
        <div class="menu-content">
          <v-date-picker v-model="publishAt" hide-header show-adjacent-months />
          <v-btn
            @click="publish(publishAt); pubmenu = false"
            :disabled="!publishAt || error"
            :color="publishAt ? 'primary' : ''"
            variant="flat"
          >{{ $gettext('Publish') }}</v-btn>
        </div>
      </v-menu>

      <v-btn icon
        @click="publish()"
        :title="$gettext('Publish')"
        :class="{error: error}" class="menu-publish"
        :disabled="item.published && !changed || error || !auth.can('file:publish')"
        :variant="item.published && !changed || error || !auth.can('file:publish') ? 'plain' : 'flat'"
        :color="item.published && !changed || error || !auth.can('file:publish') ? '' : 'blue-darken-2'"
      >
        <v-icon>
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
            <path d="M5,2V4H19V2H5 M5,12H9V21H15V12H19L12,5L5,12Z" />
          </svg>
        </v-icon>
      </v-btn>

      <v-btn
        @click.stop="drawer.toggle('aside')"
        :title="$gettext('Toggle side menu')"
        :icon="drawer.aside ? 'mdi-chevron-right' : 'mdi-chevron-left'"
      />
    </template>
  </v-app-bar>

  <v-main class="file-details">
    <v-form @submit.prevent>
      <v-tabs fixed-tabs v-model="tab">
        <v-tab value="file" :class="{changed: changed, error: error}">{{ $gettext('File') }}</v-tab>
        <v-tab value="refs">{{ $gettext('Used by') }}</v-tab>
      </v-tabs>

      <v-window v-model="tab" :touch="false">

        <v-window-item value="file">
          <FileDetailItem
            @update:item="this.$emit('update:item', item); changed = true"
            @update:file="this.file = $event; changed = true"
            @error="error = $event"
            :save="{count: savecnt}"
            :item="item"
          />
        </v-window-item>

        <v-window-item value="refs">
          <FileDetailRefs
            :item="item"
          />
        </v-window-item>

      </v-window>
    </v-form>
  </v-main>

  <AsideMeta :item="item" />

  <Teleport to="body">
    <HistoryDialog
      v-model="vhistory"
      :current="{
        data: {
          lang: item.lang,
          name: item.name,
          mime: item.mime,
          path: item.path,
          previews: item.previews,
          description: item.description,
          transcription: item.transcription,
        },
      }"
      :load="() => versions(item.id)"
      @use="use($event)"
      @revert="use($event); reset()"
    />
  </Teleport>
</template>

<style scoped>
  .v-toolbar-title {
    margin-inline-start: 0;
  }
</style>
