/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import AsideMeta from '../components/AsideMeta.vue'
import ChangesDialog from '../components/ChangesDialog.vue'
import HistoryDialog from '../components/HistoryDialog.vue'
import FileDetailRefs from '../components/FileDetailRefs.vue'
import FileDetailItem from '../components/FileDetailItem.vue'
import { useUserStore, useDrawerStore, useMessageStore, useViewStack } from '../stores'
import { applyResult, hasUnresolved } from '../merge'
import { subscribe } from '../echo'
import {
  mdiKeyboardBackspace,
  mdiHistory,
  mdiDatabaseArrowDown,
  mdiSwapHorizontal,
  mdiChevronRight,
  mdiChevronLeft
} from '@mdi/js'

export default {
  components: {
    AsideMeta,
    ChangesDialog,
    HistoryDialog,
    FileDetailItem,
    FileDetailRefs
  },

  props: {
    item: { type: Object, required: true }
  },

  data: () => ({
    echoCleanup: null,
    file: null,
    error: false,
    changed: null,
    dirty: false,
    publishAt: null,
    publishing: false,
    pubmenu: false,
    saving: false,
    vchanged: false,
    vhistory: false,
    tab: 'file'
  }),

  setup() {
    const viewStack = useViewStack()
    const messages = useMessageStore()
    const drawer = useDrawerStore()
    const user = useUserStore()

    return {
      user,
      drawer,
      messages,
      viewStack,
      mdiKeyboardBackspace,
      mdiHistory,
      mdiDatabaseArrowDown,
      mdiSwapHorizontal,
      mdiChevronRight,
      mdiChevronLeft
    }
  },

  computed: {
    hasConflict() {
      return hasUnresolved(this.changed)
    }
  },

  created() {
    if (!this.item?.id || !this.user.can('file:view')) {
      return
    }

    subscribe('file', this.item.id, (event) => {
      if (!this.dirty && this.user.can('file:view') && event.editor !== this.user.me?.email) {
        this.item.latestId = event.versionId
        Object.assign(this.item, event.data)
      }
    }).then((cleanup) => {
      this.echoCleanup = cleanup
    })
  },

  beforeUnmount() {
    this.echoCleanup?.()
  },

  methods: {
    errorUpdated(event) {
      this.error = event
    },

    fileUpdated(event) {
      this.file = event
      this.dirty = true
    },

    itemUpdated() {
      this.$emit('update:item', this.item)
      this.dirty = true
    },

    publish(at = null) {
      if (!this.user.can('file:publish')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      this.publishing = true

      this.save(true).then((valid) => {
        if (!valid || this.changed) {
          return
        }

        this.$apollo
          .mutate({
            mutation: gql`
              mutation ($id: [ID!]!, $at: DateTime) {
                pubFile(id: $id, at: $at) {
                  id
                }
              }
            `,
            variables: {
              id: [this.item.id],
              at: at?.toISOString()?.substring(0, 19)?.replace('T', ' ')
            }
          })
          .then((response) => {
            if (response.errors) {
              throw response.errors
            }

            if (!at) {
              this.item.published = true
              this.messages.add(this.$gettext('File published successfully'), 'success')
            } else {
              this.item.publish_at = at
              this.messages.add(
                this.$gettext('File scheduled for publishing at %{date}', {
                  date: at.toLocaleDateString()
                }),
                'info'
              )
            }

            this.viewStack.closeView()
          })
          .catch((error) => {
            this.messages.add(this.$gettext('Error publishing file') + ':\n' + error, 'error')
            this.$log(`FileDetail::publish(): Error publishing file`, at, error)
          })
          .finally(() => {
            this.publishing = false
          })
      })
    },

    published() {
      this.publish(this.publishAt)
      this.pubmenu = false
    },

    reset() {
      this.dirty = false
      this.changed = null
      this.error = false
    },

    revertVersion(event) {
      this.use(event)
      this.reset()
    },

    save(quiet = false) {
      if (!this.user.can('file:save')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return Promise.resolve(false)
      }

      if (this.error) {
        this.messages.add(
          this.$gettext('There are invalid fields, please resolve the errors first'),
          'error'
        )
        return Promise.resolve(false)
      }

      if (!this.dirty) {
        return Promise.resolve(true)
      }

      this.saving = true

      return this.$apollo
        .mutate({
          mutation: gql`
            mutation ($id: ID!, $input: FileInput!, $file: Upload, $latestId: ID) {
              saveFile(id: $id, input: $input, file: $file, latestId: $latestId) {
                id
                latest {
                  id
                  data
                  created_at
                }
                changed
              }
            }
          `,
          variables: {
            id: this.item.id,
            input: {
              transcription: JSON.stringify(this.item.transcription || {}),
              description: JSON.stringify(this.item.description || {}),
              previews: JSON.stringify(this.item.previews || {}),
              path: this.item.path,
              name: this.item.name,
              lang: this.item.lang
            },
            file: this.file,
            latestId: this.item.latestId
          },
          context: {
            hasUpload: true
          }
        })
        .then((result) => {
          if (result.errors) {
            throw result.errors
          }

          const file = result.data?.saveFile
          const latest = file?.latest
          const changed = file?.changed ? JSON.parse(file.changed) : null

          Object.assign(this.item, JSON.parse(latest?.data || '{}'))
          this.item.updated_at = latest?.created_at
          this.item.latestId = latest?.id

          applyResult(this, changed, this.$gettext('File saved successfully'), quiet)

          return true
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error saving file') + ':\n' + error, 'error')
          this.$log(`FileDetail::save(): Error saving file`, error)
        })
        .finally(() => {
          this.saving = false
        })
    },

    use(version) {
      Object.assign(this.item, version.data)
      this.vhistory = false
      this.dirty = true
    },

    versions(id) {
      if (!this.user.can('file:view')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return Promise.resolve([])
      }

      if (!id) {
        return Promise.resolve([])
      }

      return this.$apollo
        .query({
          query: gql`
            query ($id: ID!) {
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
            }
          `,
          variables: {
            id: id
          }
        })
        .then((result) => {
          if (result.errors || !result.data.file) {
            throw result
          }

          const keys = ['previews', 'description', 'transcription']

          return (result.data.file.versions || []).map((v) => {
            const item = { ...v, data: JSON.parse(v.data || '{}') }
            keys.forEach((key) => (item[key] ??= {}))
            return item
          })
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error fetching file versions') + ':\n' + error, 'error')
          this.$log(`FileDetail::versions(): Error fetching file versions`, id, error)
        })
    }
  }
}
</script>

<template>
  <v-app-bar :elevation="0" density="compact" role="sectionheader" :aria-label="$gettext('Menu')">
    <template v-slot:prepend>
      <v-btn
        @click="viewStack.closeView()"
        :title="$gettext('Back to list view')"
        :icon="mdiKeyboardBackspace"
      />
    </template>

    <v-app-bar-title>
      <h1 class="app-title">{{ $gettext('File') }}: {{ item.name }}</h1>
    </v-app-bar-title>

    <template v-slot:append>
      <v-btn
        @click="vhistory = true"
        :class="{ hidden: item.published && !dirty && !item.latest }"
        :title="$gettext('View history')"
        :icon="mdiHistory"
        class="no-rtl"
      />

      <v-btn v-if="changed"
        @click="vchanged = true"
        :class="{ error: hasConflict }"
        :title="$gettext('View merge changes')"
        :icon="mdiSwapHorizontal"
        class="menu-changed"
      />

      <v-btn
        @click="save()"
        :loading="saving"
        :title="$gettext('Save')"
        :class="{ error: error }"
        class="menu-save"
        :disabled="!dirty || error || !user.can('file:save')"
        :variant="!dirty || error || !user.can('file:save') ? 'plain' : 'flat'"
        :color="hasConflict ? 'warning' : (!dirty || error || !user.can('file:save') ? '' : 'blue-darken-1')"
        :icon="mdiDatabaseArrowDown"
      />

      <v-menu v-model="pubmenu" :close-on-content-click="false">
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            icon
            :loading="publishing"
            :title="$gettext('Schedule publishing')"
            :class="{ error: error }"
            class="menu-publish"
            :disabled="(item.published && !dirty) || error || !user.can('file:publish')"
            :variant="
              (item.published && !dirty) || error || !user.can('file:publish') ? 'plain' : 'flat'
            "
            :color="
              (item.published && !dirty) || error || !user.can('file:publish')
                ? ''
                : 'blue-darken-2'
            "
          >
            <v-icon>
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
                <path d="M2,1V3H16V1H2 M2,10H6V19H12V10H16L9,3L2,10Z" />
                <path
                  d="M16.7 11.4C16.7 11.4 16.61 11.4 16.7 11.4C13.19 11.49 10.4 14.28 10.4 17.7C10.4 21.21 13.19 24 16.7 24S23 21.21 23 17.7 20.21 11.4 16.7 11.4M16.7 22.2C14.18 22.2 12.2 20.22 12.2 17.7S14.18 13.2 16.7 13.2 21.2 15.18 21.2 17.7 19.22 22.2 16.7 22.2M15.6 13.1V17.6L18.84 19.58L19.56 18.5L16.95 16.97V13.1H15.6Z"
                />
              </svg>
            </v-icon>
          </v-btn>
        </template>
        <div class="menu-content">
          <v-date-picker v-model="publishAt" hide-header show-adjacent-months />
          <v-btn
            @click="published"
            :disabled="!publishAt || error"
            :color="publishAt ? 'primary' : ''"
            variant="text"
            >{{ $gettext('Publish') }}</v-btn
          >
        </div>
      </v-menu>

      <v-btn
        icon
        @click="publish()"
        :loading="publishing"
        :title="$gettext('Publish')"
        :class="{ error: error }"
        class="menu-publish"
        :disabled="(item.published && !dirty) || error || !user.can('file:publish')"
        :variant="
          (item.published && !dirty) || error || !user.can('file:publish') ? 'plain' : 'flat'
        "
        :color="
          (item.published && !dirty) || error || !user.can('file:publish') ? '' : 'blue-darken-2'
        "
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
        :icon="drawer.aside ? mdiChevronRight : mdiChevronLeft"
      />
    </template>
  </v-app-bar>

  <v-main class="file-details" :aria-label="$gettext('File')">
    <v-form @submit.prevent>
      <v-tabs fixed-tabs v-model="tab">
        <v-tab value="file" :class="{ changed: dirty, error: error }">{{
          $gettext('File')
        }}</v-tab>
        <v-tab value="refs">{{ $gettext('Used by') }}</v-tab>
      </v-tabs>

      <v-window v-model="tab" :touch="false">
        <v-window-item value="file">
          <FileDetailItem
            @update:item="itemUpdated"
            @update:file="fileUpdated"
            @error="errorUpdated"
            :item="item"
          />
        </v-window-item>

        <v-window-item value="refs">
          <FileDetailRefs :item="item" />
        </v-window-item>
      </v-window>
    </v-form>
  </v-main>

  <AsideMeta :item="item" />

  <Teleport to="body">
    <HistoryDialog
      v-model="vhistory"
      :readonly="!user.can('file:save')"
      :current="{
        data: {
          lang: item.lang,
          name: item.name,
          mime: item.mime,
          path: item.path,
          previews: item.previews,
          description: item.description,
          transcription: item.transcription
        }
      }"
      :load="() => versions(item.id)"
      @revert="revertVersion"
      @use="use($event)"
    />
    <ChangesDialog v-model="vchanged" :changed="changed"
      :targets="{ data: item }"
      @resolve="dirty = true"
    />
  </Teleport>
</template>

<style scoped>
.v-toolbar-title {
  margin-inline-start: 0;
}
</style>
