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
        :class="{error: error}" class="menu-save"
        :disabled="!changed || error || !auth.can('element:save')"
        variant="text"
      >{{ $gettext('Save') }}</v-btn>

      <v-menu v-model="pubmenu" :close-on-content-click="false">
        <template #activator="{ props }">
          <v-btn-group class="menu-publish" variant="text">
            <v-btn
              @click="publish()"
              :class="{error: error}" class="button"
              :disabled="item.published && !changed || error || !auth.can('element:publish')"
            >{{ $gettext('Publish') }}</v-btn>
            <v-btn v-bind="props"
              :class="{error: error}" class="icon"
              :disabled="item.published && !changed || error || !auth.can('element:publish')"
              :title="$gettext('Schedule publishing')"
              icon="mdi-menu-down"
            />
          </v-btn-group>
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

      <v-window v-model="tab">

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
