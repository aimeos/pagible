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
    },

    emits: ['update:modelValue', 'error', 'addFile', 'removeFile'],

    data() {
      return {
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


      remove(idx) {
        this.items.splice(idx, 1)
        this.$emit('update:modelValue', this.items)
        this.validate()
      },


      title(item) {
        return Object.values(item || {})
          .map(v => v && typeof v !== 'object' && typeof v !== 'boolean' ? v : null)
          .filter(v => !!v)
          .join(' - ')
          .substring(0, 50) || ''
      },


      toName(type) {
        return type?.charAt(0)?.toUpperCase() + type?.slice(1)
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
    <VueDraggable v-model="items" :disabled="readonly" @change="change()" draggable=".item" group="items" animation="500">

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
            <v-label>{{ field.label || code }}</v-label>
            <component :is="toName(field.type)"
              v-model="items[idx][code]"
              @addFile="$emit('addFile', $event)"
              @removeFile="$emit('removeFile', $event)"
              :readonly="readonly"
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

  .field {
    margin: 24px 0;
  }

  label {
    font-weight: bold;
    text-transform: capitalize;
    margin-bottom: 4px;
  }
</style>
