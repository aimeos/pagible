/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiArrowRightCircle, mdiCheckBold, mdiHelpCircleOutline, mdiMicrophone, mdiMicrophoneOutline } from '@mdi/js'
import { useUserStore } from '../stores'
import { useDictation } from '../dictation'
import { composing } from '../utils'

/**
 * AI prompt with help, dictation and send button which emits "submit" on Enter or the arrow.
 * The default slot contains the help list items shown when toggling the help.
 */
export default {
  name: 'AiPrompt',

  props: {
    // the prompt is processed, submitting again is ignored
    busy: { type: Boolean, default: false },
    // the last prompt was processed successfully
    done: { type: Boolean, default: false },
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    sendTitle: { type: String, default: '' },
    type: { type: String, required: true }
  },

  emits: ['submit', 'update:modelValue'],

  data() {
    return {
      dictation: useDictation(),
      help: false
    }
  },

  setup() {
    return {
      user: useUserStore(),
      mdiArrowRightCircle,
      mdiCheckBold,
      mdiHelpCircleOutline,
      mdiMicrophone,
      mdiMicrophoneOutline
    }
  },

  beforeUnmount() {
    this.dictation.stop()
  },

  computed: {
    text: {
      get() {
        return this.modelValue
      },
      set(value) {
        this.$emit('update:modelValue', value)
      }
    }
  },

  methods: {
    keydown(e) {
      if (composing(e) || e.shiftKey) {
        return // let IME compose, and Shift+Enter insert a newline instead of opening the chat
      }
      e.preventDefault()
      this.submit()
    },

    submit() {
      if (!this.busy) {
        this.$emit('submit')
      }
    },

    record() {
      // dictated text is appended so the existing prompt isn't lost
      this.dictation.toggle((text) => (this.text = (this.text ? this.text + ' ' : '') + text))
    }
  }
}
</script>

<template>
  <v-textarea
    v-model="text"
    :placeholder="placeholder || $gettext('What shall I do for you?') + ' ' + $gettext('Press Enter to open the chat')"
    :aria-label="placeholder || $gettext('What shall I do for you?')"
    :loading="busy"
    @keydown.enter="keydown"
    variant="outlined"
    class="prompt"
    rounded="lg"
    hide-details
    auto-grow
    clearable
    rows="1"
  >
    <template #prepend>
      <v-btn
        @click="help = !help"
        :icon="mdiHelpCircleOutline"
        class="no-rtl"
        :title="help ? $gettext('Hide help') : $gettext('Show help')"
        :aria-expanded="help"
        :aria-controls="type + '-help'"
        variant="text"
      />
    </template>
    <template #append>
      <v-btn
        v-if="text || done"
        @click="submit()"
        :icon="done ? mdiCheckBold : mdiArrowRightCircle"
        :title="sendTitle || $gettext('Send')"
        :loading="busy"
        variant="text"
      />
      <v-btn
        v-else-if="user.can('audio:transcribe')"
        @click="record()"
        :icon="dictation.active() ? mdiMicrophoneOutline : mdiMicrophone"
        :title="$gettext('Dictate')"
        :class="{ dictating: dictation.active() }"
        :aria-pressed="String(dictation.active())"
        :loading="dictation.busy()"
        variant="text"
      />
    </template>
  </v-textarea>
  <div v-if="help" :id="type + '-help'" class="help">
    <ul :aria-label="$gettext('Help')">
      <slot />
    </ul>
  </div>
</template>

<style scoped>
.prompt {
  margin-bottom: 16px;
}

.v-input--horizontal :deep(.v-input__prepend),
.v-input--horizontal :deep(.v-input__append) {
  margin: 0;
}
</style>
