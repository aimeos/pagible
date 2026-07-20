/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiClose } from '@mdi/js'
import { useAppStore, useSchemaStore } from '../stores'
import { locales, PAGE_BULK_LIMIT } from '../utils'

const DOMAIN_REGEX = /^([0-9a-z]+[.-])*[0-9a-z]+\.[a-z]{2,}$/

export default {
  props: {
    modelValue: { type: Boolean, required: true },
    count: { type: Number, default: 0 },
    descendants: { type: Number, default: 0 }
  },

  emits: ['apply', 'update:modelValue'],

  data() {
    return {
      valid: true,
      enabled: { status: false, cache: false, theme: false, type: false, tag: false, lang: false, domain: false },
      values: { status: 1, cache: 5, theme: '', type: '', tag: '', lang: '', domain: '' },
      cacheItems: [],
      statusItems: []
    }
  },

  setup() {
    const schemas = useSchemaStore()
    const app = useAppStore()

    return { app, schemas, locales, mdiClose }
  },

  created() {
    // the page list view never loads theme schemas (only the detail view does),
    // so the theme/type dropdowns would be empty without this
    this.schemas.load()

    this.cacheItems = [
      { key: 0, val: this.$gettext('No cache') },
      { key: 1, val: this.$ngettext('%{num} minute', '%{num} minutes', 1, { num: 1 }) },
      { key: 5, val: this.$ngettext('%{num} minute', '%{num} minutes', 5, { num: 5 }) },
      { key: 15, val: this.$ngettext('%{num} minute', '%{num} minutes', 15, { num: 15 }) },
      { key: 30, val: this.$ngettext('%{num} minute', '%{num} minutes', 30, { num: 30 }) },
      { key: 60, val: this.$ngettext('%{num} hour', '%{num} hours', 1, { num: 1 }) },
      { key: 180, val: this.$ngettext('%{num} hour', '%{num} hours', 3, { num: 3 }) },
      { key: 360, val: this.$ngettext('%{num} hour', '%{num} hours', 6, { num: 6 }) },
      { key: 720, val: this.$ngettext('%{num} hour', '%{num} hours', 12, { num: 12 }) },
      { key: 1440, val: this.$ngettext('%{num} hour', '%{num} hours', 24, { num: 24 }) }
    ]
    this.statusItems = [
      { key: 0, val: this.$gettext('Disabled') },
      { key: 1, val: this.$gettext('Enabled') },
      { key: 2, val: this.$gettext('Hidden in navigation') }
    ]
  },

  computed: {
    domainRules() {
      return [(v) => !v || DOMAIN_REGEX.test(v) || this.$gettext('Domain name is invalid')]
    },

    hasInput() {
      return Object.values(this.enabled).some((val) => val)
    },

    input() {
      const input = {}

      for (const key in this.enabled) {
        if (this.enabled[key]) {
          input[key] = this.values[key]
        }
      }

      return input
    },

    limited() {
      return this.count > PAGE_BULK_LIMIT
    },

    recurseLimited() {
      return this.count + this.descendants > PAGE_BULK_LIMIT
    },

    typeItems() {
      return Object.keys(this.schemas.themes[this.values.theme || 'cms']?.types || {})
    }
  },

  methods: {
    apply(descendants) {
      if (!this.hasInput || !this.valid) {
        return
      }

      this.$emit('apply', { input: this.input, descendants: descendants })
      this.$emit('update:modelValue', false)
    },

    reset() {
      this.enabled = { status: false, cache: false, theme: false, type: false, tag: false, lang: false, domain: false }
      this.values = { status: 1, cache: 5, theme: '', type: '', tag: '', lang: '', domain: '' }
    },

    set(key, value) {
      this.values[key] = value
      this.enabled[key] = true
    }
  },

  watch: {
    modelValue(open) {
      if (open) {
        this.reset()
      }
    },

    'values.theme'() {
      if (!this.typeItems.includes(this.values.type)) {
        this.values.type = ''
        this.enabled.type = false
      }
    }
  }
}
</script>

