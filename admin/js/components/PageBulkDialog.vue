/** @license MIT, https://opensource.org/license/mit */

<script>
import CmsDialog from './Dialog.vue'
import { useAppStore, useSchemaStore } from '../stores'
import { domain } from '../rules'
import { cacheItems, locales, PAGE_BULK_LIMIT, statusItems } from '../utils'


export default {
  components: {
    CmsDialog
  },

  props: {
    modelValue: { type: Boolean, required: true },
    count: { type: Number, default: 0 },
    descendants: { type: Number, default: 0 }
  },

  emits: ['apply', 'update:modelValue'],

  data() {
    return {
      valid: true,
      enabled: {
        status: false,
        cache: false,
        theme: false,
        type: false,
        tag: false,
        lang: false,
        domain: false
      },
      values: { status: 1, cache: 5, theme: '', type: '', tag: '', lang: '', domain: '' }
    }
  },

  setup() {
    const schemas = useSchemaStore()
    const app = useAppStore()

    return { app, schemas, locales, cacheItems: cacheItems(), statusItems: statusItems() }
  },

  created() {
    // the page list view never loads theme schemas (only the detail view does),
    // so the theme/type dropdowns would be empty without this
    this.schemas.load()
  },

  computed: {
    domainRules() {
      return [domain(this.$gettext)]
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
      this.enabled = {
        status: false,
        cache: false,
        theme: false,
        type: false,
        tag: false,
        lang: false,
        domain: false
      }
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
  <CmsDialog
    :model-value="modelValue"
    :title="$gettext('Edit properties')"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="600"
  >
    <p class="hint">
      {{
        $ngettext(
          'Apply the selected properties to %{num} page.',
          'Apply the selected properties to %{num} pages.',
          count,
          { num: count }
        )
      }}
    </p>

    <v-form v-model="valid">
      <div class="prop" :class="{ on: enabled.status }">
        <v-checkbox-btn v-model="enabled.status" :aria-label="$gettext('Change status')" />
        <v-select
          :items="statusItems"
          :modelValue="values.status"
          @update:modelValue="set('status', $event)"
          :label="$gettext('Status') + ' ‒ ' + $gettext('Disabled pages are offline, hidden pages are online but not shown in the navigation')"
          variant="underlined"
          item-title="val"
          item-value="key"
          hide-details="auto"
        />
      </div>

      <div class="prop" :class="{ on: enabled.cache }">
        <v-checkbox-btn v-model="enabled.cache" :aria-label="$gettext('Change cache time')" />
        <v-select
          :items="cacheItems"
          :modelValue="values.cache"
          @update:modelValue="set('cache', $event)"
          :label="$gettext('Cache time') + ' ‒ ' + $gettext('How long the generated page is cached before it is created again')"
          variant="underlined"
          item-title="val"
          item-value="key"
          hide-details="auto"
        />
      </div>

      <div class="prop" :class="{ on: enabled.lang }">
        <v-checkbox-btn v-model="enabled.lang" :aria-label="$gettext('Change language')" />
        <v-select
          :items="locales()"
          :modelValue="values.lang"
          @update:modelValue="set('lang', $event)"
          :label="$gettext('Language') + ' ‒ ' + $gettext('Language of the page content')"
          variant="underlined"
          hide-details="auto"
        />
      </div>

      <div class="prop" :class="{ on: enabled.theme }">
        <v-checkbox-btn v-model="enabled.theme" :aria-label="$gettext('Change theme')" />
        <v-select
          :items="Object.keys(schemas.themes)"
          :modelValue="values.theme"
          @update:modelValue="set('theme', $event)"
          :label="$gettext('Theme') + ' ‒ ' + $gettext('Design used to display the page')"
          variant="underlined"
          hide-details="auto"
        />
      </div>

      <div class="prop" :class="{ on: enabled.type }">
        <v-checkbox-btn v-model="enabled.type" :aria-label="$gettext('Change page type')" />
        <v-select
          :items="typeItems"
          :modelValue="values.type"
          @update:modelValue="set('type', $event)"
          :label="$gettext('Page type') + ' ‒ ' + $gettext('Template of the theme which defines the page layout')"
          variant="underlined"
          hide-details="auto"
        />
      </div>

      <div class="prop" :class="{ on: enabled.tag }">
        <v-checkbox-btn v-model="enabled.tag" :aria-label="$gettext('Change tag')" />
        <v-text-field
          :modelValue="values.tag"
          @update:modelValue="set('tag', $event)"
          :label="$gettext('Page tag') + ' ‒ ' + $gettext('Internal identifier to find the page in templates, e.g. blog')"
          variant="underlined"
          maxlength="30"
          counter="30"
          hide-details="auto"
        />
      </div>

      <div v-if="app.multidomain" class="prop" :class="{ on: enabled.domain }">
        <v-checkbox-btn v-model="enabled.domain" :aria-label="$gettext('Change domain')" />
        <v-text-field
          :rules="domainRules"
          :modelValue="values.domain"
          @update:modelValue="set('domain', $event)"
          :label="$gettext('Domain') + ' ‒ ' + $gettext('Domain the page is available at, e.g. example.com')"
          variant="underlined"
          maxlength="255"
          counter="255"
        />
      </div>
    </v-form>

    <template #actions>
      <v-btn
        @click="apply(false)"
        :disabled="!hasInput || !valid || limited"
        data-confirm
        class="btn-apply"
        variant="tonal"
        color="primary"
        >{{ $gettext('Apply') }}</v-btn
      >
      <v-btn
        v-if="descendants > 0"
        @click="apply(true)"
        :disabled="!hasInput || !valid || recurseLimited"
        class="btn-apply-recursive"
        variant="outlined"
        >{{ $gettext('Apply recursively') }} ({{ count + descendants }})</v-btn
      >
    </template>

    <template #footer>
      <p v-if="limited || recurseLimited" class="hint limit">
        {{ $gettext('Page bulk changes are limited to 1,000 pages') }}
      </p>
    </template>
  </CmsDialog>
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
