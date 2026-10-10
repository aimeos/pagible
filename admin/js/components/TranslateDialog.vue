/** @license MIT, https://opensource.org/license/mit */

<script>
import CmsDialog from './Dialog.vue'
import { useLanguageStore } from '../stores'

export default {
  components: {
    CmsDialog
  },

  props: {
    modelValue: { type: Boolean, required: true },
    // number of selected pages
    count: { type: Number, default: 0 },
    // languages checked when the dialog opens
    langs: { type: Array, default: () => [] },
    // number of pages matching the filter, NULL while unknown or if only the selected pages can be translated
    total: { type: Number, default: null },
    // the page list is limited by a filter or search term
    filtered: { type: Boolean, default: false }
  },

  emits: ['apply', 'update:modelValue'],

  data() {
    return {
      all: false,
      checked: []
    }
  },

  setup() {
    const languages = useLanguageStore()
    return { languages }
  },

  computed: {
    // all pages are only offered if there are more than the selected ones
    allowAll() {
      return this.total !== null && this.total > this.count
    },

    items() {
      return this.languages.available.map((code) => ({ code, name: this.languages.translate(code) }))
    }
  },

  methods: {
    apply() {
      if (!this.checked.length) {
        return
      }

      this.$emit('apply', { langs: [...this.checked], all: this.all && this.allowAll })
      this.$emit('update:modelValue', false)
    }
  },

  watch: {
    modelValue: {
      immediate: true,
      handler(open) {
        if (open) {
          this.all = false
          this.checked = this.langs.filter((code) => this.languages.available.includes(code))
        }
      }
    }
  }
}
</script>

<template>
  <CmsDialog
    :model-value="modelValue"
    :title="$gettext('Translate')"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="600"
  >
    <p class="hint">{{ $gettext('Translate into') }}</p>

    <div class="langs" role="group" :aria-label="$gettext('Languages')">
      <v-checkbox
        v-for="item in items"
        :key="item.code"
        v-model="checked"
        :value="item.code"
        :label="item.name"
        :data-lang="item.code"
        class="translate-lang"
        density="compact"
        hide-details
      />
    </div>

    <v-radio-group v-if="allowAll" v-model="all" class="translate-scope" hide-details>
      <v-radio
        :value="false"
        :label="$ngettext('Selected page (%{num})', 'Selected pages (%{num})', count, { num: count })"
        class="scope-selected"
      />
      <v-radio
        :value="true"
        :label="
          filtered
            ? $gettext('All pages matching the filter (%{num})', { num: total })
            : $gettext('All pages (%{num})', { num: total })
        "
        class="scope-all"
      />
    </v-radio-group>
    <p v-else class="hint">
      {{ $ngettext('%{num} page selected.', '%{num} pages selected.', count, { num: count }) }}
    </p>

    <p class="note">
      {{
        $gettext(
          'Only missing and outdated translations are created. Pages in their source language and up to date translations are skipped. The translations run in the background and are saved as drafts for review.'
        )
      }}
    </p>

    <template #actions>
      <v-btn @click="$emit('update:modelValue', false)" variant="text">{{ $gettext('Cancel') }}</v-btn>
      <v-btn @click="apply()" :disabled="!checked.length" class="btn-translate" color="primary" variant="tonal">
        {{ $gettext('Translate') }}
      </v-btn>
    </template>
  </CmsDialog>
</template>

<style scoped>
.hint {
  color: rgb(var(--v-theme-on-surface));
  margin-bottom: 8px;
}

.langs {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  margin-bottom: 16px;
}

.translate-scope {
  margin-bottom: 16px;
}

.note {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  margin: 0;
}
</style>
