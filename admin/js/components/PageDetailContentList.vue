/** @license MIT, https://opensource.org/license/mit */

<script>
import Fields from './Fields.vue'
import ActionItem from './ActionItem.vue'
import ActionMenu from './ActionMenu.vue'
import AiPrompt from './AiPrompt.vue'
import { defineAsyncComponent } from 'vue'
import VirtualList from 'vue-virtual-sortable'
import {
  useUserStore,
  useClipboardStore,
  useMessageStore,
  useSchemaStore,
  useSideStore
} from '../stores'
import { changedState } from '../merge'
import { editable } from '../shortcuts'
import { contentRefine } from '../contentrefine'
import { contentShare } from '../contentshare'
import { MARKDOWN_TYPES, fromMarkdown, toMarkdown } from '../markdown'
import { clone, debounce, itemTitle, uid } from '../utils'
import { reveal, scrollParent } from '../virtual'
import { typeLabelMixin } from '../i18n'
import { schemaFields } from '../field'
import {
  mdiMenuDown,
  mdiContentCopy,
  mdiContentCut,
  mdiContentPaste,
  mdiSetMerge,
  mdiDelete,
  mdiDragVertical,
  mdiMagnify,
  mdiDotsVertical,
  mdiArrowUp,
  mdiArrowDown,
  mdiLink,
  mdiLinkOff,
  mdiSwapHorizontal,
  mdiSetSplit,
  mdiViewGridPlus
} from '@mdi/js'

const SchemaDialog = defineAsyncComponent(() => import('./SchemaDialog.vue'))
const VIRTUAL_KEEPS = 30 // number of entries the virtual list renders at least
const MAX_PANELS = 3 // number of element panels open at the same time

