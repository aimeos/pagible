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
import { clone, debounce, dictate, frozenParse, itemTitle, safeParse, uid } from '../utils'
import { reveal, scrollParent } from '../virtual'
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

  emits: ['error', 'update:content'],

  data: () => ({
    chat: '',
    audio: null,
    dictating: false,
    help: false,
    lastError: false,
    refining: false,
    panel: [],
    index: null,
    scroller: null,
    checked: false,
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
      return this.content.filter((el) => el._checked).length
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
    add(item, idx) {
      const entry = item.id
        ? this.entry('reference', { refid: item.id })
        : this.entry(item.type, { data: {} })

      if (item.id) {
        for (const file of item.files || []) {
          this.assets[file.id] = file
        }

        this.elements[item.id] = item
      }

      if (idx !== null) {
        this.content.splice(idx, 0, entry)
      } else {
        this.content.push(entry)
      }

      this.panel.push(entry.id)
      this.vschemas = false
      this.flush()
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
      this.content[idx].type = item.type
      this.flush()
    },

    copy(idx) {
      const list = this.selection(idx).map((el) => ({ ...clone(el), id: null, _checked: false }))
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

      list.forEach((el) => this.take(this.content.indexOf(el)))
      this.clipboard.set('page-content', list.map((el) => ({ ...el, id: null, _checked: false })))
      this.flush()
    },

    error(el, value) {
      if (el) {
        el._error = value
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

    flush() {
      this.$emit('update:content', this.content)
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

      const idx = this.content.indexOf(entries[0])
      const text = entries.map((el) => this.createMarkdown(el) + '\n\n').join('')

      entries.forEach((el) => this.take(this.content.indexOf(el)))
      this.content.splice(idx, 0, this.entry('text', { data: { text }, _changed: true }))
      this.flush()
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

      this.content.splice(pos, 0, this.content.splice(idx, 1)[0])
      this.flush()

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

      this.content.splice(idx, 0, ...entries)
      this.flush()
    },

    purge() {
      this.selection().forEach((el) => this.take(this.content.indexOf(el)))

      this.error()
      this.flush()
    },

    record() {
      this.audio = dictate(this.audio, (busy) => (this.dictating = busy), (text) => (this.chat = text))
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
      this.take(idx)
      this.error()
      this.flush()
    },

    reset() {
      this.content.forEach((el) => {
        delete el._changed
        delete el._error
      })

      this.store()
    },

    search(term) {
      term = term?.toLocaleLowerCase().trim()

      this.content.forEach((el) => {
        const data = (el.type === 'reference' ? this.elements[el.refid] : el)?.data || {}
        const found = Object.values(data).some((value) =>
          value &&
          typeof value !== 'object' &&
          typeof value !== 'boolean' &&
          String(value).toLocaleLowerCase().includes(term)
        )

        el._hide = Boolean(term) && !found
      })
    },

    selection(idx) {
      return idx === undefined || this.content[idx]?._checked
        ? this.content.filter((el) => el._checked)
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

          for (const file of files) {
            this.assets[file.id] = file
          }

          element.data = frozenParse(element.data)
          element.files = Object.freeze(files)

          this.elements[element.id] = element
          this.content[idx] = this.entry('reference', { refid: element.id })
          invalidateList('elements')
          this.flush()
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
        !el._hide &&
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

      this.content.splice(idx, 1, ...list)
      this.flush()
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

    take(idx) {
      const [entry] = this.content.splice(idx, 1)
      this.panel = this.panel.filter((value) => value !== entry.id)
      return entry
    },

    title(el) {
      return itemTitle(el.data) || this.$pgettext('st', el.type).replace('::', ' ') || ''
    },

    toggle() {
      this.content.forEach((el) => {
        if (this.shown(el)) {
          el._checked = !el._checked
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

      for (const file of this.elements[entry.refid].files || []) {
        this.assets[file.id] = file
      }

      this.content[idx] = {
        ...this.content[idx],
        type: this.elements[entry.refid].type || null,
        data: { ...(this.elements[entry.refid].data || {}) },
        refid: undefined
      }

      this.flush()
    },

    update(el) {
      el._changed = true
      el.group = this.section

      if (!el.id) {
        el.id = uid()
      }

      this.emitContent()
    }
  },

  created() {
    this.stored = debounce(() => this.store(), 200)
    this.emitContent = debounce(this.flush, 150)
  },

  mounted() {
    this.scroller = scrollParent(this.$refs.root)
  },

  beforeUnmount() {
    if (this.audio) {
      this.audio.then((rec) => rec?.stop?.()).catch(() => {})
      this.audio = null
    }
  },

  watch: {
    content: {
      immediate: true,
      handler() {
        this.checked = false
        this.stored?.()
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
          :icon="audio ? mdiMicrophoneOutline : mdiMicrophone"
          :title="$gettext('Dictate')"
          :class="{ dictating: audio }"
          :loading="dictating"
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
              :model-value="el._checked"
              @click.stop="el._checked = !el._checked"
            />

            <span class="btn-actions">
              <ActionMenu>
                <template #activator="{ props, label }">
                  <v-btn v-bind="props" :title="label" :icon="mdiDotsVertical" variant="text" />
                </template>

                <ActionItem v-if="!el._error" :prepend-icon="mdiContentCopy" @click="copy(idx)">
                  {{ $pgettext('clipboard', 'Copy') }}{{ el._checked ? ` (${checkedCount})` : '' }}
                </ActionItem>
                <ActionItem v-if="!el._error" :prepend-icon="mdiContentCut" @click="cut(idx)">
                  {{ $pgettext('clipboard', 'Cut') }}{{ el._checked ? ` (${checkedCount})` : '' }}
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
            <div class="element-type">{{ $pgettext('st', el.type).replace('::', ' ') }}</div>
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
              v-model:data="el.data"
              v-model:files="el.files"
              :readonly="!user.can('page:save')"
              :fields="fields(el.type)"
              :assets="assets"
              :type="el.type"
              @error="error(el, $event)"
              @change="update(el)"
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
    <SchemaDialog v-model="vschemas" @add="add($event, index)" />
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

.v-expansion-panel-title .v-selection-control {
  flex: none;
}

.item-handle {
  cursor: move;
}

.icon-shared {
  color: rgb(var(--v-theme-warning));
  margin-inline-end: 4px;
}
</style>
