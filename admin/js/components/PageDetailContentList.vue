/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import Fields from './Fields.vue'
import ActionItem from './ActionItem.vue'
import ActionMenu from './ActionMenu.vue'
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
import { FILE_FIELDS, normalizeFile } from '../files'
import { invalidateList } from '../graphql'
import { editable } from '../shortcuts'
import { useDictation } from '../dictation'
import { clone, debounce, frozenParse, itemTitle, safeParse, uid } from '../utils'
import { reveal, scrollParent } from '../virtual'
import { typeLabel } from '../i18n'
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
  mdiViewGridPlus,
  mdiHelpCircleOutline,
  mdiCheckBold,
  mdiArrowRightCircle,
  mdiMicrophone,
  mdiMicrophoneOutline
} from '@mdi/js'

const SchemaDialog = defineAsyncComponent(() => import('./SchemaDialog.vue'))

const REFINE_CONTENT = gql`
  mutation ($prompt: String!, $content: JSON!, $type: String, $context: String, $lang: String, $pagetype: String) {
    refine(prompt: $prompt, content: $content, type: $type, context: $context, lang: $lang, pagetype: $pagetype)
  }
`

const ADD_ELEMENT = gql`
  ${FILE_FIELDS}
  mutation ($input: ElementInput!) {
    addElement(input: $input) {
      id
      type
      lang
      name
      data
      editor
      updated_at
      files {
        ...CmsFileFields
      }
    }
  }
`

