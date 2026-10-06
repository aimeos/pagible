/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import DetailRefs from '../components/DetailRefs.vue'
import FileDetailItem from '../components/FileDetailItem.vue'
import { applyResult, hasUnresolved } from '../merge'
import { detailBase, useDetail } from '../detail'
import { markRaw } from 'vue'
import { safeParse } from '../utils'

const FETCH_FILE = gql`
  query ($id: ID!) {
    file(id: $id) {
      disk
      id
      latest {
        id
        published
        aux
        data
        editor
        created_at
      }
    }
  }
`

const SAVE_FILE = gql`
  mutation ($id: ID!, $input: FileInput!, $file: Upload, $latestId: ID) {
    saveFile(id: $id, input: $input, file: $file, latestId: $latestId) {
      disk
      id
      latest {
        id
        aux
        data
        published
        publish_at
        editor
        created_at
      }
      changed
    }
  }
`

const FETCH_FILE_VERSIONS = gql`
  query ($id: ID!) {
    file(id: $id) {
      disk
      id
      versions {
        id
        published
        publish_at
        aux
        data
        editor
        created_at
      }
    }
  }
`

export default {
  extends: detailBase,

  components: {
    ...detailBase.components,
    DetailRefs,
    FileDetailItem
  },

  props: {
    onSaved: { type: Function, default: null }
  },

  data: () => ({
    file: null,
    initial: null
  }),

  setup() {
    return useDetail('file')
  },

  created() {
    this.initial = this.previewsJson()
  },

  beforeUnmount() {
    this.file = null
  },

  computed: {
    hasConflict() {
      return hasUnresolved(this.changed, ['data', 'aux'])
    },

    historyCurrent() {
      const item = this.item
      return markRaw({
        data: Object.freeze({
          scheduled: item.publish_at ? 1 : 0,
          lang: item.lang,
          name: item.name,
          mime: item.mime,
          path: item.path,
          previews: item.previews,
          description: item.description,
          transcription: item.transcription
        }),
        files: this.media(item)
      })
    }
  },

  methods: {
    // loads the latest version into the open editor; resolves true on success so the caller
    // can defer the websocket subscription until the initial load completed
    reload() {
      return this.reloadVersion(FETCH_FILE, this.$gettext('Error fetching file'), (file) => {
        const latest = file.latest

        Object.assign(this.item, safeParse(latest?.data), safeParse(latest?.aux))
        this.item.disk = file.disk
        this.item.latestId = latest?.id
        this.item.published = latest?.published
        this.item.updated_at = latest?.created_at
        this.item.editor = latest?.editor
        this.initial = this.previewsJson()
      }, () => !this.dirty)
    },

    apply(changes) {
      Object.assign(this.item, changes)
      this.dirty = true
      this.vhistory = false
    },

    fileUpdated(event) {
      this.file = event
      this.dirty = true
    },

    media(data) {
      if (!data?.path) {
        return {}
      }

      return Object.freeze({
        [data.path]: Object.freeze({
          disk: this.item.disk,
          id: data.path,
          name: data.name,
          mime: data.mime,
          path: data.path,
          previews: data.previews || {}
        })
      })
    },

    previewsJson() {
      return JSON.stringify(this.item?.previews || {})
    },

    save(quiet = false) {
      if (!this.saveable()) {
        return Promise.resolve(false)
      }

      if (!this.dirty) {
        return Promise.resolve(true)
      }

      this.saving = true
      const previews = this.previewsJson()

      return this.$apollo
        .mutate({
          mutation: SAVE_FILE,
          variables: {
            id: this.item.id,
            input: {
              transcription: JSON.stringify(this.item.transcription || {}),
              description: JSON.stringify(this.item.description || {}),
              // keep previews updated in the meantime (e.g. by cms:previews) if unchanged
              ...(previews !== this.initial ? { previews } : {}),
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
          const file = result.data?.saveFile
          const latest = file?.latest
          const changed = file?.changed ? markRaw(safeParse(file.changed)) : null

          Object.assign(this.item, safeParse(latest?.data), safeParse(latest?.aux))
          this.item.latestId = latest?.id
          this.initial = this.previewsJson()

          applyResult(this, changed, this.$gettext('File saved successfully'), quiet)

          this.saved(latest)
          this.onSaved?.()

          return true
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error saving file'), error)
        })
        .finally(() => {
          this.saving = false
        })
    },

    use(version, clean = false) {
      Object.assign(this.item, version.data)
      this.vhistory = false
      this.dirty = true
      if (clean) this.reset()
    },

    versions(id) {
      return this.loadVersions(FETCH_FILE_VERSIONS, id, v => {
        const data = Object.assign(safeParse(v.data), safeParse(v.aux))
        const item = { ...v, data: Object.freeze(data) }
        delete item.aux
        item.files = this.media(item.data)
        return Object.freeze(item)
      })
    }
  }
}
</script>

<template>
  <DetailAppBar v-bind="bar" :label="$gettext('File')" :has-latest="!!item.latestId" />

  <v-main class="file-details" :aria-label="$gettext('File')">
    <v-progress-linear v-if="loading" indeterminate color="primary" />
    <v-form v-else ref="form" @submit.prevent>
      <v-tabs class="detail-tabs" fixed-tabs hide-slider v-model="tab">
        <v-tab value="file" :class="{ changed: dirty, error: error }">{{
          $gettext('File')
        }}</v-tab>
        <v-tab value="refs">{{ $gettext('Used by') }}</v-tab>
        <v-tab v-for="(sp, key) in subpanels" :key="key" :value="'ext-' + key">
          {{ label(sp) }}
        </v-tab>
      </v-tabs>

      <v-window v-model="tab" :touch="false">
        <v-window-item value="file">
          <FileDetailItem
            @update:item="itemUpdated"
            @update:file="fileUpdated"
            @error="error = $event"
            :item="item"
          />
        </v-window-item>

        <v-window-item value="refs">
          <DetailRefs :item="item" type="file" />
        </v-window-item>

        <v-window-item v-for="(sp, key) in subpanels" :key="key" :value="'ext-' + key">
          <component :is="sp.component" :item="item" />
        </v-window-item>
      </v-window>
    </v-form>
  </v-main>

  <AsideMeta :item="item" />

  <Teleport to="body">
    <HistoryDialog
      v-if="vhistory"
      v-model="vhistory"
      :readonly="!user.can('file:save')"
      :current="historyCurrent"
      :load="() => versions(item.id)"
      @apply="apply"
      @use="use"
    />
    <ChangesDialog v-model="vchanged" :changed="changed"
      :targets="{ data: item, aux: item }"
      @resolve="dirty = true"
    />
  </Teleport>
</template>
