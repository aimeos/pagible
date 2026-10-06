/** @license MIT, https://opensource.org/license/mit */

<script>
/**
 * Configuration:
 * - `hint`: string, description shown below the field while it has focus
 * - `identity`: string, generated property name identifying each item
 * - `max`: int, maximum number of entries allowed
 * - `min`: int, minimum number of entries required
 * - `required`: boolean, if true, at least one entry is required
 */
import {
  mdiDotsVertical,
  mdiDragVertical,
  mdiContentCopy,
  mdiContentCut,
  mdiDelete,
  mdiArrowUp,
  mdiArrowDown,
  mdiTranslate,
  mdiArrowRightThin,
  mdiCreation,
  mdiMicrophoneOutline,
  mdiMicrophone,
  mdiViewGridPlus
} from '@mdi/js'
import VirtualList from 'vue-virtual-sortable'
import { required, minEntries, maxEntries } from '../rules'
import ActionMenu from '../components/ActionMenu.vue'
import { useUserStore, useClipboardStore, useMessageStore } from '../stores'
import { fieldBase } from '../field'
import { aiContext, hintTypes, protectTypes, toName } from '../fieldtypes'
import { clone, dictate, itemTitle, txlocales, uid } from '../utils'
import { key, reveal, scrollParent } from '../virtual'

export default {
  extends: fieldBase,

  inheritAttrs: false,

  components: {
    ActionMenu,
    VirtualList
  },

  props: {
    modelValue: { type: Array }
  },

  emits: ['addFile', 'removeFile'],

  inject: ['write', 'translate'],

  data() {
    return {
      translating: {},
      dictating: {},
      composing: {},
      items: this.init(this.modelValue),
      itemKey: (item) => item?.[this.config.identity] || key(item),
      panel: [],
      scroller: null,
      audio: {}
    }
  },

  setup() {
    const clipboard = useClipboardStore()
    const messages = useMessageStore()
    const user = useUserStore()

    return {
      user,
      clipboard,
      messages,
      mdiDotsVertical,
      mdiDragVertical,
      mdiContentCopy,
      mdiContentCut,
      mdiDelete,
      mdiArrowUp,
      mdiArrowDown,
      mdiTranslate,
      mdiArrowRightThin,
      mdiCreation,
      mdiMicrophoneOutline,
      mdiMicrophone,
      mdiViewGridPlus,
      hintTypes,
      itemTitle,
      protectTypes,
      toName,
      txlocales
    }
  },

  mounted() {
    this.scroller = scrollParent(this.$refs.panels.$el)
  },

  beforeUnmount() {
    for (const key of Object.keys(this.audio)) {
      if (this.audio[key]) {
        this.audio[key].then((rec) => rec?.stop?.()).catch(() => {})
      }
    }
  },

  computed: {
    hasError() {
      return !this.rules.every((rule) => rule(this.items) === true)
    },

    rules() {
      return [
        required(this.$gettext, this.config.required),
        minEntries(this.$ngettext, this.config.min),
        maxEntries(this.$ngettext, this.config.max)
      ]
    }
  },

  methods: {
    add() {
      this.insert(this.items.length, 'bottom')
    },

    change(items = this.items) {
      this.items = items
      this.$emit('update:modelValue', this.items)
    },

    copy(idx) {
      const item = clone(this.items[idx])
      this.clipboard.set('items-content', this.identity(item, this.config, true))
    },

    cut(idx) {
      this.clipboard.set('items-content', clone(this.items[idx]))
      this.remove(idx)
    },

    /**
     * Ensures configured identities exist recursively, optionally renewing them.
     */
    identity(item, config, renew = false) {
      if (!item || typeof item !== 'object' || Array.isArray(item)) {
        return item
      }

      const key = typeof config?.identity === 'string' ? config.identity : ''

      if (key && (renew || typeof item[key] !== 'string' || !item[key])) {
        item[key] = uid()
      }

      for (const [name, field] of Object.entries(config?.item || {})) {
        if (field?.type === 'items' && Array.isArray(item[name])) {
          item[name].forEach((child) => this.identity(child, field, renew))
        }
      }

      return item
    },

    init(val) {
      const items = Array.isArray(val) ? val : clone(this.config.default ?? [])
      items.forEach((item) => this.identity(item, this.config))
      return items
    },

    insert(idx, align = 'auto') {
      const item = this.identity({}, this.config)
      const key = this.itemKey(item)

      this.items.splice(idx, 0, item)
      this.panel.push(key)
      this.change()
      reveal(this.$refs.items, key, align)
    },

    paste(idx = null) {
      const item = this.clipboard.get('items-content')

      if (!item) {
        return
      }

      if (idx === null) {
        idx = this.items.length
      }

      this.items.splice(idx, 0, clone(item))
      this.clipboard.set('items-content', null)
      this.change()
    },

    record(idx, code) {
      if (this.readonly) return this.messages.denied()

      const key = idx + code
      this.audio[key] = dictate(this.audio[key], (busy) => (this.dictating[key] = busy), (text) => this.update(idx, code, text))
    },

    remove(idx) {
      const key = this.itemKey(this.items[idx])

      this.items.splice(idx, 1)
      this.panel = this.panel.filter((value) => value !== key)
      this.change()
    },

    translateText(idx, code, lang) {
      this.translating[idx + code] = true

      this.translate([this.items[idx][code]], lang)
        .then((result) => {
          this.update(idx, code, result[0] || '')
        })
        .finally(() => {
          this.translating[idx + code] = false
        })
    },

    update(idx, code, value) {
      if (!this.items[idx]) {
        this.items[idx] = {}
      }

      this.items[idx][code] = value
      this.$emit('update:modelValue', this.items)
    },

    writeText(idx, code) {
      const context = aiContext(this.config.item?.[code] || {}, code, this.items[idx])
      const prompt =
        this.items[idx][code] ||
        (this.items[idx]['title']
          ? 'Write a sentence about "' + this.items[idx]['title'] + '"'
          : '')

      this.composing[idx + code] = true

      this.write(prompt, context)
        .then((result) => {
          this.update(idx, code, result)
        })
        .finally(() => {
          this.composing[idx + code] = false
        })
    }
  },

  watch: {
    modelValue(val) {
      this.items = this.init(val)
    }
  }
}
</script>

