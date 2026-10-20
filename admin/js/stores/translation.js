/**
 * @license MIT, https://opensource.org/license/mit
 */

import { defineStore } from 'pinia'
import { apolloClient } from '../graphql'
import gettext from '../i18n'
import { safeParse } from '../json'
import { awaitTranslation } from '../translate'
import { useMessageStore } from './message'

/**
 * Queued page translations of the browser tab, polled independently of the views
 *
 * The batches are kept in the session storage, so their progress is shown again and the
 * views are notified when they are finished even after navigating away or reloading the tab.
 */
const TRANSLATIONS_KEY = 'cms-translations'
const translationListeners = new Set()
const translationPolls = new Map()

function storedTranslations() {
  try {
    const list = safeParse(sessionStorage.getItem(TRANSLATIONS_KEY), [])
    return Array.isArray(list)
      ? list
          .filter((batch) => typeof batch?.id === 'string')
          .map((batch) => ({
            ...batch,
            ids: Array.isArray(batch.ids) ? batch.ids : [],
            langs: Array.isArray(batch.langs) ? batch.langs : []
          }))
      : []
  } catch {
    return [] // storage not available, e.g. in private mode
  }
}

export const useTranslationStore = defineStore('translation', {
  state: () => ({
    batches: storedTranslations()
  }),

  getters: {
    // summed up progress of all running batches or NULL if there are none
    progress(state) {
      if (!state.batches.length) {
        return null
      }

      return state.batches.reduce(
        (sum, batch) => ({
          total: sum.total + (batch.total || 0),
          done: sum.done + (batch.done || 0),
          failed: sum.failed + (batch.failed || 0)
        }),
        { total: 0, done: 0, failed: 0 }
      )
    }
  },

  actions: {
    /**
     * Tracks a queued batch until all of its translations are finished
     *
     * @param {Object} batch Batch with ID and number of queued translations
     * @param {Object} info Translated page IDs ("ids"), languages ("langs") and the message shown when finished ("success")
     * @param {Object} apollo Apollo client
     * @returns {Promise<Object|null>} Final progress like awaitTranslation()
     */
    add(batch, { ids = [], langs = [], success = '' } = {}, apollo = apolloClient) {
      if (!this.batches.some((entry) => entry.id === batch.id)) {
        this.batches.push({ id: batch.id, total: batch.total || 0, done: 0, failed: 0, ids, langs, success })
        this.save()
      }

      return this.poll(batch.id, apollo)
    },

    // languages the page is translated into right now
    langsOf(id) {
      const langs = this.batches.filter((batch) => batch.ids.includes(id)).flatMap((batch) => batch.langs)
      return [...new Set(langs)]
    },

    // stops tracking all batches, e.g. after logging out
    clear() {
      this.batches = []
      translationPolls.clear()
      this.save()
    },

    // if the page is translated into the language (or any language without one) right now
    has(id, lang = null) {
      return this.batches.some((batch) => batch.ids.includes(id) && (!lang || batch.langs.includes(lang)))
    },

    /**
     * Registers a function called with the batch and its final progress when a batch is finished
     *
     * @param {Function} fn Callback which may return a promise awaited before the batch is reported finished
     * @returns {Function} Function removing the callback again
     */
    listen(fn) {
      translationListeners.add(fn)
      return () => translationListeners.delete(fn)
    },

    poll(id, apollo = apolloClient) {
      if (translationPolls.has(id)) {
        return translationPolls.get(id)
      }

      const messages = useMessageStore()
      // after clear(), the poll of a re-added batch with the same ID owns the batch
      // (the first check runs synchronously before the promise is assigned and registered)
      let promise = null
      const owner = () => !promise || translationPolls.get(id) === promise
      promise = awaitTranslation(apollo, id, {
        // batches removed from the store aren't tracked anymore
        cancelled: () => !owner() || !this.batches.some((entry) => entry.id === id),
        onProgress: (progress) => {
          const batch = owner() && this.batches.find((entry) => entry.id === id)

          if (batch) {
            Object.assign(batch, { total: progress.total, done: progress.done, failed: progress.failed })
            this.save()
          }
        }
      })
        .catch((error) => {
          messages.error(gettext.$gettext('Error fetching translation progress'), error)
          return null
        })
        .then(async (progress) => {
          // after clear(), a newer poll of a re-added batch owns the batch and its entry
          if (translationPolls.get(id) !== promise) {
            return progress
          }

          const batch = this.batches.find((entry) => entry.id === id)

          translationPolls.delete(id)
          this.batches = this.batches.filter((entry) => entry.id !== id)
          this.save()

          // unknown batches have expired on the server, there's nothing to report
          if (!batch || !progress) {
            return progress
          }

          await Promise.all(
            [...translationListeners].map((fn) =>
              Promise.resolve()
                .then(() => fn(batch, progress))
                .catch((error) => console.warn('Translation listener failed:', error))
            )
          )

          if (progress.running) {
            messages.add(gettext.$gettext('Translation is still running in the background'), 'info')
          } else if (progress.failed) {
            messages.add(
              gettext.$ngettext('%{num} translation failed', '%{num} translations failed', progress.failed, {
                num: progress.failed
              }),
              'error'
            )
          } else {
            messages.add(batch.success || gettext.$gettext('Translation finished'), 'success')
          }

          return progress
        })

      translationPolls.set(id, promise)
      return promise
    },

    // continues polling the batches of the tab, e.g. after reloading the page
    resume(apollo = apolloClient) {
      this.batches.forEach((batch) => this.poll(batch.id, apollo))
    },

    save() {
      try {
        sessionStorage.setItem(TRANSLATIONS_KEY, JSON.stringify(this.batches))
      } catch {
        // the progress isn't restored after reloading the tab
      }
    }
  }
})
