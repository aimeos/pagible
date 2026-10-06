/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import DetailRefs from '../components/DetailRefs.vue'
import ElementDetailItem from '../components/ElementDetailItem.vue'
import { useSchemaStore } from '../stores'
import { applyResult } from '../merge'
import { detailBase, useDetail } from '../detail'
import { FILE_FIELDS, fileMap } from '../files'
import { references } from '../history'
import { markRaw } from 'vue'
import { frozenParse, itemTitle, safeParse } from '../utils'

const FETCH_ELEMENT = gql`
  ${FILE_FIELDS}
  query ($id: ID!) {
    element(id: $id) {
      id
      files {
        ...CmsFileFields
      }
      latest {
        id
        published
        data
        editor
        created_at
        files {
          ...CmsFileFields
        }
      }
    }
  }
`

const SAVE_ELEMENT = gql`
  mutation ($id: ID!, $input: ElementInput!, $latestId: ID) {
    saveElement(id: $id, input: $input, latestId: $latestId) {
      id
      latest { id published publish_at editor created_at }
      changed
    }
  }
`

const FETCH_ELEMENT_VERSIONS = gql`
  ${FILE_FIELDS}
  query ($id: ID!) {
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
          ...CmsFileFields
        }
      }
    }
  }
`

export default {
  extends: detailBase,

  components: {
    ...detailBase.components,
    DetailRefs,
    ElementDetailItem
  },

  data: () => ({
    assets: {},
    latestId: null
  }),

  setup() {
    return { ...useDetail('element'), schemas: useSchemaStore() }
  },

  created() {
    this.schemas.load()
  },

  beforeUnmount() {
    this.assets = markRaw({})
  },

  computed: {
    changeTargets() {
      return markRaw({ data: this.item })
    },

    historyCurrent() {
      const item = this.item
      const ids = new Set(item.files || [])
      const files = Object.fromEntries(Object.entries(this.assets).filter(([id]) => ids.has(id)))

      return markRaw({
        data: Object.freeze({
          data: item.data || {},
          scheduled: item.publish_at ? 1 : 0,
          lang: item.lang,
          type: item.type,
          name: item.name,
        }),
        files: markRaw(files)
      })
    }
  },

  methods: {
    // loads the latest version into the open editor; resolves true on success so the caller
    // can defer the websocket subscription until the initial load completed
    reload() {
      return this.reloadVersion(FETCH_ELEMENT, this.$gettext('Error fetching element'), (element) => {
        Object.assign(this.item, safeParse(element.latest?.data))
        this.item.published = element.latest?.published
        this.item.editor = element.latest?.editor
        this.item.updated_at = element.latest?.created_at
        this.latestId = element.latest?.id

        const files = element.latest?.files || element.files || []
        this.assets = markRaw(fileMap(files))
        this.item.files = files.map(file => file.id)
      }, () => !this.dirty)
    },

    apply(changes, version) {
      if (version) this.assets = { ...version.files, ...this.assets }
      Object.assign(this.item, changes)
      if ('data' in changes) this.item.files = references(this.item.data)
      this.dirty = true
      this.vhistory = false
    },

    files: fileMap,

    save(quiet = false) {
      if (!this.saveable()) {
        return Promise.resolve(false)
      }

      if (!this.dirty) {
        return Promise.resolve(true)
      }

      if (!this.item.name) {
        this.item.name = itemTitle(this.item.data)
      }

      this.saving = true

      return this.$apollo
        .mutate({
          mutation: SAVE_ELEMENT,
          variables: {
            id: this.item.id,
            input: {
              type: this.item.type,
              name: this.item.name,
              lang: this.item.lang,
              data: JSON.stringify(this.item.data || {})
            },
            latestId: this.latestId
          }
        })
        .then((result) => {
          const el = result.data?.saveElement
          const changed = el?.changed ? markRaw(safeParse(el.changed)) : null

          if (changed?.latest?.id || el?.latest?.id) {
            this.latestId = changed?.latest?.id ?? el.latest.id
          }

          applyResult(this, changed, this.$gettext('Element saved successfully'), quiet)

          this.item.latestId = this.latestId
          this.saved(el?.latest)

          return true
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error saving element'), error)
        })
        .finally(() => {
          this.saving = false
        })
    },

    use(version, clean = false) {
      Object.assign(this.item, version.data)

      this.assets = version.files || {}
      this.item.files = Object.keys(version.files || {})

      this.vhistory = false
      this.dirty = true
      if (clean) this.reset()
    },

    versions(id) {
      return this.loadVersions(FETCH_ELEMENT_VERSIONS, id, v => {
        return Object.freeze({
          ...v,
          data: frozenParse(v.data),
          files: Object.freeze(this.files(v.files || []))
        })
      })
    }
  }
}
</script>

<template>
  <DetailAppBar v-bind="bar" :label="$gettext('Element')" :has-latest="!!latestId" />

  <v-main class="element-details" :aria-label="$gettext('Element')">
    <v-progress-linear v-if="loading" indeterminate color="primary" />
    <v-form v-else ref="form" @submit.prevent>
      <v-tabs class="detail-tabs" fixed-tabs hide-slider v-model="tab">
        <v-tab value="element" :class="{ changed: dirty, error: error }">{{
          $gettext('Element')
        }}</v-tab>
        <v-tab value="refs">{{ $gettext('Used by') }}</v-tab>
        <v-tab v-for="(sp, key) in subpanels" :key="key" :value="'ext-' + key">
          {{ label(sp) }}
        </v-tab>
      </v-tabs>

      <v-window v-model="tab" :touch="false">
        <v-window-item value="element">
          <ElementDetailItem
            @update:item="itemUpdated"
            @error="error = $event"
            :assets="assets"
            :item="item"
          />
        </v-window-item>

        <v-window-item value="refs">
          <DetailRefs :item="item" type="element" />
        </v-window-item>

        <v-window-item v-for="(sp, key) in subpanels" :key="key" :value="'ext-' + key">
          <component :is="sp.component" :item="item" :assets="assets" />
        </v-window-item>
      </v-window>
    </v-form>
  </v-main>

  <AsideMeta :item="item" />

  <Teleport to="body">
    <HistoryDialog
      v-if="vhistory"
      v-model="vhistory"
      :readonly="!user.can('element:save')"
      :current="historyCurrent"
      :load="() => versions(item.id)"
      @apply="apply"
      @use="use"
    />
    <ChangesDialog v-model="vchanged" :changed="changed"
      :targets="changeTargets"
      @resolve="dirty = true"
    />
  </Teleport>
</template>
