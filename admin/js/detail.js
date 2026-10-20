/**
 * @license MIT, https://opensource.org/license/mit
 */

import AsideMeta from './components/AsideMeta.vue'
import DetailAppBar from './components/DetailAppBar.vue'
import DetailDialogs from './components/DetailDialogs.vue'
import { setupReload } from './livesync'
import { invalidateList } from './graphql'
import { pluginLabel } from './i18n'
import { hasUnresolved, resolveChange, unresolveChange } from './merge'
import { mutation } from './lists'
import { clone, dbDate } from './utils'
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
    DetailAppBar,
    DetailDialogs
  },

  // the view edits its own copy of the initial item, so the opener's object stays untouched
  props: {
    initial: { type: Object, required: true },
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
      item: clone(this.initial),
      changed: null,
      destroyed: false,
      dirty: false,
      // number of edits, detects changes made while saving
      edits: 0,
      error: false,
      loading: true,
      publishAt: null,
      publishTime: null,
      publishing: false,
      saving: false,
      tab: this.type,
      vchanged: false,
      // snapshot of the current state compared with old versions in the history dialog
      historyData: null,
      vhistory: false
    }
  },

  created() {
    // stacked views register on top of the view below, which gets its state back on close
    this.dirtySave = () => this.save(true)
    this.dirtyStore.register(this.dirtySave)

    // non-reactive state of the running and queued save requests, not used for rendering
    this.saveQueued = null
    this.saveQuiet = false
    this.saveRun = null
    this.saveRunning = null
    this.unsubscribe = null

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
    this.dirtyStore.unregister(this.dirtySave)

    // the view below shares the side store
    if (!this.stacked) {
      this.side.$reset()
    }

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

    // shared props and listeners of the DetailDialogs
    dialogs() {
      return {
        history: this.vhistory,
        changes: this.vchanged,
        changed: this.changed,
        current: this.historyData,
        readonly: !this.user.can(`${this.type}:save`),
        load: () => this.versions(this.item.id),
        'onUpdate:history': (value) => (this.vhistory = value),
        'onUpdate:changes': (value) => (this.vchanged = value),
        onApply: this.apply,
        onUse: this.use,
        onResolve: this.resolved,
        onUnresolve: this.unresolveChange
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

    // applies the changed fields sent by the child components to the own item
    itemUpdated(patch) {
      Object.assign(this.item, patch)
      this.markChanged()
    },

    // marks the item as changed and counts the edit, so edits made while saving stay unsaved
    markChanged() {
      this.edits++
      this.dirty = true
    },

    // applies a conflict resolution chosen in the changes dialog to the item
    resolveChange({ section, key, value }) {
      resolveChange(this.changed, this.changeTargets, section, key, value)
    },

    // applies the resolution and marks the item as changed
    resolved(event) {
      this.resolveChange(event)
      this.markChanged()
    },

    unresolveChange({ section, key }) {
      unresolveChange(this.changed, this.changeTargets, section, key)
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
      // language of the page variant to publish, the source variant if none
      const lang = this.variantLang ?? null
      this.publishing = true

      this.save(true)
        .then((valid) => {
          if (!valid) return

          // edits made while saving and unresolved conflicts mustn't be marked as published
          if (this.hasConflict || this.hasChanged) {
            this.messages.add(this.$gettext('Not published, please review the changes and publish again'), 'info')
            return
          }

          return this.$apollo
            .mutate({
              mutation: mutation('pub', this.type, !!lang, true),
              variables: {
                id: [this.item.id],
                at: at ? dbDate(at) : undefined,
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
     * (model.latest is the version), the state of the version is copied afterwards. Resolves true on success so the caller can defer its
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
          if (!model.latest) throw new Error(this.$gettext('No version data available'))

          // the fetch is async: re-check now that applying is still safe - the user may have started
          // editing while it was in flight, and reset()+apply would silently wipe those edits
          if (keep && !keep()) return false

          this.reset()
          apply(model)

          this.item.published = model.latest.published
          this.item.editor = model.latest.editor
          this.item.updated_at = model.latest.created_at
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

    /**
     * Runs one save at a time, a save requested while another one is running waits for it and
     * saves the edits made in the meantime afterwards
     *
     * All requests during a running save share one follow-up save, which does nothing if the
     * item wasn't changed meanwhile. If the running save fails, the follow-up save only runs
     * when the item was edited since, otherwise the same failing save would be repeated.
     *
     * @param {(quiet: boolean) => Promise<boolean>} fcn Saves the item
     * @param {boolean} quiet If true, suppress the success message
     * @returns {Promise<boolean>}
     */
    saveOnce(fcn, quiet = false) {
      if (!this.saveRunning) {
        const run = { edits: this.edits }

        // the fields apply their changes in the next tick, so they are counted before saving
        this.saveRun = run
        this.saveRunning = this.$nextTick()
          .then(() => {
            run.edits = this.edits
            return fcn(quiet)
          })
          .finally(() => {
            this.saveRunning = null
          })

        return this.saveRunning
      }

      // the shared follow-up save is only quiet if all waiting callers asked for it
      this.saveQuiet = this.saveQueued ? this.saveQuiet && quiet : quiet

      if (!this.saveQueued) {
        const run = this.saveRun

        this.saveQueued = this.saveRunning
          .catch(() => false)
          .then((ok) => {
            this.saveQueued = null
            return ok || this.edits !== run.edits ? this.saveOnce(fcn, this.saveQuiet) : false
          })
      }

      return this.saveQueued
    },

    /**
     * Saves the item, see saveOnce() for concurrent saves
     *
     * @param {boolean} quiet If true, suppress the success message
     * @returns {Promise<boolean>}
     */
    save(quiet = false) {
      return this.saveOnce((quiet) => this.saveItem(quiet), quiet)
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

    /**
     * Saves the changed item by the mutation returned from options()
     *
     * @param {() => object} options Returns the mutate() options, runs before the sent item is copied
     * @param {(result: object, edited: boolean, sent: object) => void} done Applies the result,
     *   edited is true if the item was changed while saving and sent is the copy of the saved item
     * @param {string} message Translated error message
     * @returns {Promise<boolean>}
     */
    saveWith(options, done, message) {
      if (!this.saveable()) {
        return Promise.resolve(false)
      }

      if (!this.hasChanged) {
        return Promise.resolve(true)
      }

      const opts = options()
      const edits = this.edits
      // copy of the sent item, protects the fields edited while saving from merged values
      const sent = clone(this.item)
      this.saving = true

      return this.$apollo
        .mutate(opts)
        .then((result) => {
          done(result, this.edits !== edits, sent)
          return true
        })
        .catch((error) => {
          this.messages.error(message, error)
          return false
        })
        .finally(() => {
          this.saving = false
        })
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
      this.dirtyStore.set(value, this.dirtySave)
    },

    vhistory(val) {
      if (val) this.historyData = this.historyCurrent()
    }
  }
}

/**
 * Validates a form, scrolls to the first visible invalid input and focuses it
 *
 * Validating the form is required because inputs only show their errors after the
 * user interacted with them, e.g. empty required fields aren't highlighted otherwise.
 *
 * @param {Object} form VForm component instance
 * @returns {Promise<boolean>} True if an invalid input has been focused
 */
export async function focusInvalid(form) {
  const result = await form?.validate()

  for (const { id } of result?.errors || []) {
    const input = document.getElementById(id)

    if (input && input.getClientRects().length) {
      input.closest('.v-input')?.scrollIntoView?.({ block: 'center', behavior: 'smooth' })
      input.focus({ preventScroll: true })
      return true
    }
  }

  return false
}