<template>
  <v-dialog
    :aria-label="$gettext('Edit properties')"
    :modelValue="modelValue"
    @afterLeave="$emit('update:modelValue', false)"
    max-width="600"
  >
    <v-card>
      <v-toolbar density="compact">
        <v-toolbar-title>{{ $gettext('Edit properties') }}</v-toolbar-title>
        <v-btn :icon="mdiClose" :aria-label="$gettext('Close')" @click="$emit('update:modelValue', false)" />
      </v-toolbar>

      <v-card-text>
        <p class="hint">
          {{ $ngettext('Apply the selected properties to %{num} page.', 'Apply the selected properties to %{num} pages.', count, { num: count }) }}
        </p>

        <v-form v-model="valid">
          <div class="prop" :class="{ on: enabled.status }">
            <v-checkbox-btn v-model="enabled.status" :aria-label="$gettext('Change status')" />
            <v-select
              :items="statusItems"
              :modelValue="values.status"
              @update:modelValue="set('status', $event)"
              :label="$gettext('Status')"
              variant="underlined"
              item-title="val"
              item-value="key"
              hide-details
            />
          </div>

          <div class="prop" :class="{ on: enabled.cache }">
            <v-checkbox-btn v-model="enabled.cache" :aria-label="$gettext('Change cache time')" />
            <v-select
              :items="cacheItems"
              :modelValue="values.cache"
              @update:modelValue="set('cache', $event)"
              :label="$gettext('Cache time')"
              variant="underlined"
              item-title="val"
              item-value="key"
              hide-details
            />
          </div>

          <div class="prop" :class="{ on: enabled.lang }">
            <v-checkbox-btn v-model="enabled.lang" :aria-label="$gettext('Change language')" />
            <v-select
              :items="locales()"
              :modelValue="values.lang"
              @update:modelValue="set('lang', $event)"
              :label="$gettext('Language')"
              variant="underlined"
              hide-details
            />
          </div>

          <div class="prop" :class="{ on: enabled.theme }">
            <v-checkbox-btn v-model="enabled.theme" :aria-label="$gettext('Change theme')" />
            <v-select
              :items="Object.keys(schemas.themes)"
              :modelValue="values.theme"
              @update:modelValue="set('theme', $event)"
              :label="$gettext('Theme')"
              variant="underlined"
              hide-details
            />
          </div>

          <div class="prop" :class="{ on: enabled.type }">
            <v-checkbox-btn v-model="enabled.type" :aria-label="$gettext('Change page type')" />
            <v-select
              :items="typeItems"
              :modelValue="values.type"
              @update:modelValue="set('type', $event)"
              :label="$gettext('Page type')"
              variant="underlined"
              hide-details
            />
          </div>

          <div class="prop" :class="{ on: enabled.tag }">
            <v-checkbox-btn v-model="enabled.tag" :aria-label="$gettext('Change tag')" />
            <v-text-field
              :modelValue="values.tag"
              @update:modelValue="set('tag', $event)"
              :label="$gettext('Page tag')"
              variant="underlined"
              maxlength="30"
              counter="30"
              hide-details
            />
          </div>

          <div v-if="app.multidomain" class="prop" :class="{ on: enabled.domain }">
            <v-checkbox-btn v-model="enabled.domain" :aria-label="$gettext('Change domain')" />
            <v-text-field
              :rules="domainRules"
              :modelValue="values.domain"
              @update:modelValue="set('domain', $event)"
              :label="$gettext('Domain')"
              variant="underlined"
              maxlength="255"
              counter="255"
            />
          </div>
        </v-form>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn
          @click="apply(false)"
          :disabled="!hasInput || !valid || limited"
          class="btn-apply"
          variant="outlined"
        >{{ $gettext('Apply') }}</v-btn>
        <v-btn
          v-if="descendants > 0"
          @click="apply(true)"
          :disabled="!hasInput || !valid || recurseLimited"
          class="btn-apply-recursive"
          variant="outlined"
        >{{ $gettext('Apply recursively') }} ({{ count + descendants }})</v-btn>
      </v-card-actions>

      <p v-if="limited || recurseLimited" class="hint limit">
        {{ $gettext('Page bulk changes are limited to 1,000 pages') }}
      </p>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.hint {
  color: rgb(var(--v-theme-on-surface));
  margin-bottom: 16px;
}

.hint.limit {
  padding: 0 24px 16px;
}

.prop {
  display: flex;
  align-items: center;
  gap: 12px;
}

.prop:not(.on) {
  opacity: 0.6;
}

.prop > .v-input {
  flex: 1 1 auto;
  min-width: 0;
}
</style>
