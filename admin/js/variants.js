/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { toRaw } from 'vue'
import { useTranslationStore } from './stores'

const IGNORE_CHANGES = gql`
  mutation ($id: [ID!]!, $lang: String!) {
    ignoreChanges(id: $id, lang: $lang) {
      id
    }
  }
`

const TRANSLATE_PAGE = gql`
  mutation ($id: [ID!], $filter: PageFilter, $publish: Publish, $filterLang: String, $lang: [String!]!) {
    translatePage(id: $id, filter: $filter, publish: $publish, filter_lang: $filterLang, lang: $lang) {
      id
      total
    }
  }
`

const TRANSLATE_PROGRESS = gql`
  query ($batch: ID!) {
    translateProgress(batch: $batch) {
      total
      done
      failed
    }
  }
`

/**
 * Queues the translations of the pages into the languages.
 *
 * Only missing and outdated translations are queued, pages in their source language and
 * up to date translations are skipped by the server.
 *
 * @param {Object} apollo Apollo client
 * @param {Array<String>|Object} pages Page IDs or the page list filter as { filter, publish, filterLang } object
 * @param {Array<String>} langs Language codes
 * @returns {Promise<Object>} Batch with ID and number of queued translations
 */
export function translatePages(apollo, pages, langs) {
  const variables = Array.isArray(pages) ? { id: pages } : { ...pages }

  return apollo.mutate({ mutation: TRANSLATE_PAGE, variables: { ...variables, lang: langs } }).then((result) => {
    if (!result.data?.translatePage) {
      throw new Error('No data in translatePage mutation result')
    }
    return result.data.translatePage
  })
}

/**
 * Returns the progress of queued translations.
 *
 * @param {Object} apollo Apollo client
 * @param {String} batch Batch ID
 * @returns {Promise<Object|null>} Total, done and failed translations or NULL if the batch is unknown
 */
function translateProgress(apollo, batch) {
  return apollo
    .query({ query: TRANSLATE_PROGRESS, variables: { batch }, fetchPolicy: 'no-cache' })
    .then((result) => result.data?.translateProgress || null)
}

/**
 * Polls the progress of queued translations until all are finished.
 *
 * Polls every two seconds and backs off up to 30 seconds while the progress doesn't change.
 * Polling pauses while the browser tab is hidden and stops after 20 polls without any progress.
 *
 * @param {Object} apollo Apollo client
 * @param {String} batch Batch ID
 * @param {Object} options Optional onProgress(progress) callback and cancelled() function stopping the polling
 * @returns {Promise<Object|null>} Final progress, the last progress with "running: true" if the translations
 *  didn't progress any more, or NULL if the batch is unknown or the polling was cancelled
 */
export async function awaitTranslation(apollo, batch, { onProgress = null, cancelled = () => false } = {}) {
  let delay = 2000
  let unchanged = 0
  let last = null

  while (!cancelled()) {
    if (document.hidden) {
      await new Promise((resolve) => setTimeout(resolve, delay))
      continue
    }

    const progress = await translateProgress(apollo, batch)

    if (!progress || cancelled()) {
      return null
    }

    onProgress?.(progress)

    if (progress.done + progress.failed >= progress.total) {
      return progress
    }

    if (last && progress.done === last.done && progress.failed === last.failed) {
      if (++unchanged >= 20) {
        return { ...progress, running: true }
      }
      delay = Math.min(delay * 2, 30000)
    } else {
      unchanged = 0
      delay = 2000
    }

    last = progress
    await new Promise((resolve) => setTimeout(resolve, delay))
  }

  return null
}

/**
 * Marks the variants in the language as up to date without changing their content.
 *
 * @param {Object} apollo Apollo client
 * @param {Array<String>} ids Page IDs
 * @param {String} lang Language code
 * @returns {Promise<Object>} Mutation result
 */
export function ignoreChanges(apollo, ids, lang) {
  return apollo.mutate({ mutation: IGNORE_CHANGES, variables: { id: ids, lang } })
}

/**
 * Actions on the page variants in the current language of the page tree.
 *
 * Emits "changed" when the translation states changed.
 * Requires the lang, user, messages, confirm and languages properties, the tree ref and the
 * allowed(), selected(), missing(), mutate(), trashed(), refetch(), invalidate(), filters() and total() methods.
 *
 * The queued translations are tracked by the translation store, so several can run at the same
 * time and their progress is still shown after navigating away and back or reloading the tab.
 */
