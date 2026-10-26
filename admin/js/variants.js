/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { useLanguageStore, useTranslationStore } from './stores'
import { ADD_PAGE_VARIANT } from './pages'
import { pendingLangs, translatePages, writableLangs } from './translate'

const IGNORE_CHANGES = gql`
  mutation ($id: [ID!]!, $lang: String!) {
    ignoreChanges(id: $id, lang: $lang) {
      id
    }
  }
`

/**
 * Returns if the user is allowed to translate pages
 *
 * @param {Object} user User store
 * @returns {boolean} TRUE if pages can be translated
 */
export function canTranslate(user) {
  return user.can('page:save') && user.can('text:translate')
}

/**
 * Queues the translation of new variants, which are untranslated copies of the source until then.
 *
 * Nothing is queued if the user isn't allowed to translate pages. The translation store tracks
 * the progress, reports the result and notifies its listeners when the translation is finished.
 *
 * @param {Object} apollo Apollo client
 * @param {Object} user User store
 * @param {Array<String>} ids Page IDs
 * @param {String} lang Language code of the new variants
 * @param {String} success Message shown when the translation is finished
 * @returns {Promise<Object|null>} Batch or NULL if nothing was queued
 */
export function translateNew(apollo, user, ids, lang, success = '') {
  if (!canTranslate(user)) {
    return Promise.resolve(null)
  }

  return translatePages(apollo, ids, [lang]).then((batch) => {
    if (!batch.total) {
      return null
    }

    // the store outlives the component, so it polls with the default client instead of the component's one
    useTranslationStore().add(batch, { ids, langs: [lang], success })
    return batch
  })
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
 * allowed(), selected(), missing(), mutate(), trashed(), refetch() and invalidate() methods.
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
      // languages the selected pages need translations in, NULL while unknown
      translatePending: null
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
    },

    // languages hidden in the translate dialog because they are the source of all pages
    translateSources() {
      const sources = new Set(this.translateItems.map((stat) => stat.data?.source))
      return sources.size === 1 ? [...sources].filter(Boolean) : []
    }
  },

  methods: {
    // moves the variants in the current language to the trash, source variants are skipped
    async dropLang(stat = null) {
      const all = this.allowed('drop', 'variant', this.lang) ? this.targets(stat, (page) => !this.missing(page)) : []
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

      return this.mutate('drop', ids, this.$gettext('Error trashing language'), this.lang, 'pageVariant').then((ok) => {
        if (ok) {
          this.trashed(ids, this.lang)
          return this.refetch(list)
        }
      })
    },

    // restores the trashed variants in the current language
    keepLang(stat = null) {
      const list = this.allowed('keep', 'variant', this.lang) ? this.targets(stat, (page) => this.restorable(page)) : []

      if (!list.length) {
        return
      }

      this.mutate('keep', list.map((item) => item.data.id), this.$gettext('Error restoring language'), this.lang, 'pageVariant').then((ok) => {
        ok && this.refetch(list)
      })
    },

    // permanently removes the variants in the current language incl. the trashed ones, source variants are skipped
    async purgeLang(stat = null) {
      const list = (this.allowed('purge', 'variant', this.lang) ? this.targets(stat) : []).filter((item) => this.purgeable(item.data))

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

      this.mutate('purge', list.map((item) => item.data.id), this.$gettext('Error purging language'), this.lang, 'pageVariant').then((ok) => {
        ok && this.refetch(list)
      })
    },

    // marks the variants in the current language as up to date without changing their content
    ignore(stat = null) {
      const list = this.allowed('save', 'page', this.lang) ? this.targets(stat, (page) => this.isStale(page)) : []

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

    // variant in the current language which isn't the source, shown itself or trashed and replaced by the source variant
    purgeable(page) {
      return page.source !== this.lang && (this.missing(page) ? !!page.variant_trashed : true)
    },

    // trashed variant in the current language, shown itself or replaced by the source variant
    restorable(page) {
      return this.missing(page) ? !!page.variant_trashed : !!page.variant_deleted_at
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
        canTranslate(this.user) &&
        !page.deleted_at &&
        !!page.source
      )
    },

    // opens the dialog for translating the page or the selected pages into one or more languages
    translate(stat = null) {
      const list = this.targets(stat, (page) => this.translatable(page))

      if (!list.length) {
        return
      }

      const ids = list.map((stat) => stat.data.id)
      // ignores the result if the dialog has been opened for other pages in the meantime
      const token = (this.translateToken = (this.translateToken || 0) + 1)

      this.translateItems = list
      this.translatePending = null
      this.translateDialog = true

      // all languages stay selectable if the states can't be fetched, the server skips the others anyway
      pendingLangs(this.$apollo, this.user, ids, (id) => this.translationStore.langsOf(id))
        .then((langs) => {
          if (this.translateToken === token) {
            this.translatePending = langs
          }
        })
        .catch(() => {
          if (this.translateToken === token) {
            this.translatePending = writableLangs(this.user, this.languages.available)
          }
        })
    },

    // queues the translations of the selected pages into the languages,
    // the translation store shows the progress and refreshes the rows afterwards
    translateApply({ langs }) {
      const list = this.translateItems
      const ids = list.map((item) => item.data.id)

      if (!list.length || !langs.length) {
        return
      }

      return translatePages(this.$apollo, ids, langs)
        .then((batch) => {
          list.forEach((item) => (item._checked = false))

          // translations which are up to date or already queued are skipped
          if (!batch.total) {
            this.messages.add(this.$gettext('Nothing to translate, the pages are up to date or already being translated'), 'info')
            return
          }

          return this.translationStore.add(batch, { ids, langs })
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error translating pages'), error, ids)
        })
    }
  }
}