export default {
  components: {
    ActionItem,
    ActionMenu,
    Fields,
    SchemaDialog,
    VirtualList
  },

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
    chat: '',
    dictation: useDictation(),
    help: false,
    lastError: false,
    refining: false,
    panel: [],
    index: null,
    scroller: null,
    checked: false,
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
      mdiViewGridPlus,
      mdiHelpCircleOutline,
      mdiCheckBold,
      mdiArrowRightCircle,
      mdiMicrophone,
      mdiMicrophoneOutline
    }
  },

  computed: {
    checkedCount() {
      return this.content.filter((el) => this.selected.has(el)).length
    },

    clipCount() {
      return this.clipboard.get('page-content')?.length || 0
    },

    keeps() {
      return this.content.some((el) => !this.shown(el))
        ? Math.max(this.content.length, 30)
        : 30
    }
  },

  methods: {
    typeLabel(type) {
      return typeLabel(type, this)
    },

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
      this.$emit('patch-entry', this.content[idx], { type: item.type })
      this.$emit('change')
    },

    copy(idx) {
      const list = this.selection(idx).map((el) => ({ ...clone(el), id: null }))
      this.clipboard.set('page-content', list)
    },

    createMarkdown(el) {
      if (el.type === 'text') {
        return el.data.text || ''
      } else if (el.type === 'code') {
        return `\`\`\`${el.data.lang || ''}\n${el.data.text || ''}\n\`\`\``
      } else if (el.type === 'heading') {
        return `${'#'.repeat(Number(el.data.level) || 1)} ${el.data.title || ''}`
      }
      return ''
    },

    cut(idx) {
      const list = this.selection(idx)

      this.$emit('update:content', this.without(list))
      this.clipboard.set('page-content', list.map((el) => ({ ...el, id: null })))
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
      if (!this.schemas.content[type]?.fields) {
        console.warn(`No definition of fields for "${type}" schemas`)
        return []
      }

      return this.schemas.content[type]?.fields
    },

    insert(idx) {
      this.index = idx
      this.vschemas = true
    },

    merge() {
      const types = ['text', 'code', 'heading']
      const entries = this.selection().filter((el) => types.includes(el.type))

      if (entries.length === 0) {
        return
      }

      // the first merged entry is the topmost one, so its index is the same without the entries
      const idx = this.content.indexOf(entries[0])
      const text = entries.map((el) => this.createMarkdown(el) + '\n\n').join('')
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
        !this.user.can('page:save') ||
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

    record() {
      this.dictation.toggle((text) => (this.chat = text))
    },

    refine() {
      if (!this.user.can('page:refine')) return this.messages.denied()

      const prompt = this.chat.trim()

      if (!this.chat) {
        return
      }

      this.refining = true

      this.$apollo
        .mutate({
          mutation: REFINE_CONTENT,
          variables: {
            prompt: prompt,
            content: JSON.stringify(this.content),
            type: 'content',
            context: null,
            lang: this.item.lang,
            pagetype: this.item.type
          }
        })
        .then((result) => {
          const content = safeParse(result.data?.refine || '[]', [])

          if (content.length) {
            const map = {}
            for (const item of this.content) map[item.id] = item

            content.forEach((item) => {
              item.group = this.section

              if (JSON.stringify(item) !== JSON.stringify(map[item.id] || {})) {
                item._changed = true
              }
            })

            this.$emit('update:content', content)
          }

          this.refining = null
          this.chat = ''
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error refining content'), error)
        })
        .finally(() => {
          setTimeout(() => {
            this.refining = false
          }, 3000)
        })
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

    share(idx) {
      if (!this.user.can('element:add')) return this.messages.denied()

      const entry = this.content[idx]

      if (!entry) {
        this.messages.add(this.$gettext('Element not found'), 'error')
        return
      }

      if (entry.type === 'reference') {
        this.messages.add(this.$gettext('Element is already shared'), 'error')
        return
      }

      this.$apollo
        .mutate({
          mutation: ADD_ELEMENT,
          variables: {
            input: {
              type: entry.type,
              lang: this.item.lang,
              name: this.title(entry),
              data: JSON.stringify(entry.data || {})
            }
          }
        })
        .then((result) => {
          const element = result.data.addElement

          const files = (element.files || []).map(normalizeFile)

          element.data = frozenParse(element.data)
          element.files = Object.freeze(files)

          this.$emit('add-assets', Object.fromEntries(files.map((file) => [file.id, file])))
          this.$emit('add-elements', { [element.id]: element })
          invalidateList('elements')

          // the content may have changed while waiting for the response
          if (this.content.includes(entry)) {
            const ref = this.entry('reference', { refid: element.id })
            this.$emit('update:content', this.content.map((el) => (el === entry ? ref : el)))
          }
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Unable to make element shared'), error, idx)
        })
    },

    async showError() {
      const el = this.content.find((el) => el._error && this.shown(el))

      if (!el) {
        return
      }

      if (!this.panel.includes(el.id)) {
        this.panel.push(el.id)
      }

      reveal(this.$refs.list, el.id, 'auto')
      await this.$nextTick()
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

      const [{ fromMarkdown }, { toString }, { toMarkdown }] = await Promise.all([
        import('mdast-util-from-markdown'),
        import('mdast-util-to-string'),
        import('mdast-util-to-markdown')
      ])

      const list = []
      const ast = fromMarkdown(this.content[idx].data?.text || '')

      for (const node of ast.children) {
        switch (node.type) {
          case 'code':
            list.push(this.entry('code', { data: { lang: node.lang || null, text: node.value.trim() } }))
            break
          case 'heading':
            list.push(this.entry('heading', { data: { title: toString(node).trim(), level: String(node.depth) } }))
            break
          case 'table': {
            const rows = node.children
              .map((row) =>
                row.children
                  .map((cell) => cell.children.map((c) => c.value || '').join(''))
                  .join(';')
              )
              .join('\n')
            list.push(this.entry('table', { data: { text: rows.trim() } }))
            break
          }
          default:
            list.push(this.entry('text', { data: { text: toMarkdown(node).trim() } }))
        }
      }

      const content = [...this.content]
      content.splice(idx, 1, ...list)
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

    toggle() {
      this.content.forEach((el) => {
        if (this.shown(el)) {
          this.select(el)
        }
      })
    },

    unshare(idx) {
      if (!this.content[idx]) {
        this.messages.add(this.$gettext('Content element not found'), 'error')
        return
      }

      const entry = this.content[idx]

      if (entry.type !== 'reference' || !this.elements[entry.refid]) {
        this.messages.add(this.$gettext('Element is not shared'), 'error')
        return
      }

      const element = this.elements[entry.refid]
      const copy = {
        ...entry,
        type: element.type || null,
        data: { ...(element.data || {}) },
        refid: undefined
      }

      this.$emit('add-assets', Object.fromEntries((element.files || []).map((file) => [file.id, file])))
      this.$emit('update:content', this.content.map((el, i) => (i === idx ? copy : el)))
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
    this.dictation.stop()
  },

  watch: {
    content: {
      immediate: true,
      handler(list) {
        const current = new Set(list)

        this.checked = false
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
      if (Array.isArray(val) && val.length > 3) {
        this.panel = val.slice(-3)
      }
    }
  }
}
</script>

<template>
  <div ref="root" v-visible="store">
    <v-textarea
      v-if="user.can('page:refine')"
      v-model="chat"
      :loading="refining"
      :placeholder="$gettext('Describe the task you want to perform')"
      variant="outlined"
      class="prompt"
      rounded="lg"
      hide-details
      auto-grow
      clearable
      rows="1"
    >
      <template #prepend>
        <v-btn
          @click="help = !help"
          :icon="mdiHelpCircleOutline"
          class="no-rtl"
          :title="help ? $gettext('Hide help') : $gettext('Show help')"
          :aria-expanded="help"
          aria-controls="content-help"
          variant="text"
        />
      </template>
      <template #append>
        <v-btn
          v-if="chat"
          @click="refining || refine()"
          @keydown.enter="refining || refine()"
          :icon="refining === false ? mdiArrowRightCircle : refining === null ? mdiCheckBold : null"
          :title="refining ? $gettext('Refining ...') : $gettext('Refine content based on prompt')"
          :loading="refining"
          variant="text"
        />

        <v-btn
          v-else-if="user.can('audio:transcribe')"
          @click="record()"
          :icon="dictation.active() ? mdiMicrophoneOutline : mdiMicrophone"
          :title="$gettext('Dictate')"
          :class="{ dictating: dictation.active() }"
          :loading="dictation.busy()"
          variant="text"
        />
      </template>
    </v-textarea>
    <div v-if="help" id="content-help" class="help">
      <ul :aria-label="$gettext('Help')">
        <li>{{ $gettext('AI can add or improve content based on your input') }}</li>
        <li>{{ $gettext('It can take a long time depending on the task and content size') }}</li>
      </ul>
    </div>

    <div class="header">
      <div v-if="user.can('page:save')" class="bulk">
        <v-checkbox-btn v-model="checked" @click.stop="toggle()" :aria-label="$gettext('Toggle selection')" />
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
          <ActionItem >
            1" :prepend-icon="mdiSetMerge" @click="merge()"> {{ $gettext('Merge') }}
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
        :disabled="$vuetify.display.smAndDown || !user.can('page:save')"
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
              v-if="user.can('page:save')"
              :model-value="selected.has(el)"
              @click.stop="select(el)"
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
                <ActionItem >
                  1" :prepend-icon="mdiSetMerge" @click="merge()" > {{ $gettext('Merge') }}
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
              :readonly="!user.can('page:save')"
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

    <div v-if="user.can('page:save')" class="btn-group">
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
.prompt {
  margin-bottom: 16px;
}

.v-input--horizontal :deep(.v-input__prepend),
.v-input--horizontal :deep(.v-input__append) {
  margin: 0;
}

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
