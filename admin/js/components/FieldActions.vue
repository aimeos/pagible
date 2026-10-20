/** @license MIT, https://opensource.org/license/mit */

<script>
/**
 * AI actions for text fields: translate, generate and dictate
 * Emits "update" with the new value if an action succeeded
 */
import ActionMenu from './ActionMenu.vue'
import { useUserStore } from '../stores'
import { aiContext } from '../fieldtypes'
import { useDictation } from '../dictation'
import { txLocaleItems } from '../options'
import { mdiTranslate, mdiArrowRightThin, mdiCreation, mdiMicrophoneOutline, mdiMicrophone } from '@mdi/js'

export default {
  components: { ActionMenu },

  props: {
    code: { type: String, required: true },
    field: { type: Object, default: () => ({}) },
    value: { type: String, default: '' },
    // surrounding data passed to the AI as context
    context: { type: Object, default: () => ({}) },
    // used for generating if the field value is empty
    prompt: { type: String, default: '' }
  },

  emits: ['update'],

  inject: ['write', 'translate'],

  data() {
    return {
      composing: false,
      dictation: useDictation(),
      translating: false
    }
  },

  setup() {
    const user = useUserStore()

    return {
      user,
      mdiTranslate,
      mdiArrowRightThin,
      mdiCreation,
      mdiMicrophoneOutline,
      mdiMicrophone,
      txLocaleItems
    }
  },

  beforeUnmount() {
    this.dictation.stop()
  },

  methods: {
    generate() {
      const context = aiContext(this.field, this.code, this.context)

      this.composing = true

      return this.write(this.value || this.prompt, context)
        .then((result) => {
          // false if denied, undefined on errors
          if (typeof result === 'string') {
            this.$emit('update', result)
          }
        })
        .finally(() => {
          this.composing = false
        })
    },

    record() {
      // dictated text is appended so the existing content isn't lost
      this.dictation.toggle((text) => this.$emit('update', (this.value ? this.value + ' ' : '') + text))
    },

    translateTo(lang) {
      this.translating = true

      return this.translate([this.value], lang)
        .then((result) => {
          // false if denied, undefined on errors
          if (Array.isArray(result)) {
            this.$emit('update', result[0] || '')
          }
        })
        .finally(() => {
          this.translating = false
        })
    }
  }
}
</script>

<template>
  <span class="btn-translate">
    <ActionMenu v-if="user.can('text:translate')" :title="$gettext('Translate')" location="end center">
      <template #activator="{ props }">
        <v-btn
          v-bind="props"
          :title="$gettext('Translate')"
          :loading="translating"
          :icon="mdiTranslate"
          variant="text"
        />
      </template>

      <v-list-item v-for="lang in txLocaleItems()" :key="lang.code">
        <v-btn @click="translateTo(lang.code)" :prepend-icon="mdiArrowRightThin" variant="text">{{
          lang.name
        }}</v-btn>
      </v-list-item>
    </ActionMenu>
  </span>
  <v-btn
    v-if="user.can('text:write')"
    :title="$gettext('Generate text')"
    :loading="composing"
    @click="generate()"
    :icon="mdiCreation"
    class="btn-generate"
    variant="text"
  />
  <v-btn
    v-if="user.can('audio:transcribe')"
    @click="record()"
    :class="['btn-dictate', { dictating: dictation.active() }]"
    :aria-pressed="String(dictation.active())"
    :icon="dictation.active() ? mdiMicrophoneOutline : mdiMicrophone"
    :title="$gettext('Dictate')"
    :loading="dictation.busy()"
    variant="text"
  />
</template>
