/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import FileProtect from './FileProtect.vue'
import { defineAsyncComponent } from 'vue'
import FileDetailItemAudio from './FileDetailItemAudio.vue'
import { useUserStore, useLanguageStore, useMessageStore } from '../stores'
import { mdiContentCopy, mdiTranslate, mdiCreation, mdiMicrophoneOutline, mdiMicrophone } from '@mdi/js'
import { relocateFiles } from '../files'
import { useDictation } from '../dictation'
import { localeItems, txLocaleItems } from '../options'
import { fileurl } from '../urls'

const FileDetailItemImage = defineAsyncComponent(() => import('./FileDetailItemImage.vue'))
const FileDetailItemVideo = defineAsyncComponent(() => import('./FileDetailItemVideo.vue'))

// splits WebVTT content into blocks which are kept as they are (header, NOTE, STYLE, REGION)
// and cues whose identifier and timing line are kept while their text is translated
function parseVTT(text) {
  return text
    .replace(/\r\n?/g, '\n')
    .split(/\n[ \t]*\n/)
    .filter((block) => block.trim() !== '')
    .map((block) => {
      const lines = block.split('\n')
      const idx = lines.findIndex((line) => line.includes('-->'))

      if (idx < 0 || /^(WEBVTT|NOTE|STYLE|REGION)\b/.test(lines[0])) {
        return { keep: block }
      }

      return { head: lines.slice(0, idx + 1).join('\n'), text: lines.slice(idx + 1).join('\n') }
    })
}

// rebuilds the WebVTT content with the translated texts of the cues which have a text
function buildVTT(blocks, texts) {
  let idx = 0

  return blocks
    .map((block) => {
      if (block.keep !== undefined) return block.keep
      return block.text ? `${block.head}\n${texts[idx++] ?? block.text}` : block.head
    })
    .join('\n\n')
}

const DESCRIBE_FILE = gql`
  mutation ($file: String!, $lang: String!) {
    describe(file: $file, lang: $lang)
  }
`

