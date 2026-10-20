/** @license MIT, https://opensource.org/license/mit */

<script>
import FieldRow from './FieldRow.vue'
import { changedState } from '../merge'
import { fieldLabel } from '../field'
import { hasTrue } from '../utils'

export default {
  components: { FieldRow },

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
      changedState
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
      handler() {
        // changes of the fields of the previous type don't apply any more
        this.resetDirty()

        // fields kept for the new type don't report their state again, remounted ones do
        const errors = {}
        for (const code in this.fields || {}) {
          if (code in this.errors) errors[code] = this.errors[code]
        }
        this.errors = errors

        const has = hasTrue(errors)
        if (has !== this.lastError) {
          this.lastError = has
          this.$emit('error', has)
        }
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
    <FieldRow
      :field="field"
      :code="code"
      :label="label(field, code)"
      :modelValue="data[code]"
      :rel="data[code + '-rel']"
      :context="data"
      :assets="assets"
      :readonly="readonly"
      :dirty="isDirty(code)"
      prompt="Create a suitable text based on the context"
      @addFile="addFile($event)"
      @removeFile="removeFile($event)"
      @revert="resetField(code)"
      @update:modelValue="update(code, $event)"
      @update:rel="update(code + '-rel', $event, code)"
      @error="error(code, $event)"
    >
      <div v-if="changed[code] && !changed[code]?.overwritten" class="merged-value">
        {{ $gettext('Updated by other editor') }}
      </div>
      <div v-if="changed[code]?.overwritten" class="conflict-value">
        {{ $gettext('Overwritten') }}: {{ typeof changed[code].overwritten === 'object' ? JSON.stringify(changed[code].overwritten) : changed[code].overwritten }}
      </div>
    </FieldRow>
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
</style>