export default {
  components: {
    ActionItem,
    ActionMenu,
    AiPrompt,
    Fields,
    SchemaDialog,
    VirtualList
  },

  mixins: [contentRefine, contentShare, typeLabelMixin],

  props: {
    item: { type: Object, required: true },
    assets: { type: Object, required: true },
    changed: { type: Object, default: null },
    content: { type: Array, required: true },
    elements: { type: Object, required: true },
    section: { type: String, default: 'main' }
  },

  emits: ['change', 'error', 'update:content', 'patch-entry', 'add-assets', 'add-elements'],

  data: () => ({
    lastError: false,
    panel: [],
    index: null,
    scroller: null,
    hidden: new Set(),
    selected: new Set(),
    term: '',
    vchange: false,
    vschemas: false
  }),

  setup() {
    const clipboard = useClipboardStore()
    const messages = useMessageStore()
    const schemas = useSchemaStore()
    const side = useSideStore()
    const user = useUserStore()

    return {
      user,
      clipboard,
      side,
      messages,
      schemas,
      changedState,
      mdiMenuDown,
      mdiContentCopy,
      mdiContentCut,
      mdiContentPaste,
      mdiSetMerge,
      mdiDelete,
      mdiDragVertical,
      mdiMagnify,
      mdiDotsVertical,
      mdiArrowUp,
      mdiArrowDown,
      mdiLink,
      mdiLinkOff,
      mdiSwapHorizontal,
      mdiSetSplit,
      mdiViewGridPlus
    }
  },

  computed: {
    canSave() {
      return this.user.canLang('page:save', this.item.lang)
    },

    // all shown entries are selected
    allSelected() {
      const shown = this.content.filter((el) => this.shown(el))
      return shown.length > 0 && shown.every((el) => this.selected.has(el))
    },

    checkedCount() {
      return this.content.filter((el) => this.selected.has(el)).length
    },

    clipCount() {
      return this.clipboard.get('page-content')?.length || 0
    },

    keeps() {
      return this.content.some((el) => !this.shown(el))
        ? Math.max(this.content.length, VIRTUAL_KEEPS)
        : VIRTUAL_KEEPS
    }
  },

  methods: {
    add(item, idx) {
      const entry = item.id
        ? this.entry('reference', { refid: item.id })
        : this.entry(item.type, { data: {} })

      if (item.id) {
        this.$emit('add-assets', Object.fromEntries((item.files || []).map((file) => [file.id, file])))
        this.$emit('add-elements', { [item.id]: item })
      }

      const list = [...this.content]
      list.splice(idx !== null ? idx : list.length, 0, entry)
      this.$emit('update:content', list)

      this.panel.push(entry.id)
      this.vschemas = false
      reveal(this.$refs.list, entry.id, idx === null ? 'bottom' : 'auto')
    },

    change(idx) {
      if (!this.content[idx]) {
        this.messages.add(this.$gettext('Content element not found'), 'error')
        return
      }

      this.index = idx
      this.vchange = true
    },

    changeTo(item, idx) {
      if (!this.content[idx]) {
        this.messages.add(this.$gettext('Content element not found'), 'error')
        return
      }

      this.vchange = false
      this.$emit('patch-entry', this.content[idx], { type: item.type, _changed: true })
      this.$emit('change')
    },

    copy(idx) {
      const list = this.selection(idx).map((el) => ({ ...clone(el), id: null }))
      this.clipboard.set('page-content', list)
    },

    cut(idx) {
      const list = this.selection(idx)

      this.$emit('update:content', this.without(list))
      this.clipboard.set('page-content', list.map((el) => ({ ...clone(el), id: null })))
    },

    error(el, value) {
      if (el) {
        this.$emit('patch-entry', el, { _error: value })
      }

      const has = this.content.some((el) => el._error)
      if (has !== this.lastError) {
        this.lastError = has
        this.$emit('error', has)
      }
      this.stored()
    },

    entry(type, props = {}) {
      return { id: uid(), group: this.section, type, ...props }
    },

    fields(type) {
      return schemaFields(this, this.schemas, 'content', type)
    },

    insert(idx) {
      this.index = idx
      this.vschemas = true
    },

    merge() {
      const entries = this.selection().filter((el) => MARKDOWN_TYPES.includes(el.type))

      if (entries.length === 0) {
        return
      }

      // the first merged entry is the topmost one, so its index is the same without the entries
      const idx = this.content.indexOf(entries[0])
      const text = entries.map((el) => toMarkdown(el) + '\n\n').join('')
      const list = this.without(entries)

      list.splice(idx, 0, this.entry('text', { data: { text }, _changed: true }))
      this.$emit('update:content', list)
    },

    move(ev, el) {
      const dir = { ArrowUp: -1, ArrowDown: 1 }[ev.key]

      if (
        !dir ||
        !ev.altKey ||
        ev.ctrlKey ||
        ev.metaKey ||
        ev.shiftKey ||
        !this.canSave ||
        editable(ev.target)
      ) {
        return
      }

      ev.preventDefault()
      ev.stopPropagation()

      const idx = this.content.indexOf(el)
      let pos = idx + dir

      // skip elements hidden by the search or the side panel filters
      while (pos >= 0 && pos < this.content.length && !this.shown(this.content[pos])) {
        pos += dir
      }

      if (idx === -1 || pos < 0 || pos >= this.content.length) {
        return
      }

      const list = [...this.content]
      list.splice(pos, 0, list.splice(idx, 1)[0])
      this.$emit('update:content', list)

      this.$nextTick(() => {
        const key = CSS.escape(String(el.id))
        const handle = this.$refs.list?.$el?.querySelector(`.content[data-key="${key}"] .item-handle`)

        handle?.scrollIntoView?.({ block: 'nearest' })
        handle?.focus()
      })
    },

    openSchemas() {
      this.index = null
      this.vschemas = true
    },

    paste(idx = null) {
      if (idx === null) {
        idx = this.content.length
      }

      const entries = (this.clipboard.get('page-content') || []).map((el) => {
        return { ...el, group: this.section, id: uid(), _changed: true }
      })

      const list = [...this.content]
      list.splice(idx, 0, ...entries)
      this.$emit('update:content', list)
    },

    purge() {
      this.$emit('update:content', this.without(this.selection()))
    },

    remove(idx) {
      this.$emit('update:content', this.without([this.content[idx]]))
    },

    // the owner clears the state flags of the entries, only the local state needs an update
    reset() {
      this.lastError = false
      this.store()
    },

    search(term) {
      this.term = term?.toLocaleLowerCase().trim() || ''

      const hidden = new Set()

      this.content.forEach((el) => {
        const data = (el.type === 'reference' ? this.elements[el.refid] : el)?.data || {}
        const found = Object.values(data).some((value) =>
          value &&
          typeof value !== 'object' &&
          typeof value !== 'boolean' &&
          String(value).toLocaleLowerCase().includes(this.term)
        )

        if (this.term && !found) {
          hidden.add(el)
        }
      })

      this.hidden = hidden
    },

    select(el) {
      this.selected.has(el) ? this.selected.delete(el) : this.selected.add(el)
    },

    selection(idx) {
      return idx === undefined || this.selected.has(this.content[idx])
        ? this.content.filter((el) => this.selected.has(el))
        : [this.content[idx]]
    },

    async showError() {
      const el = this.content.find((el) => el._error && this.shown(el))

      if (!el) {
        return
      }

      if (!this.panel.includes(el.id)) {
        this.panel.push(el.id)
      }

      await reveal(this.$refs.list, el.id, 'auto')
    },

    shown(el) {
      const valid = this.side.shown('state', 'valid')
      const error = this.side.shown('state', 'error')
      const changed = this.side.shown('state', 'changed')

      return (
        !this.hidden.has(el) &&
        this.side.shown('type', el.type) &&
        ((error && el._error) || (changed && el._changed) || (valid && !el._error && !el._changed))
      )
    },

    async split(idx) {
      if (!this.content[idx]) {
        this.messages.add(this.$gettext('Not available for this content element'), 'error')
        return
      }

      const el = this.content[idx]
      let list

      try {
        list = (await fromMarkdown(el.data?.text || '')).map(({ type, data }) => this.entry(type, { data }))
      } catch (error) {
        this.messages.error(this.$gettext('Error splitting text'), error)
        return
      }

      // the content may have changed while converting, so replace the element where it is now
      const pos = this.content.findIndex((entry) => entry === el || (el.id && entry.id === el.id))

      if (pos === -1) {
        return
      }

      const content = [...this.content]

      content.splice(pos, 1, ...list)
      this.$emit('update:content', content)
    },

    store(isVisible = true) {
      if (!isVisible) {
        return
      }

      const types = {}
      const state = {}
      const inc = (map, key) => (map[key] = (map[key] || 0) + 1)

      this.content.forEach((el) => {
        el.type && inc(types, el.type)
        !el._changed && !el._error && inc(state, 'valid')
        el._changed && inc(state, 'changed')
        el._error && inc(state, 'error')
      })

      return (this.side.store = Object.freeze({ type: Object.freeze(types), state: Object.freeze(state) }))
    },

    title(el) {
      return itemTitle(el.data) || this.typeLabel(el.type) || ''
    },

    // selects all shown entries if one is unselected, otherwise clears their selection
    toggle() {
      const all = this.allSelected

      this.content.forEach((el) => {
        if (this.shown(el)) {
          all ? this.selected.delete(el) : this.selected.add(el)
        }
      })
    },

    update(el, values) {
      this.$emit('patch-entry', el, { ...values, _changed: true, group: this.section, ...(el.id ? {} : { id: uid() }) })
      this.$emit('change')
      this.stored()
    },

    // returns the list without the entries and closes their panels
    without(entries) {
      const removed = new Set(entries)

      this.panel = this.panel.filter((id) => !entries.some((el) => el.id === id))
      entries.forEach((el) => this.selected.delete(el))

      return this.content.filter((el) => !removed.has(el))
    }
  },

  created() {
    this.stored = debounce(() => this.store(), 200)
  },

  mounted() {
    this.scroller = scrollParent(this.$refs.root)
  },

  beforeUnmount() {
    this.stored.cancel()
  },

  watch: {
    content: {
      immediate: true,
      handler(list) {
        const current = new Set(list)

        this.selected.forEach((el) => !current.has(el) && this.selected.delete(el))

        if (this.term) {
          this.search(this.term)
        }

        // updates the error state and the counts after entries have been added or removed
        if (this.stored) {
          this.error()
        }
      }
    },

    panel(val) {
      if (Array.isArray(val) && val.length > MAX_PANELS) {
        this.panel = val.slice(-MAX_PANELS)
      }
    }
  }
}
</script>

