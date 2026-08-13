/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import { markRaw } from 'vue'
import { useUserStore, useMessageStore } from '../stores'
import { changedState } from '../merge'
import { fieldTypes, protectTypes } from '../fieldtypes'
import { hasTrue, txlocales } from '../utils'
import {
  mdiTranslate,
  mdiClose,
  mdiArrowRightThin,
  mdiCreation,
  mdiMicrophoneOutline,
  mdiMicrophone,
  mdiUndoVariant
} from '@mdi/js'

export default {
  props: {
    data: { type: Object, default: () => {} },
    files: { type: Array, default: () => [] },
    assets: { type: Object, default: () => {} },
    changed: { type: Object, default: () => ({}) },
    readonly: { type: Boolean, default: false },
    fields: { type: Object, required: true },
    type: { type: String, default: '' }
  },

  emits: ['change', 'error', 'update:data', 'update:files'],

  inject: ['write', 'translate'],

  data() {
    return {
      dirty: new Set(),
      original: {},
      translating: {},
      dictating: {},
      composing: {},
      errors: {},
      lastError: false,
      audio: {},
      menu: {}
    }
  },

  setup() {
    const messages = useMessageStore()
    const user = useUserStore()

    return {
      user,
      messages,
      changedState,
      mdiTranslate,
      mdiClose,
      mdiArrowRightThin,
      mdiCreation,
      mdiMicrophoneOutline,
      mdiMicrophone,
      mdiUndoVariant,
      protectTypes,
      txlocales
    }
  },

  beforeUnmount() {
    for (const key of Object.keys(this.audio)) {
      if (this.audio[key]) {
        this.audio[key].then((rec) => rec?.stop?.()).catch(() => {})
      }
    }

    this.audio = null
    this.dirty = null
    this.original = null
    this.translating = null
    this.dictating = null
    this.composing = null
    this.errors = null
    this.menu = null
  },

  methods: {
    addFile(value) {
      const files = new Set(this.files)
      const items = Array.isArray(value) ? value : [value]
      let valid = false

      for (const item of items) {
        if (!item?.id) {
          this.$log(`Fields::addFile(): Invalid item without ID`, item)
          continue
        }

        files.add(item.id)
        this.assets[item.id] = item
        valid = true
      }

      if (valid) {
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

    record(code) {
      if (this.readonly) {
        return this.messages.add(this.$gettext('Permission denied'), 'error')
      }

      if (!this.audio[code]) {
        return (this.audio[code] = markRaw(import('../audio').then((mod) => mod.recording().start())))
      }

      this.audio[code].then((rec) => {
        this.dictating[code] = true
        this.audio[code] = null

        rec.stop()?.then((buffer) => {
          import('../ai')
            .then((mod) => mod.transcribe(buffer))
            .then((transcription) => {
              this.update(code, transcription.asText())
            })
            .finally(() => {
              this.dictating[code] = false
            })
        })
      })
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

    toName(type) {
      const name = type ? type.charAt(0).toUpperCase() + type.slice(1) : ''
      return fieldTypes.has(name) ? name : 'Hidden'
    },

    translateText(code, lang) {
      this.translating[code] = true

      this.translate([this.data[code]], lang)
        .then((result) => {
          this.update(code, result[0] || '')
        })
        .finally(() => {
          this.translating[code] = false
        })
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
        this.dirty.delete(code)
        delete this.original[code]
        this.$emit('update:data', { ...this.data, [code]: value })
        this.$emit('change', value)
      }
    },

    update(code, value) {
      if (!this.dirty.has(code)) {
        this.original[code] = this.data[code]
      }
      this.dirty.add(code)
      this.$emit('update:data', { ...this.data, [code]: value })
      this.$emit('change', value)
    },

    writeText(code) {
      const context = [
        'generate for field "' + (this.fields[code].label || code) + '"',
        'required output format is "' + this.fields[code].type + '"',
        this.fields[code].min ? 'minimum characters: ' + this.fields[code].min : null,
        this.fields[code].max ? 'maximum characters: ' + this.fields[code].max : null,
        this.fields[code].placeholder ? 'hint text: ' + this.fields[code].placeholder : null,
        'context information as JSON: ' + JSON.stringify(this.data)
      ]

      this.composing[code] = true

      this.write(this.data[code] || 'Create a suitable text based on the context', context)
        .then((result) => {
          this.update(code, result)
        })
        .finally(() => {
          this.composing[code] = false
        })
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
      {{ $pgettext('fn', field.label || code).replace(/-|_/g, ' ') }}
      <div
        v-if="!readonly && (['markdown', 'plaintext', 'string', 'text'].includes(field.type) || isDirty(code))"
        class="actions"
      >
        <template v-if="['markdown', 'plaintext', 'string', 'text'].includes(field.type)">
          <span class="btn-translate">
            <component
              :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
              :aria-label="$gettext('Translate')"
              v-model="menu[code]"
              transition="scale-transition"
              location="end center"
              max-width="300"
            >
              <template #activator="{ props }">
                <v-btn
                  v-bind="props"
                  :title="$gettext('Translate')"
                  :loading="translating[code]"
                  :icon="mdiTranslate"
                  variant="text"
                />
              </template>

              <v-card v-if="user.can('text:translate')">
                <v-toolbar density="compact">
                  <v-toolbar-title>{{ $gettext('Translate') }}</v-toolbar-title>
                  <v-btn :icon="mdiClose" :aria-label="$gettext('Close')" @click="menu[code] = false" />
                </v-toolbar>

                <v-list @click="menu[code] = false">
                  <v-list-item v-for="lang in txlocales()" :key="lang.code">
                    <v-btn
                      @click="translateText(code, lang.code)"
                      :prepend-icon="mdiArrowRightThin"
                      variant="text"
                      >{{ lang.name }}</v-btn
                    >
                  </v-list-item>
                </v-list>
              </v-card>
            </component>
          </span>
          <v-btn
            v-if="user.can('text:write')"
            :title="$gettext('Generate text')"
            :loading="composing[code]"
            @click="writeText(code)"
            :icon="mdiCreation"
            class="btn-generate"
            variant="text"
          />
          <v-btn
            v-if="user.can('audio:transcribe')"
            @click="record(code)"
            :class="['btn-dictate', { dictating: audio[code] }]"
            :icon="audio[code] ? mdiMicrophoneOutline : mdiMicrophone"
            :title="$gettext('Dictate')"
            :loading="dictating[code]"
            variant="text"
          />
        </template>
        <v-btn
          v-if="isDirty(code)"
          :title="$gettext('Reset')"
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
      :aria-label="$pgettext('fn', field.label || code).replace(/-|_/g, ' ')"
      :context="data"
      :assets="assets"
      :config="field"
      :label="protectTypes.has(toName(field.type)) ? $pgettext('fn', field.label || code).replace(/-|_/g, ' ') : null"
      :readonly="readonly"
      :modelValue="data[code]"
      @addFile="addFile($event)"
      @removeFile="removeFile($event)"
      @update:modelValue="update(code, $event)"
      @error="error(code, $event)"
    >
      <template v-if="protectTypes.has(toName(field.type))" #label>
        <v-btn
          v-if="isDirty(code)"
          :title="$gettext('Reset')"
          @click="resetField(code)"
          :icon="mdiUndoVariant"
          variant="text"
        />
      </template>
    </component>
  </div>
</template>

<style scoped>
.item {
  margin: 24px 0;
  padding-inline-start: 8px;
  border-inline-start: 3px solid #d0d8e0;
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
  min-height: 48px;
}
</style>
