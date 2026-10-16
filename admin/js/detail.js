/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { defineAsyncComponent } from 'vue'
import AsideMeta from './components/AsideMeta.vue'
import DetailAppBar from './components/DetailAppBar.vue'
import { setupReload } from './echo'
import { invalidateList } from './graphql'
import { pluginLabel } from './i18n'
import { hasUnresolved } from './merge'
import { focusInvalid } from './utils'
import {
  useChangeStore,
  useDirtyStore,
  useMessageStore,
  usePluginStore,
  useSideStore,
  useUserStore,
  useViewStack
} from './stores'

/**
 * Returns the publish date from the date and the optional time string in HH:MM format
 */
function publishDate(publishAt, publishTime) {
  const at = new Date(publishAt)

  if (publishTime) {
    const [hours, minutes] = publishTime.split(':').map(Number)
    at.setHours(hours, minutes, 0, 0)
  }

  return at
}

/**
 * Returns the translated messages for publishing the item of the detail view
 */
function publishTexts(vm, at) {
  const date = at?.toLocaleDateString()

  switch (vm.type) {
    case 'element':
      return {
        success: vm.$gettext('Element published successfully'),
        scheduled: vm.$gettext('Element scheduled for publishing at %{date}', { date }),
        error: vm.$gettext('Error publishing element')
      }
    case 'file':
      return {
        success: vm.$gettext('File published successfully'),
        scheduled: vm.$gettext('File scheduled for publishing at %{date}', { date }),
        error: vm.$gettext('Error publishing file')
      }
    default:
      return {
        success: vm.$gettext('Page published successfully'),
        scheduled: vm.$gettext('Page scheduled for publishing at %{date}', { date }),
        error: vm.$gettext('Error publishing page')
      }
  }
}

/**
 * Returns the setup bindings of the page, element and file detail views
 */
export function useDetail(type) {
  return {
    type,
    changes: useChangeStore(),
    dirtyStore: useDirtyStore(),
    messages: useMessageStore(),
    side: useSideStore(),
    user: useUserStore(),
    viewStack: useViewStack()
  }
}

/**
 * Shared options of the page, element and file detail views, used with "extends"
 * The views return useDetail() from setup() and implement reload() and save()
 */
