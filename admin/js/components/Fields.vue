/** @license MIT, https://opensource.org/license/mit */

<script>
import FieldActions from './FieldActions.vue'
import { changedState } from '../merge'
import { hintTypes, protectTypes, toName } from '../fieldtypes'
import { TEXT_TYPES, fieldLabel } from '../field'
import { hasTrue } from '../utils'
import { mdiUndoVariant } from '@mdi/js'

export default {
  components: { FieldActions },

  props: {
    data: { type: Object, default: () => ({}) },
    files: { type: Array, default: () => [] },
    assets: { type: Object, default: () => ({}) },
    changed: { type: Object, default: () => ({}) },
    readonly: { type: Boolean, default: false },
    fields: { type: Object, required: true },
    type: { type: String, default: '' }
  },

  emits: ['add-assets', 'change', 'error', 'update:data', 'update:files'],

  data() {
    return {
      dirty: new Set(),
      original: {},
      errors: {},
      lastError: false
    }
  },

  setup() {
    return {
      changedState,
      mdiUndoVariant,
      hintTypes,
      protectTypes,
      textTypes: TEXT_TYPES,
      toName
    }
  },

  methods: {
    addFile(value) {
      const files = new Set(this.files)
      const items = Array.isArray(value) ? value : [value]
      const assets = {}

      for (const item of items) {
        if (!item?.id) {
          this.$log(`Fields::addFile(): Invalid item without ID`, item)
          continue
        }

        files.add(item.id)
        assets[item.id] = item
      }

      if (Object.keys(assets).length) {
        this.$emit('add-assets', assets)
        this.$emit('update:files', [...files])
      }
    },

    error(code, value) {
      this.errors[code] = value
      const has = hasTrue(this.errors)
      if (has !== this.lastError) {
        this.lastError = has
        this.$emit('error', has)
      }
    },

    removeFile(id) {
      if (!id) {
        this.$log(`Fields::removeFile(): Invalid ID`, id)
        return
      }

      const files = [...this.files]
      const idx = files.findIndex((fileid) => fileid === id)

      if (idx !== -1) {
        files.splice(idx, 1)
      }

      this.$emit('update:files', files)
    },

    label(field, code) {
      return fieldLabel(this, field, code)
    },

    isDirty(code) {
      return this.dirty.has(code)
    },

    isPrivate(code) {
      const value = this.data[code]
      const files = Array.isArray(value) ? value : [value]

      return files.some((file) => {
        const id = typeof file === 'string' ? file : file?.id
        return id && this.assets?.[id]?.disk === 'private'
      })
    },

    resetDirty() {
      this.dirty.clear()
      for (const k in this.original) delete this.original[k]
    },

    resetField(code) {
      if (code in this.original) {
        const value = this.original[code]
        const rel = code + '-rel'
        const data = { ...this.data, [code]: value }

        if (rel in this.original) {
          data[rel] = this.original[rel]
          delete this.original[rel]
        }

        this.dirty.delete(code)
        delete this.original[code]
        this.$emit('update:data', data)
        this.$emit('change', value)
      }
    },

    update(code, value, dirty = code) {
      if (!(code in this.original)) {
        this.original[code] = this.data[code]
      }
      if (!(dirty in this.original)) {
        this.original[dirty] = this.data[dirty]
      }

      this.dirty.add(dirty)
      this.$emit('update:data', { ...this.data, [code]: value })
      this.$emit('change', value)
    }
  },

  watch: {
    type: {
      immediate: true,
      handler(val) {
        this.errors = {}
      }
    }
  }
}
</script>

<template>
  <div
    v-for="(field, code) in fields"
    :key="code"
    class="item"
    :class="{
      error: errors[code],
      protected: isPrivate(code),
      ...changedState(changed, code)
    }"
  >
    <div
      v-if="field.type !== 'hidden' && !protectTypes.has(toName(field.type))"
      class="label"
    >
      {{ label(field, code) }}
      <div
        v-if="!readonly && (textTypes.includes(field.type) || isDirty(code))"
        class="actions"
      >
        <FieldActions
          v-if="textTypes.includes(field.type)"
          :code="code"
          :field="field"
          :value="data[code]"
          :context="data"
          prompt="Create a suitable text based on the context"
          @update="update(code, $event)"
        />
        <v-btn
          v-if="isDirty(code)"
          :title="$gettext('Revert')"
          @click="resetField(code)"
          :icon="mdiUndoVariant"
          variant="text"
        />
      </div>
    </div>
    <div v-if="changed[code] && !changed[code]?.overwritten" class="merged-value">
      {{ $gettext('Updated by other editor') }}
    </div>
    <div v-if="changed[code]?.overwritten" class="conflict-value">
      {{ $gettext('Overwritten') }}: {{ typeof changed[code].overwritten === 'object' ? JSON.stringify(changed[code].overwritten) : changed[code].overwritten }}
    </div>
    <component
      :is="toName(field.type)"
      :key="field.type + '-' + code"
      :aria-label="label(field, code)"
      :context="data"
      :assets="assets"
      :config="field"
      :label="protectTypes.has(toName(field.type)) ? label(field, code) : null"
      :readonly="readonly"
      :modelValue="data[code]"
      v-bind="field.rel ? { rel: data[code + '-rel'] } : {}"
      v-on="field.rel ? { 'update:rel': value => update(code + '-rel', value, code) } : {}"
      @addFile="addFile($event)"
      @removeFile="removeFile($event)"
      @update:modelValue="update(code, $event)"
      @error="error(code, $event)"
    >
      <template v-if="protectTypes.has(toName(field.type))" #label>
        <v-btn
          v-if="isDirty(code)"
          :title="$gettext('Revert')"
          @click="resetField(code)"
          :icon="mdiUndoVariant"
          variant="text"
        />
      </template>
    </component>
    <div
      v-if="field.hint && field.type !== 'hidden' && !hintTypes.has(toName(field.type))"
      class="v-input__details hint"
    >
      <div class="v-messages">
        <div class="v-messages__message">{{ $pgettext('fh', field.hint) }}</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.item {
  margin: 24px 0;
  padding-inline-start: 8px;
  border-inline-start: 3px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.item.protected {
  border-inline-start: 3px solid rgb(var(--v-theme-info));
}

.item.error {
  border-inline-start: 3px solid rgb(var(--v-theme-error));
}

.item.merged {
  border-inline-start: 3px solid rgb(var(--v-theme-info));
}

.item.conflict {
  border-inline-start: 3px solid rgb(var(--v-theme-error));
}

.merged-value {
  color: rgb(var(--v-theme-info));
  font-size: 85%;
  padding: 2px 0 4px;
}

.conflict-value {
  color: rgb(var(--v-theme-error));
  font-size: 85%;
  padding: 2px 0 4px;
  word-break: break-word;
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
