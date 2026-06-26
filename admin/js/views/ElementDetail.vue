/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import AsideMeta from '../components/AsideMeta.vue'
import DetailAppBar from '../components/DetailAppBar.vue'
import ElementDetailRefs from '../components/ElementDetailRefs.vue'
import ElementDetailItem from '../components/ElementDetailItem.vue'
import { useDirtyStore, useSideStore, useUserStore, useMessageStore, usePluginStore, useViewStack, useChangeStore } from '../stores'
import { applyResult, hasUnresolved } from '../merge'
import { publishDate, publishItem } from '../publish'
import { setupReload, cleanEcho } from '../echo'
import { reloadVersion } from '../version'
import { defineAsyncComponent, markRaw } from 'vue'
import { frozenParse, itemTitle, safeParse } from '../utils'

const ChangesDialog = defineAsyncComponent(() => import('../components/ChangesDialog.vue'))
const HistoryDialog = defineAsyncComponent(() => import('../components/HistoryDialog.vue'))

const FETCH_ELEMENT = gql`
  query ($id: ID!) {
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
  }
`

export default {
  components: {
    AsideMeta,
    ChangesDialog,
    DetailAppBar,
    HistoryDialog,
    ElementDetailRefs,
    ElementDetailItem
  },

  props: {
    item: { type: Object, required: true },
    stacked: { type: Boolean, default: false }
  },

  provide() {
    return {
      write: this.writeText,
      translate: this.translateText
    }
  },

  data: () => ({
    assets: {},
    changed: null,
    destroyed: false,
    dirty: false,
    echoCleanup: null,
    echoPromise: null,
    error: false,
    latestId: null,
    loading: true,
    publishAt: null,
    publishTime: null,
    publishing: false,
    saving: false,
    vchanged: false,
    vhistory: false,
    tab: 'element'
  }),

  setup() {
    const dirtyStore = useDirtyStore()
    const messages = useMessageStore()
    const side = useSideStore()
    const user = useUserStore()
    const viewStack = useViewStack()
    const changes = useChangeStore()

    return {
      dirtyStore,
      side,
      user,
      messages,
      viewStack,
      changes
    }
  },

  created() {
    this.dirtyStore.register(() => this.save(true))

    if (!this.item?.id || !this.user.can('element:view')) {
      this.loading = false
      return
    }

    this.reload().then((ok) => {
      if (!ok) return

      // reload the open element when its own item is saved elsewhere or after a reconnect that
      // may have missed a save, unless the user has unsaved edits
      setupReload(this, 'element', this.item.id, () => this.reload(), () => !this.dirty && this.user.can('element:view'))
    })
  },

  beforeUnmount() {
    this.dirtyStore.unregister()
    this.side.$reset()

    this.assets = markRaw({})
    this.destroyed = true
    this.changed = null

    cleanEcho(this)
  },

  computed: {
    changeTargets() {
      return markRaw({ data: this.item })
    },

    subpanels() {
      return usePluginStore().subpanels.element || {}
    },

    hasConflict() {
      return hasUnresolved(this.changed)
    },

    historyCurrent() {
      const item = this.item
      const ids = new Set(item.files || [])
      const files = {}

      for (const key in this.assets) {
        if (ids.has(key)) files[key] = this.assets[key]
      }

      return markRaw({
        data: Object.freeze({
          lang: item.lang,
          type: item.type,
          name: item.name,
          data: item.data
        }),
        files: markRaw(files)
      })
    }
  },

  methods: {
    // loads the latest version into the open editor; resolves true on success so the caller
    // can defer the websocket subscription until the initial load completed
    reload() {
      return reloadVersion(this, FETCH_ELEMENT, 'element', this.$gettext('Error fetching element'), (element) => {
        Object.assign(this.item, safeParse(element.latest?.data))
        this.item.published = element.latest?.published
        this.item.editor = element.latest?.editor
        this.item.updated_at = element.latest?.created_at
        this.latestId = element.latest?.id

        const files = []
        const assets = {}

        for (const entry of element.latest?.files || element.files || []) {
          assets[entry.id] = { ...entry, previews: frozenParse(entry.previews) }
          files.push(entry.id)
        }

        this.assets = markRaw(assets)
        this.item.files = files
      }, () => !this.dirty)
    },

    apply(changes) {
      Object.assign(this.item, changes)
      this.dirty = true
      this.vhistory = false
    },

    errorUpdated(event) {
      this.error = event
    },

    files(entries) {
      const map = {}

      for (const entry of entries) {
        map[entry.id] = { ...entry, previews: frozenParse(entry.previews) }
      }

      return map
    },

    itemUpdated() {
      this.$emit('update:item', this.item)
      this.dirty = true
    },

    loadVersions() {
      return this.versions(this.item.id)
    },

    publish(at = null) {
      publishItem(this, 'element', {
        success: this.$gettext('Element published successfully'),
        scheduled: (d) => this.$gettext('Element scheduled for publishing at %{date}', { date: d.toLocaleDateString() }),
        error: this.$gettext('Error publishing element')
      }, at)
    },

    published() {
      this.publish(publishDate(this.publishAt, this.publishTime))
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
      if (!this.user.can('element:save')) {
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

      if (!this.item.name) {
        this.item.name = this.title(this.item.data)
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
          if (result.errors) {
            throw result.errors
          }

          const el = result.data?.saveElement
          const changed = el?.changed ? markRaw(safeParse(el.changed)) : null

          if (changed?.latest?.id || el?.latest?.id) {
            this.latestId = changed?.latest?.id ?? el.latest.id
          }

          applyResult(this, changed, this.$gettext('Element saved successfully'), quiet)

          const version = el?.latest
          this.item.published = version?.published ?? false
          this.item.publish_at = version?.publish_at ?? null
          this.item.editor = version?.editor ?? this.item.editor
          this.item.updated_at = version?.created_at ?? this.item.updated_at
          this.item.latestId = this.latestId
          this.changes.notify('element', this.item)

          return true
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error saving element') + ':\n' + error, 'error')
          this.$log(`ElementDetail::save(): Error saving element`, error)
        })
        .finally(() => {
          this.saving = false
        })
    },

    title(data) {
      return itemTitle(data)
    },

    writeText(prompt, context = [], files = []) {
      if (!Array.isArray(context)) {
        context = [context]
      }

      context.push('element data as JSON: ' + JSON.stringify(this.item.data))
      context.push('required output language: ' + (this.item.lang || 'en'))

      return import('../ai').then(({ write }) => write(prompt, context, files))
    },

    use(version) {
      Object.assign(this.item, version.data)

      this.assets = version.files || {}
      this.item.files = Object.keys(version.files || {})

      this.vhistory = false
      this.dirty = true
    },

    translateText(texts, to, from = null) {
      return import('../ai').then(({ translate }) => translate(texts, to, from || this.item.lang))
    },

    versions(id) {
      if (!this.user.can('element:view')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return Promise.resolve([])
      }

      if (!id) {
        return Promise.resolve([])
      }

      return this.$apollo
        .query({
          query: FETCH_ELEMENT_VERSIONS,
          fetchPolicy: 'no-cache',
          variables: {
            id: id
          }
        })
        .then((result) => {
          if (result.errors || !result.data.element) {
            throw result
          }

          return (result.data.element.versions || []).map((v) => {
            return Object.freeze({
              ...v,
              data: frozenParse(v.data),
              files: Object.freeze(this.files(v.files || []))
            })
          })
        })
        .catch((error) => {
          this.messages.add(
            this.$gettext('Error fetching element versions') + ':\n' + error,
            'error'
          )
          this.$log(`ElementDetail::versions(): Error fetching element versions`, id, error)
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
    type="element"
    :label="$gettext('Element')"
    :name="item.name"
    :stacked="stacked"
    :dirty="dirty"
    :error="error"
    :conflict="hasConflict"
    :changed="changed"
    :published="item.published"
    :has-latest="!!latestId"
    :saving="saving"
    :publishing="publishing"
    v-model:publish-at="publishAt"
    v-model:publish-time="publishTime"
    @save="save()"
    @publish="publish()"
    @schedule="published"
    @history="vhistory = true"
    @changes="vchanged = true"
  />

  <v-main class="element-details" :aria-label="$gettext('Element')">
    <v-progress-linear v-if="loading" indeterminate color="primary" />
    <v-form v-else @submit.prevent>
      <v-tabs fixed-tabs v-model="tab">
        <v-tab value="element" :class="{ changed: dirty, error: error }">{{
          $gettext('Element')
        }}</v-tab>
        <v-tab value="refs">{{ $gettext('Used by') }}</v-tab>
        <v-tab v-for="(sp, key) in subpanels" :key="key" :value="'ext-' + key">
          {{ sp.label }}
        </v-tab>
      </v-tabs>

      <v-window v-model="tab" :touch="false">
        <v-window-item value="element">
          <ElementDetailItem
            @update:item="itemUpdated"
            @error="errorUpdated"
            :assets="assets"
            :item="item"
          />
        </v-window-item>

        <v-window-item value="refs">
          <ElementDetailRefs :item="item" />
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
      v-model="vhistory"
      :readonly="!user.can('element:save')"
      :current="historyCurrent"
      :load="loadVersions"
      @revert="revertVersion"
      @apply="apply"
      @use="use($event)"
    />
    <ChangesDialog v-model="vchanged" :changed="changed"
      :targets="changeTargets"
      @resolve="dirty = true"
    />
  </Teleport>
</template>
