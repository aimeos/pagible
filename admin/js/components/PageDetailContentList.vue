/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import Fields from './Fields.vue'
import { defineAsyncComponent, markRaw } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import {
  useUserStore,
  useClipboardStore,
  useMessageStore,
  useSchemaStore,
  useSideStore
} from '../stores'
import { changedState } from '../merge'
import { debounce, frozenParse, itemTitle, uid } from '../utils'
import {
  mdiMenuDown,
  mdiContentCopy,
  mdiContentCut,
  mdiContentPaste,
  mdiSetMerge,
  mdiDelete,
  mdiMagnify,
  mdiDotsVertical,
  mdiClose,
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
  mutation ($prompt: String!, $content: JSON!, $type: String, $context: String) {
    refine(prompt: $prompt, content: $content, type: $type, context: $context)
  }
`

const ADD_ELEMENT = gql`
  mutation ($input: ElementInput!, $files: [ID!]) {
    addElement(input: $input, files: $files) {
      id
      type
      lang
      name
      data
      editor
      updated_at
      files {
        id
        lang
        mime
        name
        path
        previews
        description
        updated_at
        editor
      }
    }
  }
`

export default {
  components: {
    Fields,
    SchemaDialog,
    VueDraggable
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
    response: '',
    audio: null,
    dictating: false,
    help: false,
    lastError: false,
    refining: false,
    panel: [],
    menu: [],
    index: null,
    checked: false,
    vchange: false,
    vschemas: false,
    currentPage: 1,
    lastPage: 1
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
      mdiMagnify,
      mdiDotsVertical,
      mdiClose,
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
    }
  },

  methods: {
    add(item, idx) {
      const entry = item.id
        ? { id: uid(), group: this.section, type: 'reference', refid: item.id }
        : { id: uid(), group: this.section, type: item.type, data: {} }

      if (item.id) {
        this.elements[item.id] = item
      }

      if (idx !== null) {
        this.content.splice(idx, 0, entry)
        this.panel.push(this.panel.includes(idx) ? idx + 1 : idx)
      } else {
        this.content.push(entry)
        this.panel.push(this.content.length - 1)
      }

      this.vschemas = false
      this.$emit('update:content', this.content)
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
      this.$emit('update:content', this.content)
    },

    copy(idx) {
      const list = []

      if (idx === undefined) {
        for (let i = this.content.length - 1; i >= 0; i--) {
          if (this.content[i]._checked) {
            const entry = structuredClone(this.content[i])
            entry._checked = false
            entry['id'] = null
            list.push(entry)
          }
        }
      } else {
        const entry = structuredClone(this.content[idx])
        entry._checked = false
        entry['id'] = null
        list.push(entry)
      }

      this.clipboard.set('page-content', list.reverse())
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
      const list = []

      if (idx === undefined) {
        for (let i = this.content.length - 1; i >= 0; i--) {
          if (this.content[i]._checked) {
            const [entry] = this.content.splice(i, 1)
            entry._checked = false
            entry.id = null
            list.push(entry)
          }
        }
      } else {
        const [entry] = this.content.splice(idx, 1)
        entry._checked = false
        entry.id = null
        list.push(entry)
      }

      this.clipboard.set('page-content', list.reverse())
      this.$emit('update:content', this.content)
    },

    error(el, value) {
      el._error = value
      const has = this.content.some((el) => el._error)
      if (has !== this.lastError) {
        this.lastError = has
        this.$emit('error', has)
      }
      this.stored()
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
      let idx = 0
      const entries = []

      for (let i = this.content.length - 1; i >= 0; i--) {
        if (
          this.content[i]._checked &&
          ['text', 'code', 'heading'].includes(this.content[i].type)
        ) {
          entries.push(this.content[i])
          this.content.splice(i, 1)
          idx = i

          const pi = this.panel.indexOf(i)
          if (pi !== -1) {
            this.panel.splice(pi, 1)
          }
        }
      }

      if (entries.length === 0) {
        return
      }

      const entry = entries.reverse().reduce(
        (acc, el) => {
          acc.data.text += this.createMarkdown(el) + '\n\n'
          return acc
        },
        { id: uid(), group: this.section, type: 'text', data: { text: '' }, _changed: true }
      )

      this.content.splice(idx, 0, entry)
      this.$emit('update:content', this.content)
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
        return { ...el, group: this.section, id: uid() }
      })

      this.content.splice(idx, 0, ...entries)
      this.$emit('update:content', this.content)
    },

    purge() {
      for (let i = this.content.length - 1; i >= 0; i--) {
        if (this.content[i]._checked) {
          this.content.splice(i, 1)
        }
      }

      this.$emit(
        'error',
        this.content.some((el) => el._error)
      )
      this.$emit('update:content', this.content)
    },

    record() {
      if (!this.audio) {
        return (this.audio = markRaw(import('../audio').then((mod) => mod.recording().start())))
      }

      this.audio.then((rec) => {
        this.dictating = true
        this.audio = null

        rec.stop()?.then((buffer) => {
          import('../ai')
            .then((mod) => mod.transcribe(buffer))
            .then((transcription) => {
              this.chat = transcription.asText()
            })
            .finally(() => {
              this.dictating = false
            })
        })
      })
    },

    refine() {
      if (!this.user.can('page:refine')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

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
            context: null
          }
        })
        .then((result) => {
          if (result.errors) {
            throw result
          }

          const content = JSON.parse(result.data?.refine || '[]')

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
          this.response = ''
          this.chat = ''
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error refining content') + ':\n' + error, 'error')
          this.$log(`PageDetailContentList::refine(): Error refining content`, error)
        })
        .finally(() => {
          setTimeout(() => {
            this.refining = false
          }, 3000)
        })
    },

    remove(idx) {
      this.content.splice(idx, 1)
      this.$emit('update:content', this.content)
    },

    reset() {
      this.content.forEach((el) => {
        delete el._changed
        delete el._error
      })

      this.store()
    },

    search(term) {
      if (term) {
        term = term.toLocaleLowerCase().trim()

        this.content.forEach((el) => {
          const data = (el.type === 'reference' ? this.elements[el.refid] : el)?.data || {}
          let found = false

          for (const k in data) {
            const v = data[k]

            if (v && typeof v !== 'object' && typeof v !== 'boolean' && String(v).toLocaleLowerCase().includes(term)) {
              found = true
              break
            }
          }

          el._hide = !found
        })
      }
    },

    share(idx) {
      if (!this.user.can('element:add')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

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
            },
            files:
              entry.files?.filter((fileid, idx, self) => {
                return self.indexOf(fileid) === idx
              }) || []
          }
        })
        .then((result) => {
          if (result.errors) {
            throw result.errors
          }

          const element = result.data.addElement

          for (const file of element.files || []) {
            file.previews = frozenParse(file.previews)
            this.assets[file.id] = Object.freeze(file)
          }

          element.data = frozenParse(element.data)
          element.files = Object.freeze(element.files.map((file) => file.id))

          this.elements[element.id] = element
          this.content[idx] = {
            id: uid(),
            group: this.section,
            type: 'reference',
            refid: element.id
          }
          this.$emit('update:content', this.content)
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Unable to make element shared') + ':\n' + error, 'error')
          this.$log(`PageDetailContentList::share(): Error making element shared`, idx, error)
        })
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
          case 'code': {
            list.push({
              id: uid(),
              type: 'code',
              group: this.section,
              data: { lang: node.lang || null, text: node.value.trim() }
            })
            break
          }
          case 'heading': {
            list.push({
              id: uid(),
              type: 'heading',
              group: this.section,
              data: { title: toString(node).trim(), level: String(node.depth) }
            })
            break
          }
          case 'table': {
            const rows = node.children
              .map((row) =>
                row.children
                  .map((cell) => cell.children.map((c) => c.value || '').join(''))
                  .join(';')
              )
              .join('\n')
            list.push({
              id: uid(),
              type: 'table',
              group: this.section,
              data: { text: rows.trim() }
            })
            break
          }
          default: {
            list.push({
              id: uid(),
              type: 'text',
              group: this.section,
              data: { text: toMarkdown(node).trim() }
            })
          }
        }
      }

      this.content.splice(idx, 1, ...list)
      this.$emit('update:content', this.content)
    },

    store(isVisible = true) {
      if (!isVisible) {
        return
      }

      const types = {}
      const state = {}

      this.content.forEach((el) => {
        if (el.type) {
          types[el.type] = (types[el.type] || 0) + 1
        }
        if (!el._changed && !el._error) {
          state['valid'] = (state['valid'] || 0) + 1
        }
        if (el._changed) {
          state['changed'] = (state['changed'] || 0) + 1
        }
        if (el._error) {
          state['error'] = (state['error'] || 0) + 1
        }
      })

      return (this.side.store = Object.freeze({ type: Object.freeze(types), state: Object.freeze(state) }))
    },

    title(el) {
      return itemTitle(el.data) || this.$pgettext('st', el.type) || ''
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

      this.$emit('update:content', this.content)
    },

    update(el) {
      el._changed = true
      el.group = this.section

      if (!el.id) {
        el.id = uid()
      }

      this.emitContent()
    },

    flush() {
      this.$emit('update:content', this.content)
    }
  },

  created() {
    this.stored = debounce(() => this.store(), 200)
    this.emitContent = debounce(() => this.$emit('update:content', this.content), 150)
  },

  beforeUnmount() {
    if (this.audio) {
      this.audio.then((rec) => rec?.stop?.()).catch(() => {})
      this.audio = null
    }

    this.panel = null
    this.menu = null
    this.response = ''
    this.chat = ''
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
  <div v-visible="store">
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
      outlined
      rows="1"
    >
      <template #prepend>
        <v-btn
          @click="help = !help"
          :icon="mdiHelpCircleOutline"
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
        <v-menu>
          <template v-slot:activator="{ props }">
            <v-btn
              v-bind="props"
              :disabled="!checkedCount && !clipboard.get('page-content')"
              :append-icon="mdiMenuDown"
              variant="text"
              >{{ $gettext('Actions') }}</v-btn
            >
          </template>
          <v-list>
            <v-list-item v-if="checkedCount">
              <v-btn :prepend-icon="mdiContentCopy" variant="text" @click="copy()">{{
                $gettext('Copy')
              }}</v-btn>
            </v-list-item>
            <v-list-item v-if="checkedCount">
              <v-btn :prepend-icon="mdiContentCut" variant="text" @click="cut()">{{
                $gettext('Cut')
              }}</v-btn>
            </v-list-item>
            <v-list-item v-if="clipboard.get('page-content')">
              <v-btn :prepend-icon="mdiContentPaste" variant="text" @click="paste()">{{
                $gettext('Paste')
              }}</v-btn>
            </v-list-item>
            <v-list-item v-if="checkedCount > 1">
              <v-btn :prepend-icon="mdiSetMerge" variant="text" @click="merge()">{{
                $gettext('Merge')
              }}</v-btn>
            </v-list-item>
            <v-list-item v-if="checkedCount">
              <v-btn :prepend-icon="mdiDelete" variant="text" @click="purge()">{{
                $gettext('Delete')
              }}</v-btn>
            </v-list-item>
          </v-list>
        </v-menu>
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
      <VueDraggable
        @update:modelValue="$emit('update:content', $event)"
        :disabled="$vuetify.display.smAndDown || !user.can('page:save')"
        :modelValue="content"
        :forceFallback="true"
        fallbackTolerance="10"
        draggable=".content"
        group="content"
      >
        <v-expansion-panel
          v-for="(el, idx) in content"
          :key="el.id"
          v-show="shown(el)"
          class="content"
          :class="{
            changed: el._changed,
            error: el._error,
            ...changedState(changed, el.id || el.refid)
          }"
        >
          <v-expansion-panel-title>
            <v-checkbox-btn
              v-if="user.can('page:save')"
              :model-value="el._checked"
              @click.stop="el._checked = !el._checked"
            />

            <component
              :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
              :aria-label="$gettext('Actions')"
              v-model="menu[idx]"
              transition="scale-transition"
              location="end center"
              max-width="300"
            >
              <template #activator="{ props }">
                <v-btn
                  v-bind="props"
                  :title="$gettext('Actions')"
                  :icon="mdiDotsVertical"
                  class="btn-actions"
                  variant="text"
                />
              </template>

              <v-card>
                <v-toolbar density="compact">
                  <v-toolbar-title>{{ $gettext('Actions') }}</v-toolbar-title>
                  <v-btn
                    :icon="mdiClose"
                    :aria-label="$gettext('Close')"
                    @click="menu[idx] = false"
                  />
                </v-toolbar>

                <v-list @click="menu[idx] = false">
                  <v-list-item v-if="!el._error">
                    <v-btn :prepend-icon="mdiContentCopy" variant="text" @click="copy(idx)">{{
                      $gettext('Copy')
                    }}</v-btn>
                  </v-list-item>
                  <v-list-item v-if="!el._error">
                    <v-btn :prepend-icon="mdiContentCut" variant="text" @click="cut(idx)">{{
                      $gettext('Cut')
                    }}</v-btn>
                  </v-list-item>
                  <v-list-item>
                    <v-btn :prepend-icon="mdiDelete" variant="text" @click="remove(idx)">{{
                      $gettext('Delete')
                    }}</v-btn>
                  </v-list-item>

                  <v-divider></v-divider>

                  <v-list-item v-if="menu[idx] && clipboard.get('page-content')">
                    <v-btn :prepend-icon="mdiArrowUp" variant="text" @click="paste(idx)">{{
                      $gettext('Paste before')
                    }}</v-btn>
                  </v-list-item>
                  <v-list-item v-if="menu[idx] && clipboard.get('page-content')">
                    <v-btn :prepend-icon="mdiArrowDown" variant="text" @click="paste(idx + 1)">{{
                      $gettext('Paste after')
                    }}</v-btn>
                  </v-list-item>
                  <v-list-item>
                    <v-btn :prepend-icon="mdiArrowUp" variant="text" @click="insert(idx)">{{
                      $gettext('Insert before')
                    }}</v-btn>
                  </v-list-item>
                  <v-list-item>
                    <v-btn :prepend-icon="mdiArrowDown" variant="text" @click="insert(idx + 1)">{{
                      $gettext('Insert after')
                    }}</v-btn>
                  </v-list-item>

                  <v-divider></v-divider>

                  <v-list-item
                    v-if="!el._error && el.type !== 'reference' && user.can('element:add')"
                  >
                    <v-btn :prepend-icon="mdiLink" variant="text" @click="share(idx)">{{
                      $gettext('Make shared')
                    }}</v-btn>
                  </v-list-item>
                  <v-list-item v-if="el.type === 'reference'">
                    <v-btn :prepend-icon="mdiLinkOff" variant="text" @click="unshare(idx)">{{
                      $gettext('Merge copy')
                    }}</v-btn>
                  </v-list-item>
                  <v-list-item v-if="el.type !== 'reference'">
                    <v-btn :prepend-icon="mdiSwapHorizontal" variant="text" @click="change(idx)">{{
                      $gettext('Change to')
                    }}</v-btn>
                  </v-list-item>
                  <v-list-item v-if="el.type === 'text'">
                    <v-btn :prepend-icon="mdiSetSplit" variant="text" @click="split(idx)">{{
                      $gettext('Split')
                    }}</v-btn>
                  </v-list-item>
                  <v-list-item v-if="el._checked && checkedCount > 1">
                    <v-btn :prepend-icon="mdiSetMerge" variant="text" @click="merge()">{{
                      $gettext('Merge')
                    }}</v-btn>
                  </v-list-item>
                </v-list>
              </v-card>
            </component>

            <v-icon
              v-if="el.type === 'reference'"
              :title="$gettext('Shared element')"
              class="icon-shared"
              :icon="mdiLink"
            />

            <div class="element-title">
              {{ el.type === 'reference' ? elements[el.refid]?.name : title(el) }}
            </div>
            <div class="element-type">{{ $pgettext('st', el.type) }}</div>
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
      </VueDraggable>
    </v-expansion-panels>

    <div v-if="user.can('page:save')" class="btn-group">
      <v-btn
        @click="openSchemas"
        :title="$gettext('Add element')"
        :icon="mdiViewGridPlus"
        class="btn-add"
        color="primary"
        variant="flat"
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

.bulk {
  display: flex;
  align-items: center;
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

.v-expansion-panel.changed {
  border-inline-start: 3px solid rgb(var(--v-theme-warning));
}

.v-expansion-panel.merged {
  border-inline-start: 3px solid rgb(var(--v-theme-info));
}

.v-expansion-panel.conflict {
  border-inline-start: 3px solid rgb(var(--v-theme-error));
}

.v-expansion-panel.conflict .v-expansion-panel-title {
  color: rgb(var(--v-theme-error));
}

.v-expansion-panel.error .v-expansion-panel-title {
  color: rgb(var(--v-theme-error));
}

.v-expansion-panel-title .v-selection-control {
  flex: none;
}

.element-type {
  max-height: 48px;
  max-width: 5rem;
  text-align: end;
}

.icon-shared {
  color: rgb(var(--v-theme-warning));
  margin-inline-end: 4px;
}

.help {
  color: rgb(var(--v-theme-on-surface));
  background-color: rgb(var(--v-theme-surface-light));
  padding: 16px 24px 16px 32px;
  margin-bottom: 16px;
  border-radius: 8px;
}
</style>
