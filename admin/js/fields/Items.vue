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
  mdiViewGridPlus
} from '@mdi/js'
import VirtualList from 'vue-virtual-sortable'
import { required, minEntries, maxEntries } from '../rules'
import ActionMenu from '../components/ActionMenu.vue'
import FieldActions from '../components/FieldActions.vue'
import { useClipboardStore } from '../stores'
import { TEXT_TYPES, fieldBase, fieldLabel } from '../field'
import { hintTypes, protectTypes, toName } from '../fieldtypes'
import { clone, itemTitle, uid } from '../utils'
import { key, reveal, scrollParent } from '../virtual'

export default {
  extends: fieldBase,

  inheritAttrs: false,

  components: {
    ActionMenu,
    FieldActions,
    VirtualList
  },

  props: {
    modelValue: { type: Array }
  },

  emits: ['addFile', 'removeFile'],

  data() {
    return {
      // errors of the nested fields by item key and field code
      errors: {},
      items: this.init(this.modelValue),
      itemKey: (item) => item?.[this.config.identity] || key(item),
      panel: [],
      scroller: null
    }
  },

  setup() {
    const clipboard = useClipboardStore()

    return {
      clipboard,
      mdiDotsVertical,
      mdiDragVertical,
      mdiContentCopy,
      mdiContentCut,
      mdiDelete,
      mdiArrowUp,
      mdiArrowDown,
      mdiViewGridPlus,
      hintTypes,
      itemTitle,
      protectTypes,
      textTypes: TEXT_TYPES,
      toName
    }
  },

  mounted() {
    this.scroller = scrollParent(this.$refs.panels.$el)
  },

  computed: {
    hasError() {
      const keys = new Set(this.items.map((item) => this.itemKey(item)))
      const nested = Object.entries(this.errors).some(([key, codes]) => keys.has(key) && Object.values(codes).some(Boolean))

      return nested || !this.rules.every((rule) => rule(this.items) === true)
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

    // emits a new array, so the parent's value and the working copy are never the same array
    change(items = this.items) {
      this.items = items
      this.prune()
      this.emitted = [...this.items]
      this.$emit('update:modelValue', this.emitted)
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
     * Returns a shallow copy if an identity was added, the passed item is never changed.
     */
    identity(item, config, renew = false) {
      if (!item || typeof item !== 'object' || Array.isArray(item)) {
        return item
      }

      const key = typeof config?.identity === 'string' ? config.identity : ''
      let result = item

      if (key && (renew || typeof item[key] !== 'string' || !item[key])) {
        result = { ...item, [key]: uid() }
      }

      for (const [name, field] of Object.entries(config?.item || {})) {
        if (field?.type === 'items' && Array.isArray(item[name])) {
          const list = item[name].map((child) => this.identity(child, field, renew))

          if (list.some((child, idx) => child !== item[name][idx])) {
            result = result === item ? { ...item } : result
            result[name] = list
          }
        }
      }

      return result
    },

    error(item, code, value) {
      const key = this.itemKey(item)
      this.errors[key] = { ...this.errors[key], [code]: value }
    },

    init(val) {
      const items = Array.isArray(val) ? val : clone(this.config.default ?? [])
      return items.map((item) => this.identity(item, this.config))
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

    label(field, code) {
      return fieldLabel(this, field, code)
    },

    // AI prompt used if the field is empty
    prompt(item) {
      return item?.title ? 'Write a sentence about "' + item.title + '"' : ''
    },

    // removes the errors of items which don't exist any more
    prune() {
      const keys = new Set(this.items.map((item) => this.itemKey(item)))

      for (const key of Object.keys(this.errors)) {
        if (!keys.has(key)) {
          delete this.errors[key]
        }
      }
    },

    remove(idx) {
      const key = this.itemKey(this.items[idx])

      this.items.splice(idx, 1)
      this.panel = this.panel.filter((value) => value !== key)
      this.change()
    },

    // replaces the changed item, the item objects are shared with the parent's value
    update(idx, code, value) {
      this.items[idx] = { ...this.items[idx], [code]: value }
      this.change()
    }
  },

  watch: {
    modelValue(val) {
      if (val !== this.emitted) {
        this.items = this.init(val)
      }
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
              {{ label(field, code) }}
              <div v-if="!readonly && textTypes.includes(field.type)" class="actions">
                <FieldActions
                  :code="code"
                  :field="field"
                  :value="items[idx]?.[code]"
                  :context="items[idx]"
                  :prompt="prompt(item)"
                  @update="update(idx, code, $event)"
                />
              </div>
            </div>
            <component
              :is="toName(field.type)"
              :modelValue="items[idx]?.[code]"
              v-bind="field.rel ? { rel: items[idx]?.[code + '-rel'] } : {}"
              v-on="field.rel ? { 'update:rel': value => update(idx, code + '-rel', value) } : {}"
              @update:modelValue="update(idx, code, $event)"
              @error="error(item, code, $event)"
              @addFile="$emit('addFile', $event)"
              @removeFile="$emit('removeFile', $event)"
              :readonly="readonly"
              :context="items[idx]"
              :assets="assets"
              :config="field"
              :label="protectTypes.has(toName(field.type)) ? label(field, code) : null"
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
