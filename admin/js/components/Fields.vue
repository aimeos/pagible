/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import { recording } from '../audio'
import { useUserStore, useMessageStore } from '../stores'
import { txlocales } from '../utils'
import { transcribe } from '../ai'
import {
  mdiTranslate,
  mdiClose,
  mdiArrowRightThin,
  mdiCreation,
  mdiMicrophoneOutline,
  mdiMicrophone
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

  emits: ['change', 'error', 'update:files'],

  inject: ['write', 'translate'],

  data() {
    return {
      dirty: new Set(),
      translating: {},
      dictating: {},
      composing: {},
      errors: {},
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
      mdiTranslate,
      mdiClose,
      mdiArrowRightThin,
      mdiCreation,
      mdiMicrophoneOutline,
      mdiMicrophone,
      txlocales,
      transcribe
    }
  },

  methods: {
    addFile(item) {
      if (!item?.id) {
        this.$log(`Fields::addFile(): Invalid item without ID`, item)
        return
      }

      const files = [...this.files]

      files.push(item.id)
      this.assets[item.id] = item
      this.$emit('update:files', files)
    },

    error(code, value) {
      this.errors[code] = value
      this.$emit('error', Object.values(this.errors).includes(true))
    },

    record(code) {
      if (this.readonly) {
        return this.messages.add(this.$gettext('Permission denied'), 'error')
      }

      if (!this.audio[code]) {
        return (this.audio[code] = recording().start())
      }

      this.audio[code].then((rec) => {
        this.dictating[code] = true
        this.audio[code] = null

        rec.stop()?.then((buffer) => {
          this.transcribe(buffer)
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
      return type?.charAt(0)?.toUpperCase() + type?.slice(1)
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

    resetDirty() {
      this.dirty = new Set()
    },

    update(code, value) {
      this.data[code] = value
      this.dirty.add(code)
      this.$emit('change', this.data[code])
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
      merged: changed[code] && !changed[code]?.overwritten,
      conflict: !!changed[code]?.overwritten
    }"
  >
    <div v-if="field.type !== 'hidden'" class="label">
      {{ $pgettext('fn', field.label || code).replace(/-|_/g, ' ') }}
      <div
        v-if="!readonly && ['markdown', 'plaintext', 'string', 'text'].includes(field.type)"
        class="actions"
      >
        <component
          :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
          :aria-label="$gettext('Translate')"
          v-if="!readonly"
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
        <v-btn
          v-if="user.can('text:write')"
          :title="$gettext('Generate text')"
          :loading="composing[code]"
          @click="writeText(code)"
          :icon="mdiCreation"
          variant="text"
        />
        <v-btn
          v-if="user.can('audio:transcribe')"
          @click="record(code)"
          :class="{ dictating: audio[code] }"
          :icon="audio[code] ? mdiMicrophoneOutline : mdiMicrophone"
          :title="$gettext('Dictate')"
          :loading="dictating[code]"
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
      :readonly="readonly"
      :modelValue="data[code]"
      @addFile="addFile($event)"
      @removeFile="removeFile($event)"
      @update:modelValue="update(code, $event)"
      @error="error(code, $event)"
    ></component>
  </div>
</template>

<style scoped>
.item {
  margin: 24px 0;
  padding-inline-start: 8px;
  border-inline-start: 3px solid #d0d8e0;
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
