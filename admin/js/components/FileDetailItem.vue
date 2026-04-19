/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import FileAiDialog from './FileAiDialog.vue'
import FileDetailItemImage from './FileDetailItemImage.vue'
import FileDetailItemVideo from './FileDetailItemVideo.vue'
import FileDetailItemAudio from './FileDetailItemAudio.vue'
import {
  useAppStore,
  useUserStore,
  useLanguageStore,
  useMessageStore,
  useSideStore
} from '../stores'
import { recording } from '../audio'
import { mdiContentCopy, mdiTranslate, mdiCreation, mdiMicrophoneOutline, mdiMicrophone } from '@mdi/js'
import { toBlob, locales, txlocales, url } from '../utils'
import { transcribe, translate } from '../ai'

export default {
  components: {
    FileAiDialog,
    FileDetailItemImage,
    FileDetailItemVideo,
    FileDetailItemAudio
  },

  props: {
    item: { type: Object, required: true }
  },

  emits: ['update:item', 'update:file', 'error'],

  data() {
    return {
      vedit: false,
      loading: {},
      tabtrans: null,
      tabdesc: null,
      audio: null
    }
  },

  setup() {
    const languages = useLanguageStore()
    const messages = useMessageStore()
    const side = useSideStore()
    const user = useUserStore()
    const app = useAppStore()

    return {
      app,
      user,
      languages,
      messages,
      side,
      mdiTranslate,
      mdiCreation,
      mdiMicrophoneOutline,
      toBlob,
      url,
      locales,
      txlocales,
      transcribe,
      translate,
      mdiContentCopy,
      mdiMicrophone
    }
  },

  computed: {
    desclangs() {
      return this.languages.available
        .concat(Object.keys(this.item.description || {}))
        .filter((v, idx, self) => {
          return self.indexOf(v) === idx
        })
    },

    readonly() {
      return !this.user.can('file:save')
    }
  },

  methods: {
    copyUrl() {
      navigator.clipboard.writeText(this.url(this.item.path)).then(() => {
        this.messages.add(this.$gettext('Copied to clipboard'), 'success')
      })
    },

    describe() {
      const lang = this.desclangs[0] || this.item.lang || 'en'

      this.loading.describe = true

      this.$apollo
        .mutate({
          mutation: gql`
            mutation ($file: String!, $lang: String!) {
              describe(file: $file, lang: $lang)
            }
          `,
          variables: {
            file: this.item.id,
            lang: lang
          },
          context: {
            hasUpload: true
          }
        })
        .then((response) => {
          if (response.errors) {
            throw response.errors
          }

          this.update(
            'description',
            Object.assign(this.item.description || {}, { [lang]: response.data?.describe })
          )
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error describing file') + ':\n' + error, 'error')
          this.$log('FileDetailItem::describe(): Error describing file', error)
        })
        .finally(() => {
          this.loading.describe = false
        })
    },

    descriptionUpdated(lang, event) {
      this.item.description[lang] = event
      this.$emit('update:item', this.item)
    },

    record() {
      if (this.readonly) {
        return this.messages.add(this.$gettext('Permission denied'), 'error')
      }

      if (!this.audio) {
        return (this.audio = recording().start())
      }

      this.audio.then((rec) => {
        this.loading.dictate = true
        this.audio = null

        rec.stop()?.then((buffer) => {
          this.transcribe(buffer)
            .then((transcription) => {
              const lang = this.desclangs[0] || this.item.lang || 'en'
              this.update(
                'description',
                Object.assign(this.item.description || {}, { [lang]: transcription.asText() })
              )
            })
            .finally(() => {
              this.loading.dictate = false
            })
        })
      })
    },

    transcribeFile() {
      if (this.readonly) {
        return this.messages.add(this.$gettext('Permission denied'), 'error')
      }

      if (!this.item.mime?.startsWith('audio/') && !this.item.mime?.startsWith('video/')) {
        return this.messages.add(
          this.$gettext('Transcription is only available for audio and video files'),
          'error'
        )
      }

      this.loading.transcribe = true

      this.transcribe(this.item.path)
        .then((transcription) => {
          const lang = this.desclangs[0] || this.item.lang || 'en'
          this.update(
            'transcription',
            Object.assign(this.item.transcription || {}, { [lang]: transcription.asText() })
          )
        })
        .finally(() => {
          this.loading.transcribe = false
        })
    },

    transcriptionUpdated(lang, event) {
      this.item.transcription[lang] = event
      this.$emit('update:item', this.item)
    },

    translateText(map) {
      if (!this.user.can('text:translate')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      if (this.readonly) {
        return this.messages.add(this.$gettext('Permission denied'), 'error')
      }

      if (!map || typeof map !== 'object') {
        this.$log(`FileDetailItem::translateText(): Invalid map object`, map)
        return
      }

      const promises = []
      const [lang, text] = Object.entries(map || {}).find(([lang, text]) => {
        return text ? true : false
      })

      this.loading.translate = true

      this.txlocales(lang)
        .map((lang) => lang.code)
        .forEach((lang) => {
          promises.push(
            this.translate(text, lang)
              .then((result) => {
                if (result[0]) {
                  map[lang] = result[0]
                }
              })
              .catch((error) => {
                this.$log(`FileDetailItem::translateText(): Error translating text`, error)
              })
          )
        })

      return Promise.all(promises).then(() => {
        this.$emit('update:item', this.item)
        this.loading.translate = false
        return map
      })
    },

    translateVTT(map) {
      if (this.readonly || !this.user.can('text:translate')) {
        return this.messages.add(this.$gettext('Permission denied'), 'error')
      }

      if (!map || typeof map !== 'object') {
        this.$log(`FileDetailItem::translateVTT(): Invalid map object`, map)
        return
      }

      const regex = /^\d{2}:\d{2}:\d{2}\.\d{3} --\> \d{2}:\d{2}:\d{2}\.\d{3}(?: .*)?$/
      const texts = { ...map }

      for (const [lang, text] of Object.entries(texts)) {
        if (text) {
          texts[lang] = text
            .split('\n')
            .map((line) =>
              line.startsWith('WEBVTT') || regex.test(line) ? `<x>${line}</x>` : line
            )
            .filter((line) => line.trim() !== '')
            .join('')
        }
      }

      this.translateText(texts)
        .then((texts) => {
          for (const [lang, text] of Object.entries(texts)) {
            if (texts[lang]) {
              map[lang] = texts[lang]
                .replaceAll(/\<x\>/g, '\n\n')
                .replaceAll(/\<\/x\>/g, '\n')
                .trim()
            }
          }

          this.$emit('update:item', this.item)
        })
        .catch((error) => {
          this.$log(`FileDetailItem::translateVTT(): Error translating VTT`, error)
        })
    },

    update(what, value) {
      this.item[what] = value
      this.$emit('update:item', this.item)
    },

    use(items) {
      if (!items?.length) {
        return
      }

      this.vedit = false
      this.item.path = items[0].path
      this.item.mime = items[0].mime

      this.$emit('update:file', null)
    }
  }
}
</script>

<template>
  <v-container>
    <v-sheet class="scroll">
      <v-row>
        <v-col cols="12" md="6">
          <v-text-field
            ref="name"
            :readonly="readonly"
            :modelValue="item.name"
            @update:modelValue="update('name', $event)"
            variant="underlined"
            :label="$gettext('Name')"
            counter="255"
            maxlength="255"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="6">
          <v-select
            ref="lang"
            :items="locales(true)"
            :readonly="readonly"
            :modelValue="item.lang"
            @update:modelValue="update('lang', $event)"
            variant="underlined"
            :label="$gettext('Language')"
          ></v-select>
        </v-col>
      </v-row>
      <v-row>
        <v-col cols="12" class="file-url-col">
          <a :href="url(item.path)" target="_blank" class="file-url">{{ url(item.path) }}</a>
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
          <FileDetailItemImage
            v-if="item.mime?.startsWith('image/')"
            :item="item"
            :readonly="readonly"
            @update:file="$emit('update:file', $event)"
            @use="use($event)"
          />
          <FileDetailItemVideo
            v-else-if="item.mime?.startsWith('video/')"
            :item="item"
            :readonly="readonly"
            @update:item="$emit('update:item', $event)"
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
                @click="translateText(item.description)"
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
                :class="{ dictating: audio }"
                :icon="audio ? mdiMicrophoneOutline : mdiMicrophone"
                :title="$gettext('Dictate')"
                :loading="loading.dictate"
                variant="text"
              />
            </div>
          </div>

          <v-tabs v-model="tabdesc">
            <v-tab v-for="entry in locales()" :key="entry.value" :value="entry.value">{{
              entry.title
            }}</v-tab>
          </v-tabs>
          <v-window v-model="tabdesc" :touch="false">
            <v-window-item v-for="entry in locales()" :key="entry.value" :value="entry.value">
              <v-textarea
                ref="description"
                @update:modelValue="descriptionUpdated(entry.value, $event)"
                :label="$gettext('Description (%{lang})', { lang: entry.value })"
                :modelValue="item.description?.[entry.value] || ''"
                :readonly="readonly"
                variant="underlined"
                counter="500"
                rows="2"
                auto-grow
                clearable
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
            <v-tab v-for="entry in locales()" :key="entry.value" :value="entry.value">{{
              entry.title
            }}</v-tab>
          </v-tabs>
          <v-window v-model="tabtrans" :touch="false">
            <v-window-item v-for="entry in locales()" :key="entry.value" :value="entry.value">
              <v-textarea
                ref="transcription"
                @update:modelValue="transcriptionUpdated(entry.value, $event)"
                :label="$gettext('Transcription (%{lang})', { lang: entry.value })"
                :modelValue="item.transcription?.[entry.value] || ''"
                :readonly="readonly"
                variant="underlined"
                rows="10"
                auto-grow
                clearable
              ></v-textarea>
            </v-window-item>
          </v-window>
        </v-col>
      </v-row>
    </v-sheet>
  </v-container>

  <Teleport to="body">
    <FileAiDialog v-model="vedit" :files="[item]" @add="use($event)" />
  </Teleport>
</template>

<style scoped>
.v-sheet.scroll {
  max-height: calc(100vh - 96px);
}

.file-url-col {
  display: flex;
  align-items: center;
  background-color: rgb(var(--v-theme-background));
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
