/**
 * @license MIT, https://opensource.org/license/mit
 */

import { mdiArrowDown, mdiArrowRight, mdiArrowUp } from '@mdi/js'
import { dragContext } from '@he-tree/vue'
import { command } from './shortcuts'
import { COPY_PAGE, INSERT_PAGE, MOVE_PAGE } from './pages'

/**
 * Structure of the page tree: selection, loading subpages, inserting, copying and moving pages
 *
 * Requires the embed prop, the user and messages properties, the tree ref and the allowed(),
 * create(), drop(), fetch(), invalidate() and pages() methods.
 */
const ARROW_KEYS = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight']

export const pageTree = {
  data() {
    return {
      clip: null,
      keyOrigin: null,
      origin: null
    }
  },

  computed: {
    // submenus of a page node, each applied before, into or after the page
    menus() {
      const list = []

      if (this.embed) {
        return list
      }

      if (this.pasteAction) {
        list.push({ label: this.$gettext('Paste'), fn: this.pasteAction })
      }

      if (this.user.can('page:add')) {
        list.push({ label: this.$gettext('Insert'), fn: this.insert })
      }

      return list
    },

    // pastes copied pages or moves cut pages if the user is allowed to, otherwise null
    pasteAction() {
      if (this.embed) {
        return null
      }

      if (this.clip?.type === 'copy' && this.user.can('page:add')) {
        return this.paste
      }

      if (this.clip?.type === 'cut' && this.user.can('page:move')) {
        return this.move
      }

      return null
    },

    places() {
      return [
        { icon: mdiArrowUp, label: this.$gettext('Before'), idx: 0 },
        { icon: mdiArrowRight, label: this.$gettext('Into'), idx: null },
        { icon: mdiArrowDown, label: this.$gettext('After'), idx: 1 }
      ]
    }
  },

  methods: {
    selected(fn = () => true) {
      return (this.$refs.tree?.statsFlat || []).filter((stat) => {
        return stat._checked && stat.data?.id && fn(stat.data)
      })
    },

    // returns the pages without those whose ancestors are in the list too
    tops(list) {
      const set = new Set(list)
      return list.filter((stat) => !this.checkedAncestor(stat, set))
    },

    checkedAncestor(stat, checked) {
      for (let parent = stat.parent; parent; parent = parent.parent) {
        if (checked.has(parent)) {
          return true
        }
      }
      return false
    },

    update(stat, fcn) {
      fcn(stat)
      stat.children?.forEach((child) => this.update(child, fcn))
    },

    // checks all pages if one is unchecked, otherwise unchecks all
    toggle() {
      const stats = this.$refs.tree.statsFlat
      const checked = stats.some((stat) => !stat._checked)

      stats.forEach((stat) => {
        stat._checked = checked
      })
    },

    // returns the parent, the position and the reference page for adding before (0), into (null) or after (1) the page
    target(stat, idx) {
      const siblings = this.$refs.tree.getSiblings(stat)
      const pos = siblings.indexOf(stat)
      const next = idx === null ? stat.children?.[0] : idx ? siblings[pos + 1] : stat

      return { parent: idx === null ? stat : stat.parent, pos, ref: next?.data.id || null }
    },

    position(stat) {
      const siblings = this.$refs.tree.getSiblings(stat)
      const next = siblings[siblings.indexOf(stat) + 1]

      return {
        id: stat.data.id,
        parent: stat.parent?.data.id || null,
        ref: next?.data.id || null
      }
    },

    load(stat, node) {
      if (stat.loading) {
        return
      }

      if (!stat.open && !node.children) {
        stat.loading = true

        this.fetch(node.id, stat.page ? stat.page + 1 : 1)
          .then((result) => {
            // fetch() reports its own errors and returns nothing then
            if (!Array.isArray(result?.data)) {
              stat.open = false
              return
            }

            this.$refs.tree.addMulti(result.data, stat, 0)
            this.$refs.tree._focusNode(stat)
            stat.page = result.currentPage || 1
          })
          .catch((error) => {
            stat.open = false
            this.messages.error(this.$gettext('Error fetching pages'), error, node.id)
          })
          .finally(() => {
            stat.loading = false
          })
      }

      stat.open = !stat.open
    },

    expand() {
      const stat = this.$refs.tree.activeDescendant

      if (stat && stat.data.has && !stat.children.length) {
        this.load(stat, stat.data)
      }
    },

    insert(stat, idx = null) {
      if (!this.allowed('add')) {
        return
      }

      const { parent, pos, ref } = this.target(stat, idx)
      const node = this.create(parent ? { theme: parent.data.theme, type: parent.data.type } : {})
      // collapsed nodes with unloaded children load them incl. the new page after inserting,
      // otherwise the new page would be added before the children are known
      const append = idx !== null || stat.open || stat.children?.length > 0

      if (idx === null && !stat.open && append) {
        this.load(stat, stat.data)
      }

      return this.$apollo
        .mutate({
          mutation: INSERT_PAGE,
          variables: { input: node, parent: parent?.data.id ?? null, ref }
        })
        .then((result) => {
          node.id = result.data.addPage.id
          node.source = node.lang

          if (append) {
            this.$refs.tree.add(node, parent, idx !== null ? pos + idx : 0)
          } else {
            this.load(stat, stat.data)
          }

          this.updateHas(parent, 1)

          this.invalidate(true)
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error inserting page'), error)
        })
    },

    copy(stat, node) {
      this.clip = { type: 'copy', node: node, stat: stat }
    },

    cut(stat, node) {
      this.$refs.tree.statsFlat.forEach((stat) => {
        delete stat.cut
      })
      stat.cut = true

      this.clip = { type: 'cut', node: node, stat: stat }
    },

    paste(stat, idx = null) {
      if (!this.allowed('add')) {
        return
      }

      const { parent, pos, ref } = this.target(stat, idx)
      // collapsed nodes with unloaded children load them incl. the copy when pasting into them
      const append = idx !== null || stat.open || stat.children?.length > 0

      // the server copies the page, its sub-pages and all language variants
      return this.$apollo
        .mutate({
          mutation: COPY_PAGE,
          variables: { id: this.clip.node.id, parent: parent?.data.id ?? null, ref }
        })
        .then((result) => {
          const id = result.data.copyPage?.id

          if (!id) {
            throw new Error('No page data returned')
          }

          this.invalidate(true)
          return this.pages({ id: [id] }, 1, 1, undefined, this.$gettext('Error fetching page'), 'WITH')
        })
        .then((result) => {
          const item = result?.data?.[0]

          if (item && append) {
            this.$refs.tree.add(item, parent, idx !== null ? pos + idx : 0)

            if (idx === null) {
              stat.open = true
            }
          } else if (!append) {
            this.load(stat, stat.data)
          }

          this.updateHas(parent, (item?.has || 0) + 1)
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error copying page'), error, stat, idx)
        })
    },

    pasteKey(stat) {
      this.pasteAction?.(stat, 1)
    },

    move(stat, idx = null) {
      const clip = this.clip
      const origin = this.position(clip.stat)
      const { parent, pos, ref } = this.target(stat, idx)
      // collapsed pages with unloaded children load them incl. the moved page, otherwise the
      // moved page would be their only known child and the other children would never be loaded
      const append = idx !== null || stat.open || stat.children?.length > 0

      return this.movePage(clip.node.id, parent?.data.id ?? null, ref).then((success) => {
        if (!success) {
          return false
        }

        const clipIdx = this.$refs.tree.getSiblings(stat).indexOf(clip.stat)
        const index = idx !== null ? (clipIdx >= 0 && clipIdx <= pos ? pos : pos + idx) : 0

        const oldparent = clip.stat.parent
        const moved = (clip.stat.data.has || 0) + 1

        if (append) {
          this.$refs.tree.move(clip.stat, parent, index)

          if (idx === null) {
            stat.open = true
          }
        } else {
          this.$refs.tree.remove(clip.stat)
          this.load(stat, stat.data)
        }

        delete clip.stat.cut

        if (this.clip === clip) {
          this.clip = null
        }

        this.updateHas(oldparent, -moved)
        this.updateHas(parent, moved)
        this.moved(origin)

        return true
      })
    },

    movePage(id, parentId, refId) {
      if (!this.allowed('move')) {
        return Promise.resolve(false)
      }

      return this.$apollo
        .mutate({
          mutation: MOVE_PAGE,
          variables: { id, parent: parentId, ref: refId }
        })
        .then(() => {
          this.invalidate()
          return true
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error moving page'), error)
          return false
        })
    },

    moved(origin) {
      if (!origin) {
        return
      }

      this.messages.add(this.$gettext('Page moved'), 'success', null, {
        label: this.$gettext('Undo'),
        handler: () => {
          this.movePage(origin.id, origin.parent, origin.ref).then((success) => {
            if (success) {
              this.reload(false)
            }
          })
        }
      })
    },

    updateHas(stat, delta) {
      // optimistically adjust the immediate parent's descendant count (`has`); only feeds the
      // "apply recursively (N)" hint, so grandparents stay approximate until the next reload
      if (stat?.data) {
        stat.data.has = Math.max(0, (stat.data.has || 0) + delta)
      }
    },

    // handles dropped pages only, he-tree also emits "change" for keyboard moves which reorder() saves
    // and the drag context still contains the last drag then; the dragged node is reset after dropping
    change() {
      if (!this.$refs.tree?.dragNode || !dragContext?.targetInfo) return

      const origin = this.origin
      this.origin = null

      // the change event is emitted after the node has been moved in the tree, the drag
      // context is global and may belong to another drag when the request finishes
      const parent = dragContext.targetInfo.parent
      const srcparent = dragContext.startInfo.parent
      const node = dragContext.startInfo.dragNode
      const moved = (node.data.has || 0) + 1
      const target = this.position(node)

      return this.movePage(target.id, target.parent, target.ref).then((success) => {
        // the tree shows the new position already, so restore the saved one
        if (!success) {
          this.reload(false)
          return
        }

        this.updateHas(srcparent, -moved)
        this.updateHas(parent, moved)
        this.moved(origin)
      })
    },

    // alt+arrow keys move the focused page in the tree before reorder() runs, so remember where it was
    reorderStart(event) {
      if (!ARROW_KEYS.includes(event.key)) return

      const stat = this.$refs.tree?.activeDescendant
      this.keyOrigin = stat ? { origin: this.position(stat), parent: stat.parent } : null
    },

    reorder(event) {
      if (!ARROW_KEYS.includes(event.key)) return

      const start = this.keyOrigin
      this.keyOrigin = null

      this.$nextTick(() => {
        const stat = this.$refs.tree.activeDescendant
        if (!stat) return

        const target = this.position(stat)
        const origin = start?.origin.id === target.id ? start.origin : null

        // nothing to save if the page couldn't be moved, e.g. up from the first position
        if (origin && origin.parent === target.parent && origin.ref === target.ref) return

        const parent = stat.parent
        const moved = (stat.data.has || 0) + 1

        return this.movePage(target.id, target.parent, target.ref).then((success) => {
          // the tree shows the new position already, so restore the saved one
          if (!success) {
            this.reload(false)
            return
          }

          if (origin) {
            this.updateHas(start.parent, -moved)
            this.updateHas(parent, moved)
          }

          this.moved(origin)
        })
      })
    },

    treeKey(ev) {
      const stat = this.$refs.tree.activeDescendant
      const actions = {
        select: () => (stat._checked = !stat._checked),
        // like the page menu, don't trash pages again or from embedded lists
        drop: () => !stat.data?.deleted_at && !this.embed && this.drop(stat),
        copy: () => this.copy(stat, stat.data),
        cut: () => this.cut(stat, stat.data),
        paste: () => this.pasteKey(stat)
      }
      const name = stat && command(ev, 'list', 'tree')

      if (actions[name]) {
        ev.preventDefault() // don't scroll the page or navigate back
        actions[name]()
      }
    }
  }
}
