/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import AsideMeta from '../components/AsideMeta.vue'
import ChangesDialog from '../components/ChangesDialog.vue'
import DetailAppBar from '../components/DetailAppBar.vue'
import HistoryDialog from '../components/HistoryDialog.vue'
import FileDetailRefs from '../components/FileDetailRefs.vue'
import FileDetailItem from '../components/FileDetailItem.vue'
import { useDirtyStore, useUserStore, useMessageStore, useViewStack } from '../stores'
import { applyResult, hasUnresolved } from '../merge'
import { publishItem } from '../publish'
import { subscribe } from '../echo'

export default {
  components: {
    AsideMeta,
    ChangesDialog,
    DetailAppBar,
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
    saving: false,
    vchanged: false,
    vhistory: false,
    tab: 'file'
  }),

  setup() {
    const dirtyStore = useDirtyStore()
    const viewStack = useViewStack()
    const messages = useMessageStore()
    const user = useUserStore()

    return {
      dirtyStore,
      user,
      messages,
      viewStack
    }
  },

  computed: {
    hasConflict() {
      return hasUnresolved(this.changed)
    }
  },

  created() {
    this.dirtyStore.register(() => this.save(true))

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
    this.dirtyStore.unregister()
    this.echoCleanup?.()
  },

  methods: {
    apply(changes) {
      Object.assign(this.item, changes)
      this.dirty = true
      this.vhistory = false
    },

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
      publishItem(this, 'file', {
        success: this.$gettext('File published successfully'),
        scheduled: (d) => this.$gettext('File scheduled for publishing at %{date}', { date: d.toLocaleDateString() }),
        error: this.$gettext('Error publishing file')
      }, at)
    },

    published() {
      this.publish(this.publishAt)
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
  },

  watch: {
    dirty(value) {
      this.dirtyStore.set(value)
    }
  }
}
</script>

<template>
  <DetailAppBar
    type="file"
    :label="$gettext('File')"
    :name="item.name"
    :dirty="dirty"
    :error="error"
    :conflict="hasConflict"
    :changed="changed"
    :published="item.published"
    :has-latest="!!item.latest"
    :saving="saving"
    :publishing="publishing"
    v-model:publish-at="publishAt"
    @save="save()"
    @publish="publish()"
    @schedule="published"
    @history="vhistory = true"
    @changes="vchanged = true"
  />

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
      @apply="apply"
      @use="use($event)"
    />
    <ChangesDialog v-model="vchanged" :changed="changed"
      :targets="{ data: item }"
      @resolve="dirty = true"
    />
  </Teleport>
</template>
