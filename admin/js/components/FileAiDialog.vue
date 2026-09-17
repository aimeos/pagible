/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import { markRaw } from 'vue'
import CmsDialog from './Dialog.vue'
import FileListItems from './FileListItems.vue'
import { createFile } from '../files'
import { useAppStore, useUserStore, useMessageStore } from '../stores'
import { fileurl, IMAGE_MIME_FILTER, toBlob, url } from '../utils'
import { mdiMicrophoneOutline, mdiMicrophone, mdiDelete } from '@mdi/js'

const IMAGINE = gql`
  mutation ($prompt: String!, $context: String, $files: [String!]) {
    imagine(prompt: $prompt, context: $context, files: $files)
  }
`

export default {
  components: {
    CmsDialog,
    FileListItems
  },

  props: {
    modelValue: { type: Boolean, required: true },
    context: { type: [Object, null], default: null },
    disk: { type: String, default: 'public' },
    files: { type: Array, default: () => [] }
  },

  emits: ['update:modelValue', 'add'],

  setup() {
    const messages = useMessageStore()
    const user = useUserStore()
    const app = useAppStore()

    return {
      app,
      user,
      messages,
      fileurl,
      toBlob,
      url,
      IMAGE_MIME_FILTER,
      mdiMicrophoneOutline,
      mdiMicrophone,
      mdiDelete
    }
  },

  data() {
    return {
      audio: null,
      chat: '',
      items: [],
      errors: [],
      used: [],
      loading: false,
      dictating: false
    }
  },

  beforeUpdate() {
    this.chat = [this.context?.title, this.context?.text, this.context?.description]
      .filter(Boolean)
      .join('\n')
    this.used = this.files || []
  },

  watch: {
    modelValue(val) {
      if (!val) {
        this.items.forEach((item) => {
          if (item.path.startsWith('blob:')) {
            URL.revokeObjectURL(item.path)
          }
        })

        this.items = []
        this.used = []
        this.chat = ''
      }
    }
  },

  beforeUnmount() {
    if (this.audio) {
      this.audio.then((rec) => rec?.stop?.()).catch(() => {})
      this.audio = null
    }

    this.items.forEach((item) => {
      if (item.path.startsWith('blob:')) {
        URL.revokeObjectURL(item.path)
      }
    })

    this.items = []
    this.used = []
    this.errors = []
    this.chat = ''
  },

  methods: {
    add(item) {
      if (!item.path.startsWith('blob:')) {
        this.$emit('add', [item])
        return
      }

      this.loading = true

      const filename = 'ai-image_' + new Date().toISOString().replace(/[^0-9]/g, '') + '.png'

      createFile(this.$apollo, {
        disk: this.disk,
        input: { name: item.name },
        file: new File([item.blob], filename, { type: item.mime })
      })
        .then((data) => {
          Object.assign(item, data)

          this.$refs.filelist.invalidate()
          this.$emit('add', [item])
        })
        .catch((error) => {
          this.messages.add(
            this.$gettext(`Error adding file %{path}`, { path: item?.path }) + ':\n' + error,
            'error'
          )
          this.$log(`FileAiDialog::add(): Error adding file`, error)
        })
        .finally(() => {
          this.loading = false
        })
    },

    create() {
      if (!this.user.can('image:imagine')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      if (!this.chat?.trim() || this.loading) {
        return
      }

      this.loading = true
      this.original = this.chat

      this.$apollo
        .mutate({
          mutation: IMAGINE,
          variables: {
            prompt: this.chat,
            context: this.context ? 'Context in JSON format:\n' + JSON.stringify(this.context) : '',
            files: this.used.map((item) => item.id)
          }
        })
        .then((response) => {
          if (response.errors) {
            throw response.errors
          }

          if (response.data.imagine) {
            const blob = this.toBlob(response.data.imagine)

            this.items.unshift({
              path: URL.createObjectURL(blob),
              blob: markRaw(blob),
              name: this.chat.slice(
                0,
                this.chat.length > 250 ? this.chat.lastIndexOf(' ', 250) : 250
              ),
              mime: 'image/png'
            })
          }
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error creating file') + ':\n' + error, 'error')
          this.$log(`FileAiDialog::create(): Error creating file`, error)
        })
        .finally(() => {
          this.loading = false
        })
    },

    record() {
      if (!this.audio) {
        return (this.audio = markRaw(import('../audio').then((mod) => mod.recording().start())))
      }

      this.audio.then((rec) => {
        this.dictating = true
        this.audio = null

        rec.stop()?.then((buffer) => {
          import('../ai')
            .then((mod) => mod.transcribe(buffer))
            .then((transcription) => {
              this.chat = transcription.asText()
            })
            .finally(() => {
              this.dictating = false
            })
        })
      })
    },

    remove(idx) {
      const item = this.items[idx]

      if (item?.path?.startsWith('blob:')) {
        URL.revokeObjectURL(item.path)
      }

      this.items.splice(idx, 1)
    },

    removeUsed(idx) {
      this.used.splice(idx, 1)
    },

    use(item) {
      if (!this.used.find((entry) => entry.path === item.path)) {
        this.used.push(item)
      }
    }
  }
}
</script>

<template>
  <CmsDialog
    :model-value="modelValue"
    :title="$gettext('Create image')"
    :card-loading="loading ? 'primary' : false"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="1200"
    scrollable
  >
    <template #toolbar-actions>
      <v-btn
        v-if="user.can('audio:transcribe')"
        @click="record()"
        :class="{ dictating: audio }"
        :icon="audio ? mdiMicrophoneOutline : mdiMicrophone"
        :aria-label="$gettext('Dictate')"
        :loading="dictating"
      />
    </template>

    <v-textarea
      v-model="chat"
      :label="$gettext('Describe the image content')"
      variant="underlined"
      autofocus
      clearable
    ></v-textarea>

    <v-btn :loading="loading" :disabled="!chat" @click="create()" variant="outlined" class="create">
      {{ $gettext('New image') }}
    </v-btn>

    <div v-if="items.length">
      <h3 class="section-title text-button">{{ $gettext('Current images') }}</h3>
      <v-list class="items grid">
        <v-list-item v-for="(item, idx) in items" :key="idx">
          <v-btn
            @click="remove(idx)"
            :title="$gettext('Remove')"
            class="btn-overlay"
            :icon="mdiDelete"
          />

          <div
            class="item-preview"
            @click="add(item)"
            @keydown.enter="add(item)"
            @keydown.space.prevent="add(item)"
            role="button"
            tabindex="0"
          >
            <img :src="fileurl(item)" :alt="item.name" />
          </div>
        </v-list-item>
      </v-list>
    </div>

    <div v-if="used.length">
      <h3 class="section-title text-button">{{ $gettext('Images used') }}</h3>
      <v-list class="items grid">
        <v-list-item v-for="(item, idx) in used" :key="idx">
          <v-btn
            :icon="mdiDelete"
            @click="removeUsed(idx)"
            class="btn-overlay"
            :title="$gettext('Remove')"
          ></v-btn>

          <div class="item-preview">
            <img :src="fileurl(item)" :alt="item.name" />
          </div>
        </v-list-item>
      </v-list>
    </div>

    <h3 class="section-title text-button">{{ $gettext('Select images') }}</h3>
    <FileListItems ref="filelist" :filter="IMAGE_MIME_FILTER" @select="use($event)" />
  </CmsDialog>
</template>

<style scoped>
.section-title {
  align-items: center;
  background-color: rgb(var(--v-theme-background));
  display: flex;
  justify-content: center;
  margin: 40px 0 0;
  min-height: 48px;
}

.v-btn.create {
  display: block;
  margin: auto;
}

.items.grid {
  grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
  display: grid;
  gap: 16px;
}

.items.grid .v-list-item {
  grid-template-rows: max-content;
  border: 1px solid rgb(var(--v-theme-primary));
}

.items.grid .item-preview {
  justify-content: center;
  display: flex;
  height: 180px;
}

.items.grid .item-preview img {
  display: block;
}
</style>
