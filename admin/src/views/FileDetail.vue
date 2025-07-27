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
        :title="$gettext('View history')"
        :class="{hidden: item.published && !changed && !item.latest}"
        icon="mdi-history"
        class="no-rtl"
      />

      <v-btn
        @click="save()"
        :class="{error: error}"
        :disabled="!changed || error || !auth.can('file:save')"
        class="menu-save"
        variant="text">
        {{ $gettext('Save') }}
      </v-btn>

      <v-menu v-model="pubmenu" :close-on-content-click="false">
        <template #activator="{ props }">
          <v-btn-group class="menu-publish" variant="text">
            <v-btn
              @click="publish()"
              :class="{error: error}" class="button"
              :disabled="item.published && !changed || error || !auth.can('file:publish')"
            >{{ $gettext('Publish') }}</v-btn>
            <v-btn v-bind="props"
              :class="{error: error}" class="icon"
              :disabled="item.published && !changed || error || !auth.can('file:publish')"
              :title="$gettext('Schedule publishing')"
              icon="mdi-menu-down"
            />
          </v-btn-group>
        </template>
        <div class="menu-content">
          <v-date-picker v-model="publishAt" hide-header show-adjacent-months />
          <v-btn
            @click="publish(publishAt); pubmenu = false"
            :color="publishAt ? 'primary' : ''"
            :disabled="!publishAt || error"
            variant="flat"
          >{{ $gettext('Publish') }}</v-btn>
        </div>
      </v-menu>

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

      <v-window v-model="tab">

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
