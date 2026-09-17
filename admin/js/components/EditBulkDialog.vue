/** @license MIT, https://opensource.org/license/mit */

<script>
import CmsDialog from './Dialog.vue'
import { locales } from '../utils'

export default {
  components: {
    CmsDialog
  },

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
    return { locales }
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
  <CmsDialog
    :model-value="modelValue"
    :title="$gettext('Edit properties')"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="600"
  >
    <p class="hint">
      {{
        $ngettext(
          'Apply the selected properties to %{num} entry.',
          'Apply the selected properties to %{num} entries.',
          count,
          { num: count }
        )
      }}
    </p>

    <v-select
      :items="locales()"
      :modelValue="lang"
      @update:modelValue="lang = $event"
      :label="$gettext('Language')"
      variant="underlined"
      hide-details
    />

    <template #actions>
      <v-btn @click="apply()" :disabled="lang === null" class="btn-apply" variant="text">{{
        $gettext('Apply')
      }}</v-btn>
    </template>
  </CmsDialog>
</template>

<style scoped>
.hint {
  color: rgb(var(--v-theme-on-surface));
  margin-bottom: 16px;
}
</style>