/**
 * Language variants of the page shown in the page editor: switching between them, adding missing
 * ones and translating the changes of the source variant
 *
 * Requires the item and stacked properties, the user and messages properties and the hasChanged
 * computed property as well as the invalidate(), refresh() and save() methods. The language of
 * the edited variant is "variantLang", NULL for the source variant.
 */
export const detailVariants = {
  data() {
    return {
      switching: false,
      translating: false,
      variantLang: (!this.stacked && this.$route?.query?.lang) || null
    }
  },

  mounted() {
    this.unlisten = this.translationStore.listen((batch, progress) => this.translated(batch, progress))
  },

  beforeUnmount() {
    this.unlisten?.()
  },

  computed: {
    // the variant isn't the source variant and can be updated from it
    isTranslation() {
      return !!this.item.source && !!this.item.lang && this.item.lang !== this.item.source
    },

    // the variant is queued for translation or the translation is requested right now
    isTranslating() {
      return this.translating || this.translationStore.has(this.item.id, this.item.lang)
    },

    // all site languages and those of the existing variants with their state
    langs() {
      const map = new Map((this.item.variants || []).map((v) => [v.lang, v]))
      const codes = [...new Set([...this.languages.available, ...map.keys()])]

      return codes.map((code) => ({
        code,
        name: this.languages.translate(code),
        source: !!map.get(code)?.source,
        state: map.get(code)?.state || 'missing'
      }))
    },

    languages() {
      return useLanguageStore()
    },

    translationStore() {
      return useTranslationStore()
    }
  },

  methods: {
    // updates the stale state of the edited variant
    setVariantState(stale) {
      const state = stale ? 'stale' : 'current'

      this.item.stale = stale
      this.item.variants = (this.item.variants || []).map((v) => (v.lang === this.item.lang ? { ...v, state } : v))
    },

    // switches the editor to the variant in the language, missing variants are created as untranslated
    // copy and translated in the background if the user is allowed to translate pages
    async switchLang(entry) {
      if (entry.code === this.variantLang || entry.state === 'trashed' || this.switching) {
        return
      }

      if (entry.state === 'missing' && !this.user.canLang('variant:add', entry.code)) {
        return this.messages.denied()
      }

      if (this.hasChanged && !(await this.save(true))) {
        return
      }

      this.switching = true

      try {
        if (entry.state === 'missing') {
          await this.$apollo.mutate({
            mutation: ADD_PAGE_VARIANT,
            variables: { id: this.item.id, lang: entry.code, access: this.user.can('page:access') }
          })
          this.invalidate()
        }
      } catch (error) {
        this.switching = false
        return this.messages.error(this.$gettext('Error adding language'), error, entry.code)
      }

      // the untranslated copy stays if queueing its translation fails
      if (entry.state === 'missing') {
        await translateNew(
          this.$apollo,
          this.user,
          [this.item.id],
          entry.code,
          this.$gettext('Translation saved as draft, review the changes')
        ).catch((error) => this.messages.error(this.$gettext('Error translating page'), error))
      }

      const prev = this.variantLang

      this.variantLang = entry.code
      this.vhistory = false

      if (!this.stacked) {
        this.$router?.replace({ query: { ...this.$route?.query, lang: entry.code } })
      }

      return this.refresh().then((ok) => {
        // the editor still contains the previous variant if loading failed or was discarded
        // because of edits made meanwhile, so saving must not write it into the new language
        if (!ok) {
          this.variantLang = prev

          if (!this.stacked) {
            this.$router?.replace({ query: { ...this.$route?.query, lang: prev || undefined } })
          }
        }
        return ok
      }).finally(() => {
        this.switching = false
      })
    },

    // marks the variant as up to date with the source variant without changing its content
    ignoreChanges() {
      if (!this.user.can('page:save')) return this.messages.denied()

      return ignoreChanges(this.$apollo, [this.item.id], this.item.lang)
        .then(() => {
          this.setVariantState(false)
          this.invalidate()
          this.messages.add(this.$gettext('Marked as up to date'), 'success')
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error marking as up to date'), error)
        })
    },

    // translates the changes of the source variant into a new draft of the variant
    async translate() {
      if (!canTranslate(this.user)) return this.messages.denied()

      if (this.hasChanged && !(await this.save(true))) {
        return
      }

      const { id, lang } = this.item
      let polling
      this.translating = true

      try {
        const batch = await translatePages(this.$apollo, [id], [lang])

        // up to date translations and those which are already queued are skipped
        if (!batch.total) {
          this.messages.add(this.$gettext('Nothing to translate, the page is up to date or already being translated'), 'info')
          return
        }

        // the store keeps polling if the editor is closed and reports the result
        polling = this.translationStore.add(
          batch,
          { ids: [id], langs: [lang], success: this.$gettext('Translation saved as draft, review the changes') }
        )
      } catch (error) {
        this.messages.error(this.$gettext('Error translating page'), error)
      } finally {
        this.translating = false
      }

      return polling
    },

    // loads the translated draft if the variant is still shown without unsaved changes
    translated(batch, progress) {
      if (!this.item?.id || !batch.ids.includes(this.item.id) || !batch.langs.includes(this.item.lang)) {
        return
      }

      this.invalidate()

      if (!progress.running && !progress.failed && !this.hasChanged) {
        return this.refresh()
      }
    }
  }
}