export const pageVariants = {
  // emitted when the translation states of the pages changed
  emits: ['changed'],

  data() {
    return {
      translateDialog: false,
      translateItems: [],
      translateTotal: null
    }
  },

  mounted() {
    this.unlisten = this.translationStore.listen((batch, progress) => this.translated(batch, progress))
  },

  beforeUnmount() {
    this.unlisten?.()
  },

  computed: {
    // summed up progress of all queued translations of the browser tab
    progress() {
      return this.translationStore.progress
    },

    translationStore() {
      return useTranslationStore()
    },

    // languages checked in the translate dialog, the current one unless it's the source of all pages
    translateLangs() {
      return this.translateItems.some((stat) => stat.data?.source !== this.lang) ? [this.lang] : []
    }
  },

  methods: {
    // moves the variants in the current language to the trash, source variants are skipped
    async dropLang(stat = null) {
      const all = this.allowed('drop') ? (stat ? [stat] : this.selected((page) => !this.missing(page))) : []
      const list = all.filter((item) => item.data.lang !== item.data.source && !item.data.variant_deleted_at)
      const skipped = all.filter((item) => item.data.lang === item.data.source).length

      if (!list.length) {
        if (skipped) {
          this.messages.add(this.$gettext('Pages in their source language are deleted as whole pages only'), 'info')
        }
        return
      }

      if (
        !stat &&
        !(await this.confirm.ask(
          this.$gettext('Delete language'),
          this.$ngettext(
            'Move %{num} page in "%{lang}" to the trash?',
            'Move %{num} pages in "%{lang}" to the trash?',
            list.length,
            { num: list.length, lang: this.languages.translate(this.lang) }
          ),
          [],
          skipped
            ? this.$ngettext(
                '%{num} page in its source language is skipped.',
                '%{num} pages in their source language are skipped.',
                skipped,
                { num: skipped }
              )
            : ''
        ))
      ) {
        return
      }

      const ids = list.map((item) => item.data.id)

      return this.mutate('drop', ids, this.$gettext('Error trashing language'), this.lang).then((ok) => {
        if (ok) {
          this.trashed(ids, this.lang)
          return this.refetch(list)
        }
      })
    },

    // restores the trashed variants in the current language
    keepLang(stat = null) {
      const list = this.allowed('keep') ? (stat ? [stat] : this.selected((page) => page.variant_deleted_at && !this.missing(page))) : []

      if (!list.length) {
        return
      }

      this.mutate('keep', list.map((item) => item.data.id), this.$gettext('Error restoring language'), this.lang).then((ok) => {
        ok && this.refetch(list)
      })
    },

    // permanently removes the variants in the current language, source variants are skipped
    async purgeLang(stat = null) {
      const list = (this.allowed('purge') ? (stat ? [stat] : this.selected((page) => !this.missing(page))) : [])
        .filter((item) => item.data.lang !== item.data.source)

      if (
        !list.length ||
        !(await this.confirm.purge(
          list.map((stat) => ({
            name: stat.data.name + ' (' + this.languages.translate(this.lang) + ')',
            info: '/' + (stat.data.path || '')
          }))
        ))
      ) {
        return
      }

      this.mutate('purge', list.map((item) => item.data.id), this.$gettext('Error purging language'), this.lang).then((ok) => {
        ok && this.refetch(list)
      })
    },

    // marks the variants in the current language as up to date without changing their content
    ignore(stat = null) {
      const list = this.user.can('page:save') ? (stat ? [stat] : this.selected((page) => this.isStale(page))) : []

      if (!list.length) {
        return
      }

      return ignoreChanges(this.$apollo, list.map((item) => item.data.id), this.lang)
        .then(() => {
          for (const item of list) {
            item.data.stale = false
            item._checked = false
          }
          this.invalidate(true)
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error ignoring changes'), error, list.map((item) => item.data.id))
        })
    },

    // variant in the current language whose source changed since it was translated
    isStale(page) {
      return !!page.stale && page.lang !== page.source && !this.missing(page)
    },

    // refreshes the translated rows shown in the tree instead of reloading the whole tree
    translated(batch) {
      // the tree may have been reloaded meanwhile, so look up the rows shown now
      const ids = new Set(batch.ids)
      const list = (this.$refs.tree?.statsFlat || []).filter((stat) => ids.has(stat.data?.id))

      this.invalidate(true)
      return list.length ? this.refetch(list) : undefined
    },

    // pages the user can translate into other languages, the server skips the source and
    // up to date variants as well as missing ones if the user isn't allowed to add pages
    translatable(page) {
      return (
        this.languages.available.length > 1 &&
        this.user.can('page:save') &&
        this.user.can('text:translate') &&
        !page.deleted_at &&
        !!page.source
      )
    },

    // opens the dialog for translating the page or the selected pages into one or more languages
    translate(stat = null) {
      const list = stat ? [stat] : this.selected((page) => this.translatable(page))

      if (!list.length) {
        return
      }

      this.translateItems = list
      this.translateTotal = null
      this.translateDialog = true

      // offer translating all pages matching the filter instead of the loaded ones only
      if (!stat) {
        this.total()
          .then((num) => {
            if (toRaw(this.translateItems) === list) {
              this.translateTotal = num
            }
          })
          .catch(() => {})
      }
    },

    // queues the translations of the selected pages or all pages matching the filter into the
    // languages, the translation store shows the progress and refreshes the rows afterwards
    translateApply({ langs, all }) {
      const list = this.translateItems
      const ids = list.map((item) => item.data.id)
      // in the list view, the rows shown are refreshed afterwards
      const shown = all ? (this.$refs.tree?.statsFlat || []).map((stat) => stat.data?.id).filter(Boolean) : ids

      if (!list.length || !langs.length) {
        return
      }

      return translatePages(this.$apollo, all ? this.filters() : ids, langs)
        .then((batch) => {
          list.forEach((item) => (item._checked = false))

          // translations which are up to date or already queued are skipped
          if (!batch.total) {
            this.messages.add(this.$gettext('Nothing to translate, the pages are up to date or already being translated'), 'info')
            return
          }

          return this.translationStore.add(batch, { ids: shown, langs }, this.$apollo)
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error translating pages'), error, all ? this.filters() : ids)
        })
    }
  }
}