export default {
  components: {
    FileDetailItemImage,
    FileDetailItemVideo,
    FileDetailItemAudio,
    FileProtect
  },

  props: {
    item: { type: Object, required: true }
  },

  emits: ['patch', 'sync', 'update:file'],

  data() {
    return {
      dictation: useDictation(),
      loading: {},
      tabtrans: null,
      tabdesc: null
    }
  },

  setup() {
    const languages = useLanguageStore()
    const messages = useMessageStore()
    const user = useUserStore()

    return {
      user,
      languages,
      messages,
      mdiTranslate,
      mdiCreation,
      mdiMicrophoneOutline,
      fileurl,
      localeItems,
      txLocaleItems,
      mdiContentCopy,
      mdiMicrophone
    }
  },

  beforeUnmount() {
    this.dictation.stop()
  },

  computed: {
    desclangs() {
      return [...new Set([...this.languages.available, ...Object.keys(this.item.description || {})])]
    },

    lang() {
      return this.desclangs[0] || this.item.lang || 'en'
    },

    // language of the shown description tab
    descLang() {
      return this.tabdesc || this.lang
    },

    // language of the shown transcription tab
    transLang() {
      return this.tabtrans || this.lang
    },

    protect() {
      return this.item.disk === 'private'
    },

    protectReadonly() {
      return !this.user.can('file:relocate')
    },

    readonly() {
      return !this.user.canLang('file:save', this.item.lang)
    },

    // restricted users can only choose their assigned languages
    langItems() {
      return localeItems(true).filter((entry) => this.user.canLang('file:save', entry.value))
    }
  },

  methods: {
    copyUrl() {
      if (!navigator.clipboard) {
        return this.messages.add(this.$gettext('Error copying to clipboard'), 'error')
      }

      navigator.clipboard
        .writeText(this.fileurl(this.item))
        .then(() => {
          this.messages.add(this.$gettext('Copied to clipboard'), 'success')
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error copying to clipboard'), error)
        })
    },

    describe() {
      const lang = this.descLang

      this.loading.describe = true

      this.$apollo
        .mutate({
          mutation: DESCRIBE_FILE,
          variables: {
            file: this.item.id,
            lang: lang
          },
          context: {
            hasUpload: true
          }
        })
        .then((response) => {
          this.update('description', { ...this.item.description, [lang]: response.data?.describe })
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error describing file'), error)
        })
        .finally(() => {
          this.loading.describe = false
        })
    },

    descriptionUpdated(lang, event) {
      this.update('description', { ...(this.item.description || {}), [lang]: event })
    },

    record() {
      if (this.readonly) return this.messages.denied()

      this.dictation.toggle((text) => {
        this.update('description', { ...this.item.description, [this.descLang]: text })
      })
    },

    setProtect(value) {
      const protect = Boolean(value)
      const disk = protect ? 'private' : 'public'

      if (this.item.disk === disk) {
        return Promise.resolve(true)
      }

      if (!this.user.can('file:relocate')) {
        this.messages.denied()
        return Promise.resolve(false)
      }

      if (this.loading.protect) {
        return Promise.resolve(false)
      }

      this.loading.protect = true

      return relocateFiles(this.$apollo, [this.item.id], disk)
        .then((items) => {
          if (!items[0]) {
            throw new Error('No file relocated')
          }

          // the file is already moved, so the changes don't make the item dirty
          this.$emit('sync', items[0])
          return true
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error saving file'), error, this.item)
          return false
        })
        .finally(() => {
          this.loading.protect = false
        })
    },

    transcribeFile() {
      if (this.readonly) return this.messages.denied()

      if (!this.item.mime?.startsWith('audio/') && !this.item.mime?.startsWith('video/')) {
        return this.messages.add(
          this.$gettext('Transcription is only available for audio and video files'),
          'error'
        )
      }

      const lang = this.transLang
      this.loading.transcribe = true

      import('../ai')
        .then((mod) => mod.transcribe(this.item.path))
        .then((transcription) => {
          const text = transcription.asText()

          // failed transcriptions are empty and must not replace the existing text
          if (text) {
            this.update('transcription', { ...this.item.transcription, [lang]: text })
          }
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error transcribing file'), error)
        })
        .finally(() => {
          this.loading.transcribe = false
        })
    },

    transcriptionUpdated(lang, event) {
      this.update('transcription', { ...this.item.transcription, [lang]: event })
    },

    translateDescription() {
      this.translateMap(this.item.description).then((texts) => {
        if (Object.keys(texts).length) {
          this.update('description', { ...this.item.description, ...texts })
        }
      })
    },

    // resolves to the translations of the first non-empty text keyed by language, or an empty object
    // the text can be split into several parts which are translated separately and joined again
    async translateMap(map, split = (text) => [text], join = (result) => result[0]) {
      if (this.readonly || !this.user.can('text:translate')) {
        this.messages.denied()
        return {}
      }

      if (!map || typeof map !== 'object') {
        this.$log(`FileDetailItem::translateMap(): Invalid map object`, map)
        return {}
      }

      const [lang, text] = Object.entries(map).find(([lang, text]) => text) || []

      if (!text) return {}

      this.loading.translate = true

      try {
        const ai = await import('../ai')
        const texts = {}
        const parts = split(text)

        await Promise.all(
          this.txLocaleItems(lang).map(({ code }) =>
            ai.translate(parts, code)
              .then((result) => {
                // translate() reports its own errors and returns no array then
                if (Array.isArray(result) && result.length === parts.length && result.some(Boolean)) {
                  texts[code] = join(result)
                }
              })
              .catch((error) => {
                this.messages.error(this.$gettext('Error translating texts'), error)
              })
          )
        )

        return texts
      } catch (error) {
        this.messages.error(this.$gettext('Error translating texts'), error)
        return {}
      } finally {
        this.loading.translate = false
      }
    },

    translateVTT(map) {
      if (this.readonly || !this.user.can('text:translate')) return this.messages.denied()

      if (!map || typeof map !== 'object') {
        this.$log(`FileDetailItem::translateVTT(): Invalid map object`, map)
        return
      }

      const [, source] = Object.entries(map).find(([, text]) => text) || []
      const blocks = source ? parseVTT(source) : []
      const cues = blocks.filter((block) => block.keep === undefined && block.text)

      if (!cues.length) return

      // the other languages are translated from the first non-empty one, which translateMap() picks too
      this.translateMap(
        map,
        () => cues.map((block) => block.text),
        (result) => buildVTT(blocks, result)
      ).then((texts) => {
        if (!Object.keys(texts).length) {
          return
        }

        const transcription = { ...this.item.transcription, ...texts }

        this.update('transcription', transcription)
      })
      .catch((error) => {
        this.$log(`FileDetailItem::translateVTT(): Error translating VTT`, error)
      })
    },

    update(what, value) {
      this.$emit('patch', { [what]: value })
    },

    use(items) {
      if (!items?.length) {
        return
      }

      this.$emit('patch', { path: items[0].path, mime: items[0].mime })
      this.$emit('update:file', null)
    }
  }
}
</script>

