/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import { mdiClose } from '@mdi/js'
import { locales } from '../utils'

export default {
  props: {
    modelValue: { type: Boolean, required: true },
    count: { type: Number, default: 0 }
  },

  emits: ['apply', 'update:modelValue'],

  data() {
    return {
      lang: null
    }
  },

  setup() {
    return { locales, mdiClose }
  },

  methods: {
    apply() {
      if (this.lang === null) {
        return
      }

      this.$emit('apply', this.lang)
      this.$emit('update:modelValue', false)
    },

    reset() {
      this.lang = null
    }
  },

  watch: {
    modelValue(open) {
      if (open) {
        this.reset()
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
          {{ $ngettext('Apply the selected properties to %{num} entry.', 'Apply the selected properties to %{num} entries.', count, { num: count }) }}
        </p>

        <v-select
          :items="locales()"
          :modelValue="lang"
          @update:modelValue="lang = $event"
          :label="$gettext('Language')"
          variant="underlined"
          hide-details
        />
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn
          @click="apply()"
          :disabled="lang === null"
          class="btn-apply"
          variant="text"
        >{{ $gettext('Apply') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.hint {
  color: rgb(var(--v-theme-on-surface));
  margin-bottom: 16px;
}
</style>
