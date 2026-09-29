/** @license MIT, https://opensource.org/license/mit */

<script>
import { markRaw } from 'vue'
import { Marked } from 'marked'
import DOMPurify from 'dompurify'
import {
  mdiBroom,
  mdiContentCopy,
  mdiCreation,
  mdiMicrophone,
  mdiMicrophoneOutline,
  mdiSend,
  mdiStop
} from '@mdi/js'
import { useUserStore } from '../stores'
import { chat } from '../chat'
import CmsDialog from './Dialog.vue'

// GFM (tables, task lists, strikethrough) with single newlines as <br> to match chat expectations.
// A dedicated instance avoids mutating marked's global options; DOMPurify sanitizes the output.
const md = new Marked({ gfm: true, breaks: true })

export default {
  name: 'ChatDialog',

  components: {
    CmsDialog
  },

  props: {
    context: { type: String, default: '' },
    modelValue: { type: Boolean, default: false },
    permission: { type: String, default: 'page:chat' }
  },

  emits: ['done', 'update:modelValue'],

  data() {
    return {
      audio: null,
      busy: false,
      dictating: false,
      historyDraft: '',
      historyIndex: null,
      input: '',
      messages: [],
      seq: 0 // monotonic id source for stable message keys (splice/concurrent turns must not reindex)
    }
  },

  setup() {
    const user = useUserStore()

    return {
      user,
      mdiBroom,
      mdiContentCopy,
      mdiCreation,
      mdiMicrophone,
      mdiMicrophoneOutline,
      mdiSend,
      mdiStop
    }
  },

  computed: {
    open: {
      get() {
        return this.modelValue
      },
      set(val) {
        this.$emit('update:modelValue', val)
      }
    }
  },

  watch: {
    open(val) {
      // Closing the dialog mid-stream aborts the request so the server stops generating and frees
      // the per-user lock, instead of leaving it running unseen.
      if (!val && this.busy) {
        this.stop()
      }
    }
  },

  methods: {
    clean(text) {
      // Normalize whitespace (collapse blank-line runs, trim ends) before copying or building history.
      return (text || '').replace(/\n{3,}/g, '\n\n').trim()
    },

    clear() {
      if (!this.busy) {
        this.historyDraft = ''
        this.historyIndex = null
        this.messages = []
      }
    },

    copy(text) {
      navigator.clipboard?.writeText(this.clean(text))
    },

    keydown(e) {
      if (e.isComposing) {
        return // don't submit while an IME candidate is being composed (CJK input)
      }
      if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
        this.recall(e, e.key === 'ArrowUp' ? -1 : 1)
        return
      }
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        this.send()
      }
    },

    recall(e, offset) {
      if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) {
        return
      }

      const entries = this.messages.filter((m) => m.role === 'user').map((m) => m.content)

      if (!entries.length || (this.historyIndex === null && offset > 0)) {
        return
      }

      if (this.historyIndex === null) {
        this.historyDraft = this.input
        this.historyIndex = entries.length
      }

      e.preventDefault()
      const index = Math.max(0, Math.min(entries.length, this.historyIndex + offset))

      this.input = index < entries.length ? entries[index] : this.historyDraft
      this.historyIndex = index < entries.length ? index : null
    },

    record() {
      if (!this.audio) {
        this.audio = markRaw(import('../audio').then((mod) => mod.recording().start()))
        return
      }

      this.audio.then((rec) => {
        this.dictating = true
        this.audio = null

        rec.stop()?.then((buffer) => {
          import('../ai')
            .then((mod) => mod.transcribe(buffer))
            .then((transcription) => {
              this.input = (this.input ? this.input + ' ' : '') + transcription.asText()
            })
            .finally(() => {
              this.dictating = false
            })
        })
      })
    },

    render(text) {
      return DOMPurify.sanitize(md.parse(text || ''))
    },

    scrollDown() {
      // Coalesce the per-chunk scroll calls into at most one per frame to avoid layout thrashing
      if (this.scrollPending) {
        return
      }

      this.scrollPending = true

      requestAnimationFrame(() => {
        this.scrollPending = false

        const el = this.$refs.list
        if (el) {
          el.scrollTop = el.scrollHeight
        }
      })
    },

    send(text) {
      text = String(text ?? this.input).trim()

      if (!text || this.busy || !this.user.can(this.permission)) {
        return
      }

      // Prior turns only - the endpoint appends the current prompt as the next user message. Drop
      // errored turns and inline tool markers, and cap to the last 20 turns the server keeps anyway
      // (so clean() runs a bounded number of times); the server re-enforces user/assistant alternation.
      const history = this.messages
        .filter((m) => !m.error)
        .slice(-20)
        .map((m) => ({ role: m.role, content: this.clean(m.content) }))
        .filter((m) => m.content)

      this.input = ''
      this.historyDraft = ''
      this.historyIndex = null
      this.busy = true
      const controller = (this.controller = new AbortController())

      this.messages.push({ id: ++this.seq, role: 'user', content: text })
      this.messages.push({
        id: ++this.seq,
        role: 'assistant',
        content: '',
        blocks: [],
        pending: '',
        mark: 0,
        scanned: 0,
        fenceOpen: false,
        streaming: true
      })

      const assistant = this.messages[this.messages.length - 1]
      this.scrollDown()

      chat(
        text,
        history,
        (delta) => {
          const text = delta.text || ''
          // A blank line may close one or more blocks; render & cache those once (never
          // re-sanitized or re-parsed). Track ``` fences incrementally - counting only the
          // line-start delimiters marked treats as code fences in the newly-arrived span (so
          // inline/prose backticks don't false-open one) - so a blank line inside an open fenced
          // code block defers its commit without an O(n^2) re-scan.
          const boundary = (assistant.content.slice(-1) + text).includes('\n\n')
          assistant.content += text
          assistant.pending += text // accumulate the raw tail incrementally (O(chunk), not O(tail))

          if (boundary) {
            const end = assistant.content.lastIndexOf('\n\n')

            if (end >= assistant.scanned) {
              if (
                (assistant.content.slice(assistant.scanned, end).match(/^```/gm) || []).length % 2
              ) {
                assistant.fenceOpen = !assistant.fenceOpen
              }
              assistant.scanned = end

              if (!assistant.fenceOpen) {
                assistant.blocks.push(this.render(assistant.content.slice(assistant.mark, end)))
                assistant.mark = end + 2
                assistant.scanned = end + 2
                assistant.pending = assistant.content.slice(assistant.mark) // reset tail after a commit
              }
            }
          }

          this.scrollDown()
        },
        controller.signal,
        this.context
      )
        .then((result) => {
          // onDelta normally fills the bubble; fall back to the resolved text if it stayed empty.
          // Stop before anything streamed resolves (AbortError) with '' - don't fabricate a "Done"
          // answer for a cancelled turn; the finally drops its empty bubble instead.
          if (!assistant.content && !controller.signal.aborted) {
            assistant.content = result || this.$gettext('Done')
          }
        })
        .catch((error) => {
          assistant.error = true
          if (!assistant.content) {
            // Errors flagged `shown` carry a translated, user-facing message (lock / rate-limit /
            // unavailable); show it verbatim. Everything else gets the generic fallback.
            assistant.content = error?.shown
              ? error.message
              : this.$gettext('Sorry, something went wrong.')
          }
        })
        .finally(() => {
          // Cache the final open block (everything past the last cached block) as the last chunk
          if (assistant.mark < assistant.content.length) {
            assistant.blocks.push(this.render(assistant.content.slice(assistant.mark)))
            assistant.mark = assistant.content.length
          }
          assistant.pending = ''
          assistant.streaming = false
          // Only clear the shared turn state if a newer turn (Stop + fast resend) hasn't taken over
          if (this.controller === controller) {
            this.busy = false
            this.controller = null
          }

          if (assistant.content) {
            // Any turn that streamed output may have created or changed pages via tool calls - even
            // one that errored or was stopped mid-way, since AddPage persists immediately. So reload
            // the list (on dialog close) regardless of the error flag; a no-op reload is cheap.
            this.$emit('done')
          } else {
            // Cancelled before any output: drop the empty assistant bubble so it doesn't linger.
            const idx = this.messages.indexOf(assistant)
            if (idx !== -1) {
              this.messages.splice(idx, 1)
            }
          }

          this.scrollDown()
        })
    },

    stop() {
      // Abort the request; chat() then resolves (AbortError) and the send() finally folds the
      // trailing block in and clears streaming - so the open tail isn't hidden before it's rendered.
      this.busy = false
      this.controller?.abort()
    }
  }
}
</script>

<template>
  <CmsDialog
    v-model="open"
    :title="$gettext('AI Assistant')"
    :max-width="720"
    content-class="chat-body d-flex flex-column"
    class="chat-dialog"
  >
    <template #toolbar-actions>
      <v-btn
        :icon="mdiBroom"
        :title="$gettext('New chat')"
        :aria-label="$gettext('New chat')"
        :disabled="busy || !messages.length"
        @click="clear()"
      />
    </template>

    <div ref="list" class="chat-messages scroll" role="log" aria-live="polite">
      <div v-if="!messages.length" class="chat-empty">
        <v-avatar color="primary" variant="tonal" size="72" class="chat-empty-icon">
          <v-icon :icon="mdiCreation" size="36" />
        </v-avatar>
        <p>{{ $gettext('What shall I do for you?') }}</p>
      </div>

      <div v-for="m in messages" :key="m.id" class="chat-row" :class="m.role">
        <div class="chat-bubble" :class="{ error: m.error }">
          <!-- Each completed block is rendered once into m.blocks and appended as its own element,
               so neither DOMPurify nor the DOM re-processes earlier blocks. The open trailing block
               (m.pending) is rendered live as markdown too - so single-newline content with no
               blank-line boundary still formats while streaming instead of showing raw markdown
               until the block closes. marked tolerates partial syntax, so the commit is seamless -->
          <template v-if="m.role === 'assistant'">
            <div v-for="(b, j) in m.blocks" :key="j" class="chat-md" v-html="b"></div>
            <div v-if="m.streaming && m.pending" class="chat-md" v-html="render(m.pending)"></div>
          </template>
          <div v-else class="chat-text">{{ m.content }}</div>
          <span v-if="m.streaming && !m.content" class="chat-typing" role="status">
            <span></span><span></span><span></span>
            <span class="chat-sr">{{ $gettext('Thinking ...') }}</span>
          </span>
          <span v-else-if="m.streaming" class="chat-cursor" aria-hidden="true"></span>
          <v-btn
            v-if="m.role === 'assistant' && m.content && !m.streaming"
            :icon="mdiContentCopy"
            :title="$pgettext('clipboard', 'Copy')"
            @click="copy(m.content)"
            size="x-small"
            variant="text"
            class="chat-copy"
          />
        </div>
      </div>
    </div>

    <template #footer>
      <div class="chat-input">
        <v-textarea
          v-model="input"
          :placeholder="$gettext('Send a message ...')"
          @keydown="keydown"
          variant="outlined"
          color="primary"
          rounded="lg"
          rows="1"
          hide-details
          auto-grow
          autofocus
        >
          <template #append-inner>
            <v-btn
              v-if="user.can('audio:transcribe') && !input"
              @click="record()"
              :icon="audio ? mdiMicrophoneOutline : mdiMicrophone"
              :title="$gettext('Dictate')"
              :aria-label="$gettext('Dictate')"
              :class="{ dictating: audio }"
              :loading="dictating"
              variant="tonal"
              size="small"
            />
            <v-btn
              v-if="busy"
              @click="stop()"
              :icon="mdiStop"
              :title="$gettext('Stop')"
              :aria-label="$gettext('Stop')"
              color="error"
              variant="tonal"
              size="small"
            />
            <v-btn
              v-else
              @click="send()"
              :icon="mdiSend"
              :title="$gettext('Send')"
              :aria-label="$gettext('Send')"
              :disabled="!input.trim()"
              color="primary"
              variant="flat"
              size="small"
              class="chat-send"
            />
          </template>
        </v-textarea>
      </div>
    </template>
  </CmsDialog>
</template>

<style scoped>
.chat-messages {
  flex: 1 1 auto;
  min-height: 0;
  height: calc(80vh - 132px);
  overflow-y: auto;
  padding: 20px;
  background: linear-gradient(180deg, rgba(var(--v-theme-primary), 0.06), transparent 120px);
}

.chat-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  text-align: center;
  gap: 12px;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.chat-empty-icon {
  margin-bottom: 4px;
}

.chat-row {
  display: flex;
  margin-bottom: 14px;
  animation: chat-in 0.24s cubic-bezier(0.2, 0.8, 0.2, 1);
}

@keyframes chat-in {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .chat-row {
    animation: none;
  }
}

.chat-row.user {
  justify-content: flex-end;
}

.chat-bubble {
  position: relative;
  min-width: 0;
  max-width: 80%;
  padding: 11px 15px;
  border: 1px solid rgba(var(--v-theme-primary), 0.16);
  border-radius: 16px;
  border-top-left-radius: 4px;
  background-color: rgba(var(--v-theme-primary), 0.06);
  color: rgb(var(--v-theme-on-surface));
}

.chat-row.user .chat-bubble {
  border: 0;
  border-radius: 16px;
  border-top-right-radius: 4px;
  background-color: rgb(var(--v-theme-primary));
  box-shadow: 0 6px 18px -6px rgba(var(--v-theme-primary), 0.45);
  color: rgb(var(--v-theme-on-primary));
}

.chat-bubble.error {
  border-color: rgba(var(--v-theme-error), var(--v-border-opacity));
  background-color: rgba(var(--v-theme-error), var(--v-activated-opacity));
}

/* User bubble: raw text, so honor its own newlines/spacing. */
.chat-text {
  white-space: pre-wrap;
  word-break: break-word;
}

/* Assistant bubble: marked-rendered block HTML, so no pre-wrap (it would turn the newlines
   between blocks into gaps); only <pre> code keeps pre-wrap below. */
.chat-md {
  word-break: break-word;
}

.chat-md :deep(:first-child) {
  margin-top: 0;
}

.chat-md :deep(:last-child) {
  margin-bottom: 0;
}

.chat-md :deep(p) {
  margin: 0 0 8px;
}

.chat-md :deep(h1),
.chat-md :deep(h2),
.chat-md :deep(h3),
.chat-md :deep(h4) {
  margin: 12px 0 8px;
  font-size: 1.05em;
  font-weight: 600;
  line-height: 1.3;
}

.chat-md :deep(ul),
.chat-md :deep(ol) {
  margin: 0 0 8px;
  padding-inline-start: 20px;
}

.chat-md :deep(blockquote) {
  margin: 0 0 8px;
  padding-inline-start: 10px;
  border-inline-start: 3px solid rgba(var(--v-border-color), var(--v-border-opacity));
  opacity: 0.85;
}

.chat-md :deep(code) {
  padding: 1px 4px;
  border-radius: 4px;
  background-color: rgba(var(--v-theme-on-surface), 0.08);
  font-size: 0.875em;
}

.chat-md :deep(pre) {
  margin: 0 0 8px;
  padding: 8px 10px;
  border-radius: 6px;
  background-color: rgba(var(--v-theme-on-surface), 0.04);
  white-space: pre-wrap;
  overflow-x: auto;
}

.chat-md :deep(pre code) {
  padding: 0;
  background: none;
}

.chat-md :deep(table) {
  border-collapse: collapse;
  margin: 0 0 8px;
  font-size: 0.875em;
}

.chat-md :deep(th),
.chat-md :deep(td) {
  padding: 4px 8px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  text-align: left;
}

.chat-md :deep(th) {
  font-weight: 600;
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}

/* "Thinking" dots shown until the first streamed chunk arrives */
.chat-typing {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 1.5em;
}

.chat-typing > span:not(.chat-sr) {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)), rgb(var(--v-theme-secondary)));
  animation: chat-bounce 1.2s ease-in-out infinite;
}

.chat-typing > span:nth-child(2) {
  animation-delay: 0.15s;
}

.chat-typing > span:nth-child(3) {
  animation-delay: 0.3s;
}

@keyframes chat-bounce {
  0%,
  60%,
  100% {
    opacity: 0.35;
    transform: translateY(0);
  }
  30% {
    opacity: 1;
    transform: translateY(-4px);
  }
}

.chat-sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

@media (prefers-reduced-motion: reduce) {
  .chat-typing > span:not(.chat-sr) {
    animation: none;
  }

  .chat-typing > span:not(.chat-sr) {
    opacity: 0.7;
  }
}

.chat-cursor {
  display: inline-block;
  width: 7px;
  height: 1em;
  margin-left: 2px;
  vertical-align: text-bottom;
  background-color: currentColor;
  animation: chat-blink 1s steps(2, start) infinite;
}

@keyframes chat-blink {
  to {
    visibility: hidden;
  }
}

.chat-copy {
  position: absolute;
  top: 2px;
  right: 2px;
  opacity: 0;
  transition: opacity 0.2s;
}

.chat-bubble:hover .chat-copy,
.chat-copy:focus-visible {
  opacity: 0.7;
}

.chat-input {
  flex: 0 0 auto;
  padding: 14px 16px 16px;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.chat-send:not(.v-btn--disabled) {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)), rgb(var(--v-theme-secondary)));
  color: rgb(var(--v-theme-on-primary));
}

.chat-input :deep(textarea) {
  max-height: 160px;
}

@media (hover: none) {
  .chat-copy {
    opacity: 0.6;
  }
}

@media (max-width: 599px) {
  .chat-messages {
    height: calc(88vh - 124px);
    padding: 16px 12px;
  }

  .chat-bubble {
    max-width: 86%;
  }

  .chat-input {
    padding: 12px;
  }
}

.dictating {
  color: rgb(var(--v-theme-error));
}
</style>

<style>
/* Unscoped: the body element belongs to CmsDialog, whose scoped padding wins over layered utilities */
.v-card-text.dialog-body.chat-body {
  padding: 0;
}
</style>
