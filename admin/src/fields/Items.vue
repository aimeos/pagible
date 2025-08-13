<script>
  /**
   * Configuration:
   * - `max`: int, maximum number of characters allowed in the input field
   * - `min`: int, minimum number of characters required in the input field
   * - `required`: boolean, if true, the field is required
   */
  import gql from 'graphql-tag'
  import { VueDraggable } from 'vue-draggable-plus'

  export default {
    components: {
      VueDraggable
    },

    props: {
      'modelValue': {type: Array, default: () => []},
      'config': {type: Object, default: () => {}},
      'assets': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    emits: ['update:modelValue', 'error', 'addFile', 'removeFile'],

    inject: ['compose', 'translate', 'txlocales'],

    data() {
      return {
        translating: {},
        composing: {},
        errors: [],
        items: [],
        panel: [],
      }
    },

    methods: {
      add() {
        this.items.push({})
        this.panel.push(this.items.length - 1)
        this.$emit('update:modelValue', this.items)
        this.validate()
      },


      change() {
        this.$emit('update:modelValue', this.items)
      },


      composeText(idx, code) {
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

        this.compose(prompt, context).then(result => {
          this.update(idx, code, result)
        }).finally(() => {
          this.composing[idx+code] = false
        })
      },


      remove(idx) {
        this.items.splice(idx, 1)
        this.$emit('update:modelValue', this.items)
        this.validate()
      },


      title(el) {
        return (el.title || el.text || Object.values(el || {})
          .map(v => v && typeof v !== 'object' && typeof v !== 'boolean' ? v : null)
          .filter(v => !!v)
          .join(' - '))
          .substring(0, 50) || ''
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
      },


      async validate() {
        const rules = [
          v => (!this.config.max || this.config.max && v.length <= this.config.max) || this.$gettext(`Maximum is %{num} entries`, {num: this.config.max}),
          v => ((this.config.min ?? 1) && v.length >= (this.config.min ?? 1)) || this.$gettext(`Minimum is %{num} entries`, {num: this.config.min ?? 1}),
        ]

        this.errors = rules.map(rule => rule(this.items)).filter(v => v !== true)
        this.$emit('error', this.errors.length > 0)

        return await !this.errors.length
      }
    },

    watch: {
      modelValue: {
        immediate: true,
        handler(val) {
          this.items = val
          this.validate()
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
      :disabled="readonly || panel.length"
      :forceFallback="true"
      fallbackTolerance="10"
      draggable=".item"
      group="items"
      animation="500">

      <v-expansion-panel v-for="(item, idx) in items" :key="idx" class="item">
        <v-expansion-panel-title>
          <v-btn v-if="!readonly"
            @click="remove(idx)"
            :title="$gettext('Remove element')"
            icon="mdi-trash-can"
            variant="plain"
          />
          <div class="element-title">{{ title(item) }}</div>
        </v-expansion-panel-title>

        <v-expansion-panel-text>
          <div v-for="(field, code) in (config.item || {})" :key="code" class="field">
            <v-label>
              {{ field.label || code }}
              <div v-if="!readonly && ['markdown', 'plaintext', 'string', 'text'].includes(field.type)" class="actions">
                <v-menu>
                  <template #activator="{ props }">
                    <v-btn v-bind="props"
                      :title="$gettext('Translate %{code} field', {code: code})"
                      :loading="translating[idx+code]"
                      icon="mdi-translate"
                      variant="text"
                      elevation="0"
                    />
                  </template>
                  <v-list>
                    <v-list-item v-for="lang in txlocales()" :key="lang.code">
                      <v-btn
                        @click="translateText(idx, code, lang.code)"
                        prepend-icon="mdi-arrow-right-thin"
                        variant="text"
                      >{{ lang.name }}</v-btn>
                    </v-list-item>
                  </v-list>
                </v-menu>
                <v-btn
                  :title="$gettext('Generate text for %{code} field', {code: code})"
                  :loading="composing[idx+code]"
                  @click="composeText(idx, code)"
                  icon="mdi-creation"
                  variant="text"
                  elevation="0"
                />
              </div>
            </v-label>
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

  .v-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-transform: capitalize;
    font-weight: bold;
    margin-bottom: 4px;
  }
</style>
