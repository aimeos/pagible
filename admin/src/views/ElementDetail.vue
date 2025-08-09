<script>
  import gql from 'graphql-tag'
  import AsideMeta from '../components/AsideMeta.vue'
  import HistoryDialog from '../components/HistoryDialog.vue'
  import ElementDetailRefs from '../components/ElementDetailRefs.vue'
  import ElementDetailItem from '../components/ElementDetailItem.vue'
  import { useAuthStore, useDrawerStore, useMessageStore} from '../stores'


  export default {
    components: {
      AsideMeta,
      HistoryDialog,
      ElementDetailRefs,
      ElementDetailItem
    },

    inject: ['closeView'],

    props: {
      'item': {type: Object, required: true}
    },

    data: () => ({
      assets: {},
      changed: false,
      error: false,
      publishAt: null,
      pubmenu: false,
      vhistory: false,
      tab: 'element',
    }),

    setup() {
      const messages = useMessageStore()
      const drawer = useDrawerStore()
      const auth = useAuthStore()

      return { auth, drawer, messages }
    },

    created() {
      if(!this.item?.id || !this.auth.can('element:view')) {
        return
      }

      this.$apollo.query({
        query: gql`query($id: ID!) {
          element(id: $id) {
            id
            files {
              id
              mime
              name
              path
              previews
              updated_at
              editor
            }
            latest {
              id
              published
              data
              editor
              created_at
              files {
                id
                mime
                name
                path
                previews
                updated_at
                editor
              }
            }
          }
        }`,
        variables: {
          id: this.item.id
        }
      }).then(result => {
        if(result.errors || !result.data.element) {
          throw result
        }

        const files = []
        const element = result.data.element

        this.reset()
        this.assets = {}

        for(const entry of (element.latest?.files || element.files || [])) {
          this.assets[entry.id] = {...entry, previews: JSON.parse(entry.previews || '{}')}
          files.push(entry.id)
        }

        this.item.files = files
      }).catch(error => {
        this.messages.add(this.$gettext('Error fetching element'), 'error')
        this.$log(`ElementDetail::watch(item): Error fetching element`, error)
      })
    },

    methods: {
      publish(at = null) {
        if(!this.auth.can('element:publish')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        this.save(true).then(valid => {
          if(!valid) {
            return
          }

          this.$apollo.mutate({
            mutation: gql`mutation ($id: [ID!]!, $at: DateTime) {
              pubElement(id: $id, at: $at) {
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
              this.messages.add(this.$gettext('Element published successfully'), 'success')
            } else {
              this.item.publish_at = at
              this.messages.add(this.$gettext('Element scheduled for publishing at %{date}', {date: at.toLocaleDateString()}), 'info')
            }

            this.closeView()
          }).catch(error => {
            this.messages.add(this.$gettext('Error publishing element'), 'error')
            this.$log(`ElementDetail::publish(): Error publishing element`, at, error)
          })
        })
      },


      reset() {
        this.changed = false
        this.error = false
      },


      save(quiet = false) {
        if(!this.auth.can('element:save')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return Promise.resolve(false)
        }

        if(!this.changed) {
          return Promise.resolve(true)
        }

        return this.$apollo.mutate({
          mutation: gql`mutation ($id: ID!, $input: ElementInput!, $files: [ID!]) {
            saveElement(id: $id, input: $input, files: $files) {
              id
            }
          }`,
          variables: {
            id: this.item.id,
            input: {
              type: this.item.type,
              name: this.item.name,
              lang: this.item.lang,
              data: JSON.stringify(this.item.data || {}),
            },
            files: this.item.files.filter((id, idx, self) => {
              return self.indexOf(id) === idx
            })
          }
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          this.item.published = false
          this.reset()

          if(!quiet) {
            this.messages.add(this.$gettext('Element saved successfully'), 'success')
          }

          return true
        }).catch(error => {
          this.messages.add(this.$gettext('Error saving element'), 'error')
          this.$log(`ElementDetail::save(): Error saving element`, error)
        })
      },


      use(version) {
        Object.assign(this.item, version.data)
        this.vhistory = false
        this.changed = true
      },


      versions(id) {
        if(!this.auth.can('element:view')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return Promise.resolve([])
        }

        if(!id) {
          return Promise.resolve([])
        }

        return this.$apollo.query({
          query: gql`query($id: ID!) {
            element(id: $id) {
              id
              versions {
                id
                published
                publish_at
                data
                editor
                created_at
                files {
                  id
                }
              }
            }
          }`,
          variables: {
            id: id
          }
        }).then(result => {
          if(result.errors || !result.data.element) {
            throw result
          }

          return (result.data.element.versions || []).map(v => {
            return {
              ...v,
              data: JSON.parse(v.data || '{}'),
              files: v.files.map(file => file.id),
            }
          }).reverse() // latest versions first
        }).catch(error => {
          this.messages.add(this.$gettext('Error fetching element versions'), 'error')
          this.$log(`ElementDetail::versions(): Error fetching element versions`, id, error)
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
        {{ $gettext('Element') }}: {{ item.name }}
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
        :disabled="!changed || error || !auth.can('element:save')"
        :variant="!changed || error || !auth.can('element:save') ? 'plain' : 'flat'"
        :color="!changed || error || !auth.can('element:save') ? '' : 'blue-darken-1'"
        icon="mdi-database-arrow-down"
      />

      <v-menu v-model="pubmenu" :close-on-content-click="false">
        <template #activator="{ props }">
          <v-btn v-bind="props" icon
            :title="$gettext('Schedule publishing')"
            :class="{error: error}" class="menu-publish"
            :disabled="item.published && !changed || error || !auth.can('element:publish')"
            :variant="item.published && !changed || error || !auth.can('element:publish') ? 'plain' : 'flat'"
            :color="item.published && !changed || error || !auth.can('element:publish') ? '' : 'blue-darken-2'"
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
        :disabled="item.published && !changed || error || !auth.can('element:publish')"
        :variant="item.published && !changed || error || !auth.can('element:publish') ? 'plain' : 'flat'"
        :color="item.published && !changed || error || !auth.can('element:publish') ? '' : 'blue-darken-2'"
      >
        <v-icon>
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
            <path d="M5,2V4H19V2H5 M5,12H9V21H15V12H19L12,5L5,12Z" />
          </svg>
        </v-icon>
      </v-btn>

      <v-btn
        @click="drawer.toggle('aside')"
        :title="$gettext('Toggle side menu')"
        :icon="drawer.aside ? 'mdi-chevron-right' : 'mdi-chevron-left'"
      />
    </template>
  </v-app-bar>

  <v-main class="element-details">
    <v-form @submit.prevent>
      <v-tabs fixed-tabs v-model="tab">
        <v-tab value="element" :class="{changed: changed, error: error}">{{ $gettext('Element') }}</v-tab>
        <v-tab value="refs">{{ $gettext('Used by') }}</v-tab>
      </v-tabs>

      <v-window v-model="tab" :touch="false">

        <v-window-item value="element">
          <ElementDetailItem
            @update:item="this.$emit('update:item', item); changed = true"
            @error="error = $event"
            :assets="assets"
            :item="item"
          />
        </v-window-item>

        <v-window-item value="refs">
          <ElementDetailRefs
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
      @use="use($event)"
      @revert="use($event); reset()"
      :current="{
        data: {
          lang: item.lang,
          type: item.type,
          name: item.name,
          data: item.data,
        },
        files: item.files,
      }"
      :load="() => versions(item.id)"
    />
  </Teleport>
</template>

<style scoped>
  .v-toolbar-title {
    margin-inline-start: 0;
  }
</style>
