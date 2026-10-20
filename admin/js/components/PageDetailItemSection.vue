/** @license MIT, https://opensource.org/license/mit */

<script>
import { defineAsyncComponent } from 'vue'
import Fields from './Fields.vue'
import { useUserStore, useMessageStore, useSchemaStore, useSideStore } from '../stores'
import { hasTrue, itemTitle } from '../utils'
import { mdiPencil, mdiDelete, mdiViewGridPlus } from '@mdi/js'
import { typeLabelMixin } from '../i18n'
import { schemaFields } from '../field'

const SchemaDialog = defineAsyncComponent(() => import('./SchemaDialog.vue'))

export default {
  components: {
    Fields,
    SchemaDialog
  },

  mixins: [typeLabelMixin],

  props: {
    assets: { type: Object, default: () => ({}) },
    item: { type: Object, required: true },
    permission: { type: String, default: null },
    section: { type: String, required: true }
  },

  emits: ['patch', 'add-assets', 'error'],

  data: () => ({
    errors: {},
    vschemas: false,
    panel: []
  }),

  setup() {
    const messages = useMessageStore()
    const schemas = useSchemaStore()
    const side = useSideStore()
    const user = useUserStore()

    return { user, messages, schemas, side, mdiPencil, mdiDelete, mdiViewGridPlus }
  },

  computed: {
    available() {
      return Object.keys(this.schemas[this.section] || {}).length
    },

    canEdit() {
      return this.user.can('page:save') && (!this.permission || this.user.can(this.permission))
    },

    entries() {
      return this.item[this.section] || {}
    }
  },

  methods: {
    add(item) {
      if (this.entries[item.type]) {
        this.messages.add(this.$gettext('The element has already been added'), 'error')
        return
      }

      this.patch({ ...this.entries, [item.type]: { type: item.type, data: {}, files: [] } })
      this.panel.push(item.type)
      this.vschemas = false
    },

    // updates the error state of the entry and reports if any entry is invalid
    error(code, value) {
      if (value) {
        this.errors[code] = value
      } else {
        delete this.errors[code]
      }

      this.$emit('error', hasTrue(this.errors))
      this.store()
    },

    fields(type) {
      return schemaFields(this, this.schemas, this.section, type)
    },

    // sends the new entries of the section to the owner of the item
    patch(entries) {
      this.$emit('patch', { [this.section]: entries })
    },

    remove(code) {
      const { [code]: removed, ...entries } = this.entries

      this.panel = this.panel.filter((open) => open !== code)
      this.patch(entries)
      this.error(code, false)
    },

    reset() {
      this.errors = {}
    },

    showError() {
      const code = Object.keys(this.entries).find((code) => this.errors[code])

      if (code && !this.panel.includes(code)) {
        this.panel.push(code)
      }
    },

    shown(el, code) {
      const valid = this.side.shown('state', 'valid')
      const error = this.side.shown('state', 'error')
      const changed = this.side.shown('state', 'changed')
      const failed = this.errors[code]

      return (
        this.side.shown('type', el.type) &&
        ((error && failed) || (changed && el._changed) || (valid && !failed && !el._changed))
      )
    },

    store(isVisible = true) {
      if (!isVisible) {
        return
      }

      const types = {}
      const state = {}

      for (const [code, el] of Object.entries(this.entries)) {
        if (el.type) {
          types[el.type] = (types[el.type] || 0) + 1
        }
        if (!el._changed && !this.errors[code]) {
          state['valid'] = (state['valid'] || 0) + 1
        }
        if (el._changed) {
          state['changed'] = (state['changed'] || 0) + 1
        }
        if (this.errors[code]) {
          state['error'] = (state['error'] || 0) + 1
        }
      }

      this.side.store = Object.freeze({ type: Object.freeze(types), state: Object.freeze(state) })
    },

    title(el) {
      return itemTitle(el.data) || this.typeLabel(el.type) || ''
    },

    update(code, values) {
      this.patch({ ...this.entries, [code]: { ...this.entries[code], ...values, _changed: true } })
      this.store()
    }
  }
}
</script>

<template>
  <v-container v-visible="store">
    <v-sheet>
      <v-expansion-panels class="list" v-model="panel" elevation="0" multiple>
        <v-expansion-panel
          v-for="(el, code) in entries"
          :key="code"
          :value="code"
          :class="{ changed: el._changed, error: errors[code] }"
          v-show="shown(el, code)"
        >
          <v-expansion-panel-title :expand-icon="mdiPencil">
            <v-btn
              v-if="canEdit"
              @click="remove(code)"
              :title="$gettext('Remove content element')"
              :icon="mdiDelete"
              class="btn-remove"
              variant="text"
            />
            <div class="element-title">{{ title(el) }}</div>
            <div class="element-type">{{ typeLabel(el.type) }}</div>
          </v-expansion-panel-title>
          <v-expansion-panel-text eager>
            <Fields
              ref="field"
              :data="el.data"
              :files="el.files"
              @update:data="update(code, { data: $event })"
              @update:files="update(code, { files: $event })"
              @add-assets="$emit('add-assets', $event)"
              @error="error(code, $event)"
              :readonly="!canEdit"
              :fields="fields(el.type)"
              :assets="assets"
              :type="el.type"
            />
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>

      <div v-if="available && canEdit" class="btn-group">
        <v-btn
          @click="vschemas = true"
          :title="$gettext('Add element')"
          :icon="mdiViewGridPlus"
          class="btn-add"
          color="primary"
          variant="tonal"
        />
      </div>
    </v-sheet>
  </v-container>

  <SchemaDialog v-model="vschemas" :elements="false" :type="section" @add="add($event)" />
</template>

<style scoped>
.v-expansion-panel {
  border-inline-start: 3px solid transparent;
}
</style>