<template>
  <v-expansion-panels
    ref="panels"
    v-bind="$attrs"
    class="items"
    v-model="panel"
    elevation="0"
    multiple
  >
    <VirtualList
      v-if="scroller"
      ref="items"
      :modelValue="items"
      @update:modelValue="change"
      :dataKey="itemKey"
      :scroller="scroller"
      :disabled="readonly || $vuetify.display.smAndDown"
      handle=".item-handle"
      group="items"
      :animation="500"
      lockAxis="x"
    >
      <template #item="{ item, index: idx, key }">
        <v-expansion-panel :key="key" :value="key" class="item">
        <v-expansion-panel-title>
          <v-btn
            v-if="!readonly"
            variant="text"
            class="item-handle"
            :aria-label="$gettext('Move element')"
            :icon="mdiDragVertical"
          />

          <span class="btn-actions" v-if="!readonly">
            <ActionMenu>
              <template #activator="{ props, label }">
                <v-btn v-bind="props" :title="label" :icon="mdiDotsVertical" variant="text" />
              </template>

              <template
                v-for="(action, i) in [
                  { icon: mdiContentCopy, label: $pgettext('clipboard', 'Copy'), fn: () => copy(idx) },
                  { icon: mdiContentCut, label: $pgettext('clipboard', 'Cut'), fn: () => cut(idx) },
                  { icon: mdiDelete, label: $gettext('Remove'), fn: () => remove(idx) },
                  { divider: true },
                  { icon: mdiArrowUp, label: $gettext('Paste before'), fn: () => paste(idx), hide: !clipboard.get('items-content') },
                  { icon: mdiArrowDown, label: $gettext('Paste after'), fn: () => paste(idx + 1), hide: !clipboard.get('items-content') },
                  { icon: mdiArrowUp, label: $gettext('Insert before'), fn: () => insert(idx) },
                  { icon: mdiArrowDown, label: $gettext('Insert after'), fn: () => insert(idx + 1) }
                ].filter((action) => !action.hide)"
                :key="i"
              >
                <v-divider v-if="action.divider" />
                <v-list-item v-else>
                  <v-btn :prepend-icon="action.icon" variant="text" @click="action.fn()">{{
                    action.label
                  }}</v-btn>
                </v-list-item>
              </template>
            </ActionMenu>
          </span>

          <div class="element-title">{{ itemTitle(item) }}</div>
        </v-expansion-panel-title>

        <v-expansion-panel-text>
          <div v-for="(field, code) in config.item || {}" :key="code" class="field">
            <div v-if="!protectTypes.has(toName(field.type))" class="label">
              {{ $pgettext('fn', field.label || code).replace(/-|_/g, ' ') }}
              <div
                v-if="!readonly && ['markdown', 'plaintext', 'string', 'text'].includes(field.type)"
                class="actions"
              >
                <ActionMenu
                  v-if="user.can('text:translate')"
                  :title="$gettext('Translate')"
                  location="end center"
                >
                  <template #activator="{ props }">
                    <v-btn
                      v-bind="props"
                      :title="$gettext('Translate')"
                      :loading="translating[idx + code]"
                      :icon="mdiTranslate"
                      variant="text"
                    />
                  </template>

                  <v-list-item v-for="lang in txlocales()" :key="lang.code">
                    <v-btn
                      @click="translateText(idx, code, lang.code)"
                      :prepend-icon="mdiArrowRightThin"
                      variant="text"
                      >{{ lang.name }}</v-btn
                    >
                  </v-list-item>
                </ActionMenu>
                <v-btn
                  v-if="user.can('text:write')"
                  :title="$gettext('Generate text')"
                  :loading="composing[idx + code]"
                  @click="writeText(idx, code)"
                  :icon="mdiCreation"
                  variant="text"
                />
                <v-btn
                  v-if="user.can('audio:transcribe')"
                  @click="record(idx, code)"
                  :class="{ dictating: audio[idx + code] }"
                  :icon="audio[idx + code] ? mdiMicrophoneOutline : mdiMicrophone"
                  :title="$gettext('Dictate')"
                  :loading="dictating[idx + code]"
                  variant="text"
                />
              </div>
            </div>
            <component
              :is="toName(field.type)"
              :modelValue="items[idx]?.[code]"
              v-bind="field.rel ? { rel: items[idx]?.[code + '-rel'] } : {}"
              v-on="field.rel ? { 'update:rel': value => update(idx, code + '-rel', value) } : {}"
              @update:modelValue="update(idx, code, $event)"
              @addFile="$emit('addFile', $event)"
              @removeFile="$emit('removeFile', $event)"
              :readonly="readonly"
              :context="items[idx]"
              :assets="assets"
              :config="field"
              :label="protectTypes.has(toName(field.type)) ? $pgettext('fn', field.label || code).replace(/-|_/g, ' ') : null"
            ></component>
            <div
              v-if="field.hint && field.type !== 'hidden' && !hintTypes.has(toName(field.type))"
              class="v-input__details hint"
            >
              <div class="v-messages">
                <div class="v-messages__message">{{ $pgettext('fh', field.hint) }}</div>
              </div>
            </div>
          </div>
        </v-expansion-panel-text>
        </v-expansion-panel>
      </template>
    </VirtualList>
  </v-expansion-panels>

  <div class="btn-group">
    <v-btn
      v-if="!readonly && (!config.max || (config.max && +items.length < +config.max))"
      :title="$gettext('Add element')"
      :icon="mdiViewGridPlus"
      class="btn-add"
      color="primary"
      variant="tonal"
      @click="add()"
    />
  </div>
</template>

<style scoped>
.v-expansion-panel.v-expansion-panel--active.item {
  border: 1px solid rgba(var(--v-border-color), var(--v-medium-emphasis-opacity));
}

.items.v-expansion-panels {
  display: block;
}

.item-handle {
  cursor: move;
}

.v-expansion-panel-title {
  padding: 8px 16px;
}

.field {
  margin-bottom: 12px;
}

.label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  text-transform: capitalize;
  font-weight: bold;
  margin-bottom: 4px;
}
</style>