<template>
  <div ref="root" v-visible="store">
    <AiPrompt
      v-if="user.can('page:refine')"
      v-model="chat"
      :busy="refining"
      :done="refined"
      :placeholder="$gettext('Describe the task you want to perform')"
      :send-title="refining ? $gettext('Refining ...') : $gettext('Refine content based on prompt')"
      type="content"
      @submit="refine()"
    >
      <li>{{ $gettext('AI can add or improve content based on your input') }}</li>
      <li>{{ $gettext('It can take a long time depending on the task and content size') }}</li>
    </AiPrompt>

    <div class="header">
      <div v-if="canSave" class="bulk">
        <v-checkbox-btn
          :model-value="allSelected"
          :indeterminate="checkedCount > 0 && !allSelected"
          @click.stop="toggle()"
          :aria-label="$gettext('Toggle selection')"
        />
        <ActionMenu>
          <template #activator="{ props, label }">
            <v-btn
              v-bind="props"
              :disabled="!checkedCount && !clipboard.get('page-content')"
              :title="label"
              :append-icon="mdiMenuDown"
              variant="text"
              >{{ label }}</v-btn
            >
          </template>
          <ActionItem v-if="checkedCount" :prepend-icon="mdiContentCopy" @click="copy()">
            {{ $pgettext('clipboard', 'Copy') }} ({{ checkedCount }})
          </ActionItem>
          <ActionItem v-if="checkedCount" :prepend-icon="mdiContentCut" @click="cut()">
            {{ $pgettext('clipboard', 'Cut') }} ({{ checkedCount }})
          </ActionItem>
          <ActionItem v-if="clipboard.get('page-content')" :prepend-icon="mdiContentPaste" @click="paste()">
            {{ $gettext('Paste') }} ({{ clipCount }})
          </ActionItem>
          <ActionItem v-if="checkedCount > 1" :prepend-icon="mdiSetMerge" @click="merge()">
            {{ $gettext('Merge') }}
          </ActionItem>
          <ActionItem v-if="checkedCount" :prepend-icon="mdiDelete" @click="purge()">
            {{ $gettext('Remove') }}
          </ActionItem>
        </ActionMenu>
      </div>

      <v-text-field
        @click:clear="search('')"
        @input="search($event.target.value)"
        :label="$gettext('Search for')"
        :prepend-inner-icon="mdiMagnify"
        variant="underlined"
        class="search"
        clearable
        hide-details
      />
    </div>

    <v-expansion-panels class="list" v-model="panel" elevation="0" multiple>
      <VirtualList
        v-if="scroller"
        :key="keeps"
        ref="list"
        :modelValue="content"
        @update:modelValue="$emit('update:content', $event)"
        dataKey="id"
        :scroller="scroller"
        :disabled="$vuetify.display.smAndDown || !canSave"
        handle=".item-handle"
        group="content"
        :animation="0"
        :keeps="keeps"
        :size="80"
        lockAxis="x"
      >
        <template #item="{ item: el, index: idx, key }">
          <v-expansion-panel
            :key="key"
            :value="key"
            :data-key="key"
            v-show="shown(el)"
            @keydown="move($event, el)"
            class="content"
            :class="{
              changed: el._changed,
              error: el._error,
              ...changedState(changed, el.id || el.refid)
            }"
          >
          <v-expansion-panel-title>
            <v-btn
              variant="text"
              class="item-handle"
              :aria-label="$gettext('Move element')"
              aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown"
              icon
            >
              <svg height="24" width="24" viewBox="0 0 24 24" fill="currentColor">
                <path :d="mdiDragVertical" />
              </svg>
            </v-btn>

            <v-checkbox-btn
              v-if="canSave"
              :model-value="selected.has(el)"
              @click.stop="select(el)"
              :aria-label="$gettext('Toggle selection')"
            />

            <span class="btn-actions">
              <ActionMenu>
                <template #activator="{ props, label }">
                  <v-btn v-bind="props" :title="label" :icon="mdiDotsVertical" variant="text" />
                </template>

                <ActionItem v-if="!el._error" :prepend-icon="mdiContentCopy" @click="copy(idx)">
                  {{ $pgettext('clipboard', 'Copy') }}{{ selected.has(el) ? ` (${checkedCount})` : '' }}
                </ActionItem>
                <ActionItem v-if="!el._error" :prepend-icon="mdiContentCut" @click="cut(idx)">
                  {{ $pgettext('clipboard', 'Cut') }}{{ selected.has(el) ? ` (${checkedCount})` : '' }}
                </ActionItem>
                <ActionItem :prepend-icon="mdiDelete" @click="remove(idx)">
                  {{ $gettext('Remove') }}
                </ActionItem>

                <v-divider></v-divider>

                <ActionItem v-if="clipboard.get('page-content')" :prepend-icon="mdiArrowUp" @click="paste(idx)">
                  {{ $gettext('Paste before') }} ({{ clipCount }})
                </ActionItem>
                <ActionItem v-if="clipboard.get('page-content')" :prepend-icon="mdiArrowDown" @click="paste(idx + 1)">
                  {{ $gettext('Paste after') }} ({{ clipCount }})
                </ActionItem>
                <ActionItem :prepend-icon="mdiArrowUp" @click="insert(idx)">
                  {{ $gettext('Insert before') }}
                </ActionItem>
                <ActionItem :prepend-icon="mdiArrowDown" @click="insert(idx + 1)">
                  {{ $gettext('Insert after') }}
                </ActionItem>

                <v-divider></v-divider>

                <ActionItem
                  v-if="!el._error && el.type !== 'reference' && user.can('element:add')"
                  :prepend-icon="mdiLink"
                  @click="share(idx)"
                  >{{ $gettext('Make shared') }}</ActionItem
                >
                <ActionItem v-if="el.type === 'reference'" :prepend-icon="mdiLinkOff" @click="unshare(idx)">
                  {{ $gettext('Replace with local copy') }}
                </ActionItem>
                <ActionItem v-if="el.type !== 'reference'" :prepend-icon="mdiSwapHorizontal" @click="change(idx)">
                  {{ $gettext('Change to') }}
                </ActionItem>
                <ActionItem v-if="el.type === 'text'" :prepend-icon="mdiSetSplit" @click="split(idx)">
                  {{ $pgettext('text element', 'Split') }}
                </ActionItem>
                <ActionItem v-if="selected.has(el) && checkedCount > 1" :prepend-icon="mdiSetMerge" @click="merge()">
                  {{ $gettext('Merge') }}
                </ActionItem>
              </ActionMenu>
            </span>

            <v-icon
              v-if="el.type === 'reference'"
              :title="$gettext('Shared element')"
              class="icon-shared"
              :icon="mdiLink"
            />

            <div class="element-title">
              {{ el.type === 'reference' ? elements[el.refid]?.name : title(el) }}
            </div>
            <div class="element-type">{{ typeLabel(el.type) }}</div>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <Fields
              v-if="el.type === 'reference'"
              :data="elements[el.refid]?.data || {}"
              :fields="fields(elements[el.refid]?.type)"
              :assets="assets"
              :readonly="true"
              :type="el.type"
            />
            <Fields
              v-else
              :data="el.data"
              :files="el.files"
              :readonly="!canSave"
              :fields="fields(el.type)"
              :assets="assets"
              :type="el.type"
              @update:data="update(el, { data: $event })"
              @update:files="update(el, { files: $event })"
              @add-assets="$emit('add-assets', $event)"
              @error="error(el, $event)"
            />
          </v-expansion-panel-text>
          </v-expansion-panel>
        </template>
      </VirtualList>
    </v-expansion-panels>

    <div v-if="canSave" class="btn-group">
      <v-btn
        @click="openSchemas"
        :title="$gettext('Add element')"
        :icon="mdiViewGridPlus"
        class="btn-add"
        color="primary"
        variant="tonal"
      />
    </div>
  </div>

  <Teleport to="body">
    <SchemaDialog v-model="vschemas" :lang="item.lang" @add="add($event, index)" />
  </Teleport>

  <Teleport to="body">
    <SchemaDialog v-model="vchange" :elements="false" @add="changeTo($event, index)" />
  </Teleport>
</template>

<style scoped>
.v-input.search {
  max-width: 30rem;
  flex-grow: 1;
  width: 100%;
  margin: auto;
}

.v-input.search > * {
  width: 100%;
}

.v-expansion-panel {
  border-inline-start: 3px solid transparent;
}

.item-handle {
  cursor: move;
}

.icon-shared {
  color: rgb(var(--v-theme-warning));
  margin-inline-end: 4px;
}
</style>
