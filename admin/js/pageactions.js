/**
 * @license MIT, https://opensource.org/license/mit
 */

import { mutation, tally, trashMessage } from './lists'
import { AI_EDITOR, CLEAR_CACHE, SAVE_PAGES } from './pages'
import { safeParse, sanitize } from './json'
import { dbDate } from './utils'

/**
 * Actions for single and selected pages: publish, status, trash, restore, purge,
 * clear cache, and the dialogs for editing the properties and the access rules
 *
 * Requires the embed prop, the filter, lang, user, messages and confirm properties, the tree ref
 * and the allowed(), invalidate(), missing(), reload() methods and those of the pageTree mixin.
 */
export const pageActions = {
  data() {
    return {
      accessDialog: false,
      accessDescendants: 0,
      accessIds: [],
      accessSelected: false,
      counts: tally([]),
      propsDialog: false,
      propsCount: 0,
      propsDescendants: 0,
      propsIds: [],
      propsSelected: false
    }
  },

  methods: {
    count() {
      const items = this.selected().map((stat) => stat.data)
      const own = items.filter((item) => !this.missing(item))

      const can = (action) => this.user.can('page:' + action)

      // variant actions only count the pages which have a variant in the current language
      // and are zero without permission, so the language section is hidden if nothing is left
      this.counts = Object.assign(tally(items), {
        draft: own.filter((item) => !item.published).length,
        own: own.length,
        langLive: can('drop') ? own.filter((item) => item.lang !== item.source && !item.variant_deleted_at).length : 0,
        langPurge: can('purge') ? items.filter((item) => this.purgeable(item)).length : 0,
        // whole pages are only deleted and purged while their source language is shown
        live: can('drop') ? items.filter((item) => !item.deleted_at && item.source === this.lang).length : 0,
        purge: can('purge') ? items.filter((item) => item.source === this.lang).length : 0,
        langTrashed: can('keep') ? items.filter((item) => this.restorable(item)).length : 0,
        stale: can('save') ? own.filter((item) => this.isStale(item)).length : 0,
        translate: items.filter((item) => this.translatable(item)).length
      })
    },

    // runs the mutation for the page IDs (of the variants in the language) and resolves true on success
    mutate(action, ids, msg, lang = null) {
      return this.$apollo
        .mutate({
          mutation: mutation(action, 'page', !!lang),
          variables: lang ? { id: ids, lang } : { id: ids }
        })
        .then(() => {
          this.invalidate(action !== 'pub')
          return true
        })
        .catch((error) => {
          this.messages.error(msg, error, ids)
          return false
        })
    },

    publish(stat) {
      const list = this.allowed('publish') ? this.targets(stat, (page) => !page.published && !this.missing(page)) : []

      if (!list.length) {
        return
      }

      return this.mutate('pub', list.map((item) => item.data.id), this.$gettext('Error publishing page'), this.lang).then((ok) => {
        for (const item of ok ? list : []) {
          item.data.published = true
          item._checked = false
        }
      })
    },

    status(stat, val) {
      if (!this.allowed('save')) {
        return
      }

      const list = this.targets(stat, (page) => !this.missing(page))

      if (!list.length) {
        return
      }

      return this.$apollo
        .mutate({
          mutation: SAVE_PAGES,
          variables: {
            id: list.map((stat) => stat.data.id),
            input: {
              status: val
            },
            lang: this.lang
          }
        })
        .then((result) => {
          const ids = new Set(result.data.bulkPage?.ids || [])

          list.forEach((stat) => {
            if (ids.has(stat.data.id)) {
              stat.data.status = val
            }
          })

          // new drafts replace AI drafts, which changes the translation counts too
          this.invalidate(list.some((stat) => ids.has(stat.data.id) && stat.data.editor === AI_EDITOR))
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error saving page'), error, list, val)
        })
    },

    // trashes the pages incl. all variants, only while their source language is shown
    drop(stat) {
      const all = this.allowed('drop') ? this.targets(stat) : []
      const list = all.filter((item) => item.data.source === this.lang)
      const ids = list.map((item) => item.data.id)

      if (!list.length) {
        if (all.length) {
          this.messages.add(this.$gettext('Pages are deleted as whole pages in their source language only'), 'info')
        }
        return
      }

      return this.mutate('drop', ids, this.$gettext('Error trashing page')).then((ok) => {
        if (!ok) {
          return
        }

        const date = dbDate()

        for (const item of this.tops(list)) {
          this.update(item, (item) => {
            item.data.deleted_at = date
            item._checked = false
          })

          if (this.filter.trashed === 'WITHOUT') {
            this.$refs.tree.remove(item)
          }
        }

        this.trashed(ids)
      })
    },

    // shows the undo message for the trashed pages (or their variants in the language)
    trashed(ids, lang = null) {
      const undo = () => {
        const msg = lang ? this.$gettext('Error restoring language') : this.$gettext('Error restoring page')
        this.mutate('keep', ids, msg, lang).then((ok) => {
          ok && this.reload(false)
        })
      }

      trashMessage(this, ids.length, this.user.can('page:keep') ? undo : null)
    },

    keep(stat) {
      const list = this.allowed('keep') ? this.tops(this.targets(stat, (page) => page.deleted_at)) : []

      if (!list.length) {
        return
      }

      return this.mutate('keep', list.map((item) => item.data.id), this.$gettext('Error restoring page')).then((ok) => {
        for (const item of ok ? list : []) {
          const deleted_at = item.data.deleted_at || null

          this.update(item, (item) => {
            if (deleted_at >= item.data.deleted_at) {
              item.data.deleted_at = null
              item._checked = false
            }
          })
        }
      })
    },

    // permanently removes the pages incl. all variants, only while their source language is shown
    async purge(stat) {
      const list = (this.allowed('purge') ? this.targets(stat) : []).filter((item) => item.data.source === this.lang)

      if (
        !list.length ||
        !(await this.confirm.purge(
          list.map((stat) => ({
            name: stat.data.name,
            info: '/' + (stat.data.path || '')
          })),
          list.some((stat) => stat.data.has)
            ? this.$gettext('All subpages of these pages will be purged as well.')
            : ''
        ))
      ) {
        return
      }

      const ids = list.map((item) => item.data.id).reverse()

      return this.mutate('purge', ids, this.$gettext('Error purging page')).then((ok) => {
        for (const item of ok ? this.tops(list) : []) {
          const parent = item.parent
          const removed = (item.data.has || 0) + 1

          this.$refs.tree.remove(item)
          this.updateHas(parent, -removed)
        }
      })
    },

    clear(stat = null) {
      if (!this.allowed('clear', 'cache')) {
        return
      }

      const list = this.targets(stat)
      const ids = list.map((stat) => stat.data.id)

      if (!list.length) {
        return
      }

      return this.$apollo
        .mutate({
          mutation: CLEAR_CACHE,
          variables: {
            ids: ids
          }
        })
        .then((result) => {
          const done = result.data?.clearCache || ids.length
          this.messages.add(
            done === 1
              ? this.$gettext('Cache cleared')
              : this.$ngettext(
                  'Cache cleared for %{num} page.',
                  'Cache cleared for %{num} pages.',
                  done,
                  { num: done }
                ),
            'success'
          )
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error clearing cache'), error, list)
        })
    },

    editProps(stat = null) {
      const list = this.targets(stat, (page) => !this.missing(page))
      const set = new Set(list)

      this.propsCount = list.length
      // pages a recursive apply reaches beyond the selection (0 for leaf-only selections); skip
      // checked-ancestor-covered pages so overlapping selections aren't counted twice
      const affected = list
        .filter((item) => !this.checkedAncestor(item, set))
        .reduce((sum, item) => sum + (item.data.has || 0) + 1, 0)
      this.propsDescendants = affected - list.length
      this.propsIds = list.map((item) => item.data.id)
      this.propsSelected = !stat
      this.propsDialog = this.propsCount > 0
    },

    // merges the saved values into the loaded tree nodes and unchecks them if the selection was saved
    // returns true if one of the saved pages was an AI draft because that changes the translation counts
    applyProps(res) {
      const ids = new Set(res.ids || [])
      // data/latest are JSON scalar strings; sanitize drops prototype-pollution keys
      const data = sanitize(safeParse(res.data))
      const latest = safeParse(res.latest)
      let aiDraft = false

      this.$refs.tree?.statsFlat.forEach((stat) => {
        const id = stat.data?.id

        if (ids.has(id)) {
          // new drafts replace AI drafts
          aiDraft ||= stat.data.editor === AI_EDITOR

          if (!this.missing(stat.data)) {
            for (const key in data) {
              if (key in stat.data) {
                stat.data[key] = data[key]
              }
            }

            if (latest[id]) {
              stat.data.latest_id = latest[id]
            }
          }
        }

        if (this.propsSelected) {
          stat._checked = false
        }
      })

      return aiDraft
    },

    saveProps({ input, descendants }) {
      if (!this.allowed('save')) {
        return
      }

      const ids = this.propsIds

      if (!ids.length || !Object.keys(input).length) {
        return
      }

      const { lang: source, ...props } = input
      const save = (input, lang) =>
        this.$apollo
          .mutate({
            mutation: SAVE_PAGES,
            variables: { id: ids, input: input, descendants: descendants, lang: lang }
          })
          .then((result) => result.data.bulkPage || {})

      // the language is changed in the source variants, the other values in the shown variants
      const saved = Object.keys(props).length ? save(props, this.lang) : Promise.resolve({})

      return saved
        .then((res) => {
          if (source === undefined) {
            return res
          }

          // the list is reloaded after language changes, so only the failures are counted
          return save({ lang: source }, null).then((src) => ({ ...res, failed: (res.failed || 0) + (src.failed || 0) }))
        })
        .then((res) => {
          const aiDraft = this.applyProps(res)

          this.propsIds = []
          this.propsSelected = false
          this.invalidate('lang' in input || aiDraft)

          // best effort: the server reports how many attempted pages could not be saved
          if (res.failed > 0) {
            this.messages.add(
              this.$ngettext(
                '%{num} page could not be updated',
                '%{num} pages could not be updated',
                res.failed,
                { num: res.failed }
              ),
              'info'
            )
          }
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error saving page'), error, ids, input)
        })
    },

    editAccess(stat = null) {
      const list = this.targets(stat)

      this.accessIds = list.map((stat) => stat.data.id)
      this.accessDescendants = list.length === 1 ? list[0].data.has || 0 : 0
      this.accessSelected = !stat
      this.accessDialog = this.accessIds.length > 0
    },

    accessApplied(access, descendants = false) {
      const stats = this.$refs.tree?.statsFlat || []
      const ids = new Set(this.accessIds)
      const selected = new Set(stats.filter((stat) => ids.has(stat.data?.id)))

      stats.forEach((stat) => {
        if (selected.has(stat) || (descendants && this.checkedAncestor(stat, selected))) {
          stat.data.access = Array.isArray(access) ? [...access] : null
          stat.data.restricted = access !== null
        }

        if (this.accessSelected) {
          stat._checked = false
        }
      })

      this.accessDialog = false
      this.accessIds = []
      this.accessSelected = false
    }
  }
}
