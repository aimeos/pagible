/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'

const IGNORE_CHANGES = gql`
  mutation ($id: [ID!]!, $lang: String!) {
    ignoreChanges(id: $id, lang: $lang) {
      id
    }
  }
`

const TRANSLATE_PAGE = gql`
  mutation ($id: [ID!]!, $lang: [String!]!) {
    translatePage(id: $id, lang: $lang) {
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
 * @param {Object} apollo Apollo client
 * @param {Array<String>} ids Page IDs
 * @param {Array<String>} langs Language codes
 * @returns {Promise<Object>} Batch with ID and number of queued translations
 */
export function translatePages(apollo, ids, langs) {
  return apollo.mutate({ mutation: TRANSLATE_PAGE, variables: { id: ids, lang: langs } }).then((result) => {
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
 * Requires the lang, destroyed, user, messages, confirm and languages properties, the tree ref and the
 * allowed(), selected(), missing(), mutate(), trashed(), refetch() and invalidate() methods.
 */
export const pageVariants = {
  // emitted when the translation states of the pages changed
  emits: ['changed'],

  data() {
    return {
      progress: null
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

    // polls the progress of the queued translations until all are finished and refreshes the
    // translated rows shown in the tree afterwards instead of reloading the whole tree
    poll(batch, ids = []) {
      return awaitTranslation(this.$apollo, batch, {
        onProgress: (progress) => (this.progress = progress),
        cancelled: () => this.destroyed
      })
        .then((progress) => {
          this.progress = null

          if (!progress) {
            return
          }

          // the tree may have been reloaded meanwhile, so look up the rows shown now
          const translated = new Set(ids)
          const list = (this.$refs.tree?.statsFlat || []).filter((stat) => translated.has(stat.data?.id))

          this.invalidate(true)

          if (progress.running) {
            this.messages.add(this.$gettext('Translation is still running in the background'), 'info')
          } else if (progress.failed) {
            this.messages.add(
              this.$ngettext('%{num} translation failed', '%{num} translations failed', progress.failed, {
                num: progress.failed
              }),
              'error'
            )
          } else {
            this.messages.add(this.$gettext('Translation finished'), 'success')
          }

          return list.length ? this.refetch(list) : undefined
        })
        .catch((error) => {
          this.progress = null
          this.messages.error(this.$gettext('Error fetching translation progress'), error)
        })
    },

    // pages the user can translate into the current language, missing variants require page:add
    translatable(page) {
      return (
        this.user.can('page:save') &&
        this.user.can('text:translate') &&
        !page.deleted_at &&
        !!page.source &&
        page.source !== this.lang &&
        (this.missing(page) ? this.user.can('page:add') : !page.variant_deleted_at)
      )
    },

    // queues the translations of the pages into the current language and shows the progress
    async translate(stat = null) {
      const list = stat ? [stat] : this.selected((page) => this.translatable(page))

      if (!list.length || this.progress) {
        return
      }

      if (
        !stat &&
        !(await this.confirm.ask(
          this.$gettext('Translate'),
          this.$ngettext(
            'Translate %{num} page into "%{lang}"?',
            'Translate %{num} pages into "%{lang}"?',
            list.length,
            { num: list.length, lang: this.languages.translate(this.lang) }
          ),
          [],
          this.$gettext('The translations run in the background and are saved as drafts for review.')
        ))
      ) {
        return
      }

      return translatePages(this.$apollo, list.map((item) => item.data.id), [this.lang])
        .then((batch) => {
          list.forEach((item) => (item._checked = false))

          // translations of the same pages and language which are already queued are skipped
          if (!batch.total) {
            this.messages.add(this.$gettext('Translation is already running in the background'), 'info')
            return
          }

          this.progress = { total: batch.total, done: 0, failed: 0 }
          return this.poll(batch.id, list.map((item) => item.data.id))
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error translating pages'), error, list.map((item) => item.data.id))
        })
    }
  }
}
