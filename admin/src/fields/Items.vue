/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

<script>
  /**
   * Configuration:
   * - `max`: int, maximum number of characters allowed in the input field
   * - `min`: int, minimum number of characters required in the input field
   * - `required`: boolean, if true, the field is required
   */
  import gql from 'graphql-tag'
  import { recording } from '../audio'
  import { VueDraggable } from 'vue-draggable-plus'
  import { useAuthStore, useClipboardStore, useMessageStore } from '../stores'

  export default {
    components: {
      VueDraggable
    },

    props: {
      'modelValue': {type: Array},
      'config': {type: Object, default: () => {}},
      'assets': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    emits: ['update:modelValue', 'error', 'addFile', 'removeFile'],

    inject: ['write', 'translate', 'transcribe', 'txlocales'],

    data() {
      return {
        translating: {},
        dictating: {},
        composing: {},
        errors: [],
        items: [],
        menu: [],
        panel: [],
        audio: {},
      }
    },

    setup() {
      const clipboard = useClipboardStore()
      const messages = useMessageStore()
      const auth = useAuthStore()

      return { auth, clipboard, messages }
    },

    computed: {
      rules() {
        return [
          v => (!this.config.max || this.config.max && v.length <= this.config.max) || this.$gettext(`Maximum is %{num} entries`, {num: this.config.max}),
          v => ((this.config.min ?? 1) && v.length >= (this.config.min ?? 1)) || this.$gettext(`Minimum is %{num} entries`, {num: this.config.min ?? 1}),
        ]
      }
    },

    methods: {
      add() {
        this.items.push({})
        this.panel.push(this.items.length - 1)
        this.$emit('update:modelValue', this.items)
      },


      change() {
        this.$emit('update:modelValue', this.items)
      },


      writeText(idx, code) {
        const context = [
          'generate for field "' + (this.config.item?.[code]?.label || code) + '"',
          'required output format is "' + this.config.item?.[code]?.type + '"',
          this.config.item?.[code]?.min ? 'minimum characters: ' + this.config.item?.[code]?.min : null,
          this.config.item?.[code]?.max ? 'maximum characters: ' + this.config.item?.[code]?.max : null,
          this.config.item?.[code]?.placeholder ? 'hint text: ' + this.config.item?.[code]?.placeholder : null,
          'context information as JSON: ' + JSON.stringify(this.items[idx]),
        ]
        const prompt = this.items[idx][code] || (
          this.items[idx]['title'] ? 'Write a sentence about "' + this.items[idx]['title'] + '"' : ''
        )

        this.composing[idx+code] = true

        this.write(prompt, context).then(result => {
          this.update(idx, code, result)
        }).finally(() => {
          this.composing[idx+code] = false
        })
      },


      copy(idx) {
        this.clipboard.set('items-content', JSON.parse(JSON.stringify(this.items[idx])))
      },


      cut(idx) {
        this.clipboard.set('items-content', JSON.parse(JSON.stringify(this.items[idx])))
        this.items.splice(idx, 1)
        this.$emit('update:modelValue', this.items)
      },


      insert(idx) {
        this.items.splice(idx, 0, {})
        this.panel.push(idx)
        this.$emit('update:modelValue', this.items)
      },


      paste(idx = null) {
        if(idx === null) {
          idx = this.items.length
        }

        this.items.splice(idx, 0, this.clipboard.get('items-content'))
        this.$emit('update:modelValue', this.items)
      },


      record(idx, code) {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        if(!this.audio[idx+code]) {
          return this.audio[idx+code] = recording().start()
        }

        this.audio[idx+code].then(rec => {
          this.dictating[idx+code] = true
          this.audio[idx+code] = null

          rec.stop()?.then(buffer => {
            this.transcribe(buffer).then(transcription => {
              this.update(idx, code, transcription.asText())
            }).finally(() => {
              this.dictating[idx+code] = false
            })
          })
        })
      },


      remove(idx) {
        this.items.splice(idx, 1)
        this.$emit('update:modelValue', this.items)
      },


      title(el) {
        return (el.title || el.text || Object.values(el || {})
          .map(v => v && typeof v !== 'object' && typeof v !== 'boolean' ? v : null)
          .filter(v => !!v)
          .join(' - '))
          .substring(0, 100) || ''
      },


      toName(type) {
        return type?.charAt(0)?.toUpperCase() + type?.slice(1)
      },


      translateText(idx, code, lang) {
        this.translating[idx+code] = true

        this.translate([this.items[idx][code]], lang).then(result => {
          this.update(idx, code, result[0] || '')
        }).finally(() => {
          this.translating[idx+code] = false
        })
      },


      update(idx, code, value) {
        if(!this.items[idx]) {
          this.items[idx] = {}
        }

        this.items[idx][code] = value
        this.$emit('update:modelValue', this.items)
      }
    },

    watch: {
      modelValue: {
        immediate: true,
        handler(val) {
          this.items = Array.isArray(val) ? val : this.config.default ?? []

          this.$emit('error', !this.rules.every(rule => {
            return rule(this.items) === true
          }))
        }
      }
    }
  }
</script>

<template>
  <v-expansion-panels class="items" v-model="panel" elevation="0" multiple>
    <VueDraggable
      v-model="items"
      @update="change()"
      :disabled="readonly || $vuetify.display.smAndDown"
      :forceFallback="true"
      fallbackTolerance="10"
      draggable=".item"
      group="items"
      animation="500">

      <v-expansion-panel v-for="(item, idx) in items" :key="idx" class="item">
        <v-expansion-panel-title>
          <component :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
            v-if="!readonly"
            v-model="menu[idx]"
            transition="scale-transition"
            location="end center"
            max-width="300">

            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                :title="$gettext('Actions')"
                icon="mdi-dots-vertical"
                variant="text"
              />
            </template>

            <v-card>
              <v-toolbar density="compact">
                <v-toolbar-title>{{ $gettext('Actions') }}</v-toolbar-title>
                <v-btn icon="mdi-close" @click="menu[idx] = false" />
              </v-toolbar>

              <v-list @click="menu[idx] = false">
                <v-list-item>
                  <v-btn prepend-icon="mdi-content-copy" variant="text" @click="copy(idx)">{{ $gettext('Copy') }}</v-btn>
                </v-list-item>
                <v-list-item>
                  <v-btn prepend-icon="mdi-content-cut" variant="text" @click="cut(idx)">{{ $gettext('Cut') }}</v-btn>
                </v-list-item>
                <v-list-item>
                  <v-btn prepend-icon="mdi-delete" variant="text" @click="remove(idx)">{{ $gettext('Delete') }}</v-btn>
                </v-list-item>

                <v-divider></v-divider>

                <v-list-item v-if="menu[idx] && clipboard.get('items-content')">
                  <v-btn prepend-icon="mdi-arrow-up" variant="text" @click="paste(idx)">{{ $gettext('Paste before') }}</v-btn>
                </v-list-item>
                <v-list-item v-if="menu[idx] && clipboard.get('items-content')">
                  <v-btn prepend-icon="mdi-arrow-down" variant="text" @click="paste(idx + 1)">{{ $gettext('Paste after') }}</v-btn>
                </v-list-item>
                <v-list-item>
                  <v-btn prepend-icon="mdi-arrow-up" variant="text" @click="insert(idx)">{{ $gettext('Insert before') }}</v-btn>
                </v-list-item>
                <v-list-item>
                  <v-btn prepend-icon="mdi-arrow-down" variant="text" @click="insert(idx + 1)">{{ $gettext('Insert after') }}</v-btn>
                </v-list-item>
              </v-list>
            </v-card>
          </component>

          <div class="element-title">{{ title(item) }}</div>
        </v-expansion-panel-title>

        <v-expansion-panel-text>
          <div v-for="(field, code) in (config.item || {})" :key="code" class="field">
            <div class="label">
              {{ field.label || code }}
              <div v-if="!readonly && ['markdown', 'plaintext', 'string', 'text'].includes(field.type)" class="actions">
                <component v-if="auth.can('text:translate')"
                  :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
                  v-model="menu[idx+code]"
                  transition="scale-transition"
                  location="end center"
                  max-width="300">

                  <template #activator="{ props }">
                    <v-btn
                      v-bind="props"
                      :title="$gettext('Translate')"
                      :loading="translating[idx+code]"
                      icon="mdi-translate"
                      variant="text"
                    />
                  </template>

                  <v-card>
                    <v-toolbar density="compact">
                      <v-toolbar-title>{{ $gettext('Translate') }}</v-toolbar-title>
                      <v-btn icon="mdi-close" @click="menu[idx+code] = false" />
                    </v-toolbar>

                    <v-list @click="menu[idx+code] = false">
                      <v-list-item v-for="lang in txlocales()" :key="lang.code">
                        <v-btn
                          @click="translateText(idx, code, lang.code)"
                          prepend-icon="mdi-arrow-right-thin"
                          variant="text"
                        >{{ lang.name }}</v-btn>
                      </v-list-item>
                    </v-list>
                  </v-card>
                </component>
                <v-btn v-if="auth.can('text:write')"
                  :title="$gettext('Generate text')"
                  :loading="composing[idx+code]"
                  @click="writeText(idx, code)"
                  icon="mdi-creation"
                  variant="text"
                />
                <v-btn v-if="auth.can('audio:transcribe')"
                  @click="record(idx, code)"
                  :class="{dictating: audio[idx+code]}"
                  :icon="audio[idx+code] ? 'mdi-microphone-outline' : 'mdi-microphone'"
                  :title="$gettext('Dictate')"
                  :loading="dictating[idx+code]"
                  variant="text"
                />
              </div>
            </div>
            <component :is="toName(field.type)"
              :modelValue="items[idx]?.[code]"
              @update:modelValue="update(idx, code, $event)"
              @addFile="$emit('addFile', $event)"
              @removeFile="$emit('removeFile', $event)"
              :readonly="readonly"
              :context="items[idx]"
              :assets="assets"
              :config="field"
            ></component>
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>

    </VueDraggable>
  </v-expansion-panels>

  <div v-if="errors.length" class="v-input--error">
    <div class="v-input__details" role="alert" aria-live="polite">
      <div class="v-messages">
        <div v-for="(msg, idx) in errors" :key="idx" class="v-messages__message">
          {{ msg }}
        </div>
      </div>
    </div>
  </div>

  <div class="btn-group">
    <v-btn v-if="!readonly && (!config.max || config.max && +items.length < +config.max)"
      :title="$gettext('Add element')"
      icon="mdi-view-grid-plus"
      @click="add()"
    />
  </div>
</template>

<style scoped>
  .v-expansion-panel.v-expansion-panel--active.item {
    border: 1px solid #D0D8E0;
  }

  .items.v-expansion-panels {
    display: block;
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