export const detailBase = {
  components: {
    AsideMeta,
    ChangesDialog: defineAsyncComponent(() => import('./components/ChangesDialog.vue')),
    DetailAppBar,
    HistoryDialog: defineAsyncComponent(() => import('./components/HistoryDialog.vue'))
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

  data() {
    return {
      changed: null,
      destroyed: false,
      dirty: false,
      error: false,
      loading: true,
      publishAt: null,
      publishTime: null,
      publishing: false,
      saving: false,
      tab: this.type,
      vchanged: false,
      vhistory: false
    }
  },

  created() {
    this.dirtyStore.register(() => this.save(true))

    if (!this.item?.id || !this.user.can(`${this.type}:view`)) {
      this.loading = false
      return
    }

    this.reload().then((ok) => {
      if (!ok || this.destroyed) return

      // reload the open item when it is saved elsewhere or after a reconnect that may have
      // missed a save, unless the user has unsaved edits
      this.unsubscribe = setupReload(this.type, this.item.id, () => this.refresh(), () => !this.hasChanged && this.user.can(`${this.type}:view`))
    })
  },

  beforeUnmount() {
    this.dirtyStore.unregister()
    this.side.$reset()

    this.destroyed = true
    this.changed = null

    this.unsubscribe?.()
  },

  computed: {
    // shared props and listeners of the DetailAppBar
    bar() {
      return {
        type: this.type,
        name: this.item.name,
        stacked: this.stacked,
        dirty: this.hasChanged,
        error: this.hasError,
        conflict: this.hasConflict,
        changed: this.changed,
        published: this.item.published,
        saving: this.saving,
        publishing: this.publishing,
        publishAt: this.publishAt,
        publishTime: this.publishTime,
        'onUpdate:publishAt': (value) => (this.publishAt = value),
        'onUpdate:publishTime': (value) => (this.publishTime = value),
        onChanges: () => (this.vchanged = true),
        onHistory: () => (this.vhistory = true),
        onPublish: (close) => this.publish(null, close),
        onSave: () => this.save(),
        onSchedule: this.schedule
      }
    },

    hasChanged() {
      return this.dirty
    },

    hasConflict() {
      return hasUnresolved(this.changed)
    },

    hasError() {
      return this.error
    },

    subpanels() {
      return usePluginStore().subpanels[this.type] || {}
    }
  },

  methods: {
    invalidate() {
      invalidateList(this.type + 's')
    },

    itemUpdated() {
      this.$emit('update:item', this.item)
      this.dirty = true
    },

    label(panel) {
      return pluginLabel(panel, this)
    },

    // fetches the versions of the item and converts each snapshot by convert(version)
    loadVersions(query, id, convert, variables = {}) {
      if (!this.user.can(this.type + ':view')) {
        this.messages.denied()
        return Promise.resolve([])
      }

      if (!id) return Promise.resolve([])

      return this.$apollo.query({ query, variables: { ...variables, id }, fetchPolicy: 'no-cache' }).then((result) => {
        if (!result.data?.[this.type]) throw result
        return (result.data[this.type].versions || []).map(convert)
      })
    },

    /**
     * Saves and publishes the item now or at the given date
     *
     * @param {Date|null} at Schedule date or null for immediate publish
     * @param {Boolean} close Close the view after publishing
     */
    publish(at = null, close = false) {
      if (!this.user.can(`${this.type}:publish`)) return this.messages.denied()

      const msgs = publishTexts(this, at)
      const model = this.type[0].toUpperCase() + this.type.slice(1)
      // language of the page variant to publish, the source variant if none
      const lang = this.variantLang ?? null
      this.publishing = true

      this.save(true)
        .then((valid) => {
          if (!valid || this.changed) return

          return this.$apollo
            .mutate({
              mutation: lang
                ? gql`mutation ($id: [ID!]!, $at: DateTime, $lang: String) { pub${model}(id: $id, at: $at, lang: $lang) { id } }`
                : gql`mutation ($id: [ID!]!, $at: DateTime) { pub${model}(id: $id, at: $at) { id } }`,
              variables: {
                id: [this.item.id],
                at: at?.toISOString()?.substring(0, 19)?.replace('T', ' '),
                ...(lang && { lang })
              }
            })
            .then(() => {
              if (!at) {
                this.item.published = true
                this.messages.add(msgs.success, 'success')
              } else {
                this.item.publish_at = at
                this.messages.add(msgs.scheduled, 'info')
              }

              this.invalidate()
              this.changes.notify(this.type, this.item)

              if (close) {
                if (this.stacked) {
                  this.viewStack.closeView()
                } else {
                  this.$router.push({ name: `${this.type}:view` })
                }
              }
            })
            .catch((error) => {
              this.messages.error(msgs.error, error, at)
            })
        })
        .finally(() => {
          this.publishing = false
        })
    },

    refresh() {
      return this.reload()
    },

    /**
     * Loads the latest version of the item and applies it
     *
     * The per-model field mapping is done by apply(model), which receives the queried item
     * (model.latest is the version). Resolves true on success so the caller can defer its
     * websocket subscription until the load completed.
     *
     * @param {object} query GraphQL query document
     * @param {string} message Translated, user-facing error message
     * @param {(model: object) => void} apply Applies the fetched item to the component
     * @param {(() => boolean)|null} keep Optional guard re-checked after the fetch; if it returns
     *   false (e.g. the user started editing while the request was in flight) the result is discarded
     *   so unsaved edits are not overwritten
     * @param {object} variables Additional GraphQL variables
     * @returns {Promise<boolean>}
     */
    reloadVersion(query, message, apply, keep = null, variables = {}) {
      return this.$apollo
        .query({ query, fetchPolicy: 'no-cache', variables: { ...variables, id: this.item.id } })
        .then((result) => {
          if (this.destroyed) return false

          const model = result.data?.[this.type]

          if (!model) throw new Error(this.$gettext('No data available'))
          if (!model.latest) throw new Error('No version data available')

          // the fetch is async: re-check now that applying is still safe - the user may have started
          // editing while it was in flight, and reset()+apply would silently wipe those edits
          if (keep && !keep()) return false

          this.reset()
          apply(model)
          this.loading = false
          return true
        })
        .catch((error) => {
          const detail = error?.message || ''

          this.loading = false
          this.messages.add(message + (detail ? ':\n' + detail : ''), 'error')
          this.$log('reloadVersion(' + this.type + ')', error)
          return false
        })
    },

    reset() {
      this.dirty = false
      this.changed = null
      this.error = false
    },

    // checks the permission and the field errors before saving
    saveable() {
      if (!this.user.can(`${this.type}:save`)) return this.messages.denied()

      if (this.hasError) {
        this.messages.add(
          this.$gettext('There are invalid fields, please resolve the errors first'),
          'error'
        )
        this.showError()
        return false
      }

      return true
    },

    // applies the saved version to the item and notifies the lists
    saved(version) {
      this.item.published = version?.published ?? false
      this.item.publish_at = version?.publish_at ?? null
      this.item.editor = version?.editor ?? this.item.editor
      this.item.updated_at = version?.created_at ?? this.item.updated_at

      this.invalidate()
      this.changes.notify(this.type, this.item)
    },

    schedule(close = false) {
      this.publish(publishDate(this.publishAt, this.publishTime), close)
    },

    showError() {
      this.tab = this.type
      this.$nextTick(() => focusInvalid(this.$refs.form))
    },

    translateText(texts, to, from = null) {
      return import('./ai').then(({ translate }) => translate(texts, to, from || this.item.lang))
    },

    // item specific context for the AI text generation
    writeContext() {
      return this.type + ' data as JSON: ' + JSON.stringify(this.item.data)
    },

    writeText(prompt, context = [], files = []) {
      context = [].concat(context, this.writeContext(), 'required output language: ' + (this.item.lang || 'en'))
      return import('./ai').then(({ write }) => write(prompt, context, files))
    }
  },

  watch: {
    hasChanged(value) {
      this.dirtyStore.set(value)
    }
  }
}
