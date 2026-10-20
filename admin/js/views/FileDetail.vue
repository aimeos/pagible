/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import DetailRefs from '../components/DetailRefs.vue'
import FileDetailItem from '../components/FileDetailItem.vue'
import { applyResult, hasUnresolved } from '../merge'
import { detailBase, useDetail } from '../detail'
import { markRaw } from 'vue'
import { safeParse } from '../json'
import { clone } from '../utils'

// fields the user edits, which are kept if they were changed while saving
const EDITABLE = ['description', 'lang', 'name', 'transcription']

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
    savedPreviews: null
  }),

  setup() {
    return useDetail('file')
  },

  created() {
    this.savedPreviews = this.previewsJson()
  },

  computed: {
    changeTargets() {
      return markRaw({ data: this.item, aux: this.item })
    },

    hasConflict() {
      return hasUnresolved(this.changed, ['data', 'aux'])
    }
  },

  methods: {
    // the state compared in the history dialog, taken when it opens like in the page view
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
          description: clone(item.description),
          transcription: clone(item.transcription)
        }),
        files: this.media(item)
      })
    },

    // loads the latest version into the open editor; resolves true on success so the caller
    // can defer the websocket subscription until the initial load completed
    reload() {
      return this.reloadVersion(FETCH_FILE, this.$gettext('Error fetching file'), (file) => {
        const latest = file.latest

        Object.assign(this.item, safeParse(latest?.data), safeParse(latest?.aux))
        this.item.disk = file.disk
        this.item.latestId = latest?.id
        this.savedPreviews = this.previewsJson()
      }, () => !this.dirty)
    },

    apply(changes) {
      Object.assign(this.item, changes)
      this.markChanged()
      this.vhistory = false
    },

    // applies changes that are already saved without making the item dirty
    itemSynced(patch) {
      Object.assign(this.item, patch)
      // the next save only sends the previews if they were changed afterwards
      if ('previews' in patch) this.savedPreviews = this.previewsJson()
    },

    fileUpdated(event) {
      this.file = event
      this.markChanged()
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

    saveItem(quiet) {
      const upload = this.file
      const previews = this.previewsJson()

      return this.saveWith(() => ({
        mutation: SAVE_FILE,
        variables: {
          id: this.item.id,
          input: {
            transcription: JSON.stringify(this.item.transcription || {}),
            description: JSON.stringify(this.item.description || {}),
            // keep previews updated in the meantime (e.g. by cms:previews) if unchanged
            ...(previews !== this.savedPreviews ? { previews } : {}),
            path: this.item.path,
            name: this.item.name,
            lang: this.item.lang
          },
          file: upload,
          latestId: this.item.latestId
        },
        context: {
          hasUpload: true
        }
      }), (result, edited, sent) => {
        const file = result.data?.saveFile
        const latest = file?.latest
        const changed = file?.changed ? markRaw(safeParse(file.changed)) : null
        const data = { ...safeParse(latest?.data), ...safeParse(latest?.aux) }

        // fields edited while saving are kept and sent by the next save
        if (edited) {
          EDITABLE.forEach((key) => delete data[key])
        }

        Object.assign(this.item, data)
        this.item.latestId = latest?.id
        this.savedPreviews = this.previewsJson()

        // the uploaded image is saved now, later saves must not upload it again
        if (this.file === upload) {
          this.file = null
        }

        applyResult(this, changed, this.$gettext('File saved successfully'), quiet, edited, sent)

        this.saved(latest)
        this.onSaved?.()
      }, this.$gettext('Error saving file'))
    },

    use(version, clean = false) {
      Object.assign(this.item, version.data)
      this.vhistory = false
      this.markChanged()

      // discarding the changes also drops the file which would be uploaded by the next save
      if (clean) {
        this.file = null
        this.reset()
      }
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
        <v-tab value="file" :class="{ changed: dirty }">{{
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
            @patch="itemUpdated"
            @sync="itemSynced"
            @update:file="fileUpdated"
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

  <DetailDialogs v-bind="dialogs" />
</template>