<template>
  <v-container>
    <v-sheet class="scroll">
      <FileProtect
        :disabled="loading.protect"
        :loading="loading.protect"
        :model-value="protect"
        :readonly="protectReadonly"
        @update:model-value="setProtect($event)"
      />
      <v-row>
        <v-col cols="12" md="6">
          <v-text-field
            :readonly="readonly"
            :modelValue="item.name"
            @update:modelValue="update('name', $event)"
            variant="underlined"
            :label="$gettext('Name') + ' ‒ ' + $gettext('Name to find the file in the media list')"
            counter="255"
            maxlength="255"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="6">
          <v-select
            :items="langItems"
            :readonly="readonly"
            :modelValue="item.lang"
            @update:modelValue="update('lang', $event)"
            variant="underlined"
            :label="$gettext('Language') + ' ‒ ' + $gettext('Language of the file content, choose none if used in all languages')"
          ></v-select>
        </v-col>
      </v-row>
      <v-row>
        <v-col cols="12" class="file-url-col">
          <a :href="fileurl(item)" target="_blank" rel="noopener noreferrer" class="file-url">{{ fileurl(item) }}</a>
          <v-btn
            @click="copyUrl()"
            :icon="mdiContentCopy"
            :title="$gettext('Copy URL')"
            variant="text"
            size="small"
          />
        </v-col>
      </v-row>
      <v-row>
        <v-col v-if="item" cols="12" class="preview">
          <!-- a new path remounts the editor, which resets its cropper and edit history -->
          <FileDetailItemImage
            v-if="item.mime?.startsWith('image/')"
            :key="item.disk + item.path"
            :item="item"
            :readonly="readonly"
            @update:file="$emit('update:file', $event)"
            @use="use($event)"
          />
          <FileDetailItemVideo
            v-else-if="item.mime?.startsWith('video/')"
            :item="item"
            :readonly="readonly"
            @sync="$emit('sync', $event)"
          />
          <FileDetailItemAudio v-else-if="item.mime?.startsWith('audio/')" :item="item" />
          <svg
            v-else
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 16 16"
            fill="currentColor"
          >
            <path
              d="M7.05 11.885c0 1.415-.548 2.206-1.524 2.206C4.548 14.09 4 13.3 4 11.885c0-1.412.548-2.203 1.526-2.203.976 0 1.524.79 1.524 2.203m-1.524-1.612c-.542 0-.832.563-.832 1.612q0 .133.006.252l1.559-1.143c-.126-.474-.375-.72-.733-.72zm-.732 2.508c.126.472.372.718.732.718.54 0 .83-.563.83-1.614q0-.129-.006-.25zm6.061.624V14h-3v-.595h1.181V10.5h-.05l-1.136.747v-.688l1.19-.786h.69v3.633z"
            />
            <path
              d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2M9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"
            />
          </svg>
        </v-col>
      </v-row>
      <v-row>
        <v-col cols="12" class="description">
          <div class="label">
            {{ $gettext('Descriptions') }}
            <div v-if="!readonly" class="actions">
              <v-btn
                v-if="
                  user.can('text:translate') &&
                  Object.values(item.description || {}).find((v) => !!v)
                "
                @click="translateDescription()"
                :title="$gettext('Translate text')"
                :loading="loading.translate"
                :icon="mdiTranslate"
                variant="text"
              />
              <v-btn
                v-if="user.can('file:describe')"
                @click="describe()"
                :title="$gettext('Generate description')"
                :loading="loading.describe"
                :icon="mdiCreation"
                variant="text"
              />
              <v-btn
                v-if="user.can('audio:transcribe')"
                @click="record()"
                :class="{ dictating: dictation.active() }"
                :aria-pressed="String(dictation.active())"
                :icon="dictation.active() ? mdiMicrophoneOutline : mdiMicrophone"
                :title="$gettext('Dictate')"
                :loading="dictation.busy()"
                variant="text"
              />
            </div>
          </div>

          <v-tabs v-model="tabdesc">
            <v-tab v-for="entry in localeItems()" :key="entry.value" :value="entry.value">{{
              entry.title
            }}</v-tab>
          </v-tabs>
          <v-window v-model="tabdesc" :touch="false">
            <v-window-item v-for="entry in localeItems()" :key="entry.value" :value="entry.value">
              <v-textarea
                @update:modelValue="descriptionUpdated(entry.value, $event)"
                :label="$gettext('Description (%{lang})', { lang: entry.value }) + ' ‒ ' + $gettext('Alternative text for screen readers and search engines, also used as caption')"
                :modelValue="item.description?.[entry.value] || ''"
                :readonly="readonly"
                variant="underlined"
                counter="500"
                rows="2"
                auto-grow
                :clearable="!readonly"
              ></v-textarea>
            </v-window-item>
          </v-window>
        </v-col>
      </v-row>
      <v-row v-if="item.mime?.startsWith('audio/') || item.mime?.startsWith('video/')">
        <v-col cols="12" class="transcription">
          <div class="label">
            {{ $gettext('Transcriptions') }}
            <div v-if="!readonly" class="actions">
              <v-btn
                v-if="
                  user.can('text:translate') &&
                  Object.values(item.transcription || {}).find((v) => !!v)
                "
                @click="translateVTT(item.transcription)"
                :title="$gettext('Translate text')"
                :loading="loading.translate"
                :icon="mdiTranslate"
                variant="text"
              />
              <v-btn
                v-if="user.can('audio:transcribe')"
                @click="transcribeFile()"
                :title="$gettext('Transcribe file content')"
                :loading="loading.transcribe"
                :icon="mdiCreation"
                variant="text"
              />
            </div>
          </div>

          <v-tabs v-model="tabtrans">
            <v-tab v-for="entry in localeItems()" :key="entry.value" :value="entry.value">{{
              entry.title
            }}</v-tab>
          </v-tabs>
          <v-window v-model="tabtrans" :touch="false">
            <v-window-item v-for="entry in localeItems()" :key="entry.value" :value="entry.value">
              <v-textarea
                @update:modelValue="transcriptionUpdated(entry.value, $event)"
                :label="$gettext('Transcription (%{lang})', { lang: entry.value }) + ' ‒ ' + $gettext('Spoken text in WebVTT format, shown as subtitles and transcript')"
                :modelValue="item.transcription?.[entry.value] || ''"
                :readonly="readonly"
                variant="underlined"
                rows="10"
                auto-grow
                :clearable="!readonly"
              ></v-textarea>
            </v-window-item>
          </v-window>
        </v-col>
      </v-row>
    </v-sheet>
  </v-container>
</template>

<style scoped>
.v-sheet.scroll {
  height: var(--cms-detail-height);
}

.file-url-col {
  display: flex;
  align-items: center;
  background-color: rgb(var(--v-theme-surface));
}

.file-url {
  flex: 1;
  min-width: 0;
  display: block;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  color: rgb(var(--v-theme-primary));
  padding: 1rem;
}

.preview {
  display: flex;
  justify-content: center;
}

.preview svg {
  width: 5rem;
  height: 5rem;
}

.description .label,
.transcription .label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  text-transform: capitalize;
  font-weight: bold;
  margin-bottom: 4px;
  margin-top: 40px;
}

.description .v-textarea,
.transcription .v-textarea {
  margin-top: 16px;
}
</style>
