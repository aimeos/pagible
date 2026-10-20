/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import { markRaw } from 'vue'
import CmsDialog from './Dialog.vue'
import FileListItems from './FileListItems.vue'
import { createFile, IMAGE_MIME_FILTER, revokeBlob, toBlob } from '../files'
import { useUserStore, useMessageStore } from '../stores'
import { useDictation } from '../dictation'
import { fileurl } from '../urls'
import { mdiMicrophoneOutline, mdiMicrophone, mdiDelete } from '@mdi/js'

// maximum length of the file name generated from the prompt
const MAX_NAME_LENGTH = 250

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
    disk: { type: String, default: 'public' }
  },

  emits: ['update:modelValue', 'add'],

  setup() {
    const messages = useMessageStore()
    const user = useUserStore()

    return {
      user,
      messages,
      fileurl,
      IMAGE_MIME_FILTER,
      mdiMicrophoneOutline,
      mdiMicrophone,
      mdiDelete
    }
  },

  data() {
    return {
      chat: '',
      dictation: useDictation(),
      items: [],
      used: [],
      loading: false,
      uploading: false,
      // bumped when closing so late image responses are dropped
      seq: 0
    }
  },

  watch: {
    modelValue: {
      immediate: true,
      handler(val) {
        // prefill only when opened, re-renders (e.g. while generating) must keep the user's input
        if (val) {
          this.chat = [this.context?.title, this.context?.text, this.context?.description]
            .filter(Boolean)
            .join('\n')
          this.used = []
          return
        }

        // a running recording would continue unseen and fill the prompt later
        this.dictation.stop()
        this.seq++
        this.loading = false
        this.items.forEach(revokeBlob)
        this.items = []
        this.used = []
        this.chat = ''
      }
    }
  },

  beforeUnmount() {
    this.dictation.stop()
    this.seq++

    this.items.forEach(revokeBlob)
    this.items = []
    this.used = []
    this.chat = ''
  },

  methods: {
    add(item) {
      if (!item.path.startsWith('blob:')) {
        this.$emit('add', [item])
        return
      }

      // one upload at a time, otherwise double clicks create duplicate files
      if (this.uploading) return

      this.uploading = true

      const filename = 'ai-image_' + new Date().toISOString().replace(/[^0-9]/g, '') + '.png'

      createFile(this.$apollo, {
        disk: this.disk,
        input: { name: item.name },
        file: new File([item.blob], filename, { type: item.mime })
      })
        .then((data) => {
          // the server path replaces the object URL which can't be revoked afterwards
          revokeBlob(item)
          Object.assign(item, data)

          // the list isn't rendered any more if the dialog has been closed during the upload
          this.$refs.filelist?.invalidate()
          this.$emit('add', [item])
        })
        .catch((error) => {
          this.messages.error(this.$gettext(`Error adding file %{path}`, { path: item?.path }), error)
        })
        .finally(() => {
          this.uploading = false
        })
    },

    create() {
      if (!this.user.can('image:imagine')) return this.messages.denied()

      if (!this.chat?.trim() || this.loading) {
        return
      }

      const seq = ++this.seq
      const prompt = this.chat
      this.loading = true

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
          // dialog closed in the meantime
          if (seq !== this.seq || !response.data.imagine) {
            return
          }

          const blob = toBlob(response.data.imagine)

          this.items.unshift({
            path: URL.createObjectURL(blob),
            blob: markRaw(blob),
            name: this.filename(prompt),
            mime: 'image/png'
          })
        })
        .catch((error) => {
          if (seq === this.seq) {
            this.messages.error(this.$gettext('Error creating file'), error)
          }
        })
        .finally(() => {
          if (seq === this.seq) {
            this.loading = false
          }
        })
    },

    // shortens the prompt at a word boundary to the maximum file name length
    filename(prompt) {
      if (prompt.length <= MAX_NAME_LENGTH) {
        return prompt
      }

      const idx = prompt.lastIndexOf(' ', MAX_NAME_LENGTH)
      return prompt.slice(0, idx > 0 ? idx : MAX_NAME_LENGTH)
    },

    record() {
      this.dictation.toggle((text) => {
        this.chat = (this.chat ? this.chat + ' ' : '') + text
      })
    },

    remove(idx) {
      revokeBlob(this.items[idx])
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
    :card-loading="loading || uploading ? 'primary' : false"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="1200"
  >
    <template #toolbar-actions>
      <v-btn
        v-if="user.can('audio:transcribe')"
        @click="record()"
        :class="{ dictating: dictation.active() }"
        :icon="dictation.active() ? mdiMicrophoneOutline : mdiMicrophone"
        :aria-label="$gettext('Dictate')"
        :loading="dictation.busy()"
      />
    </template>

    <v-textarea
      v-model="chat"
      :label="$gettext('Describe the image content') + ' ‒ ' + $gettext('Describe the subject, style and colors of the image to generate')"
      variant="underlined"
      autofocus
      clearable
    ></v-textarea>

    <v-btn :loading="loading" :disabled="!chat" @click="create()" variant="tonal" color="primary" class="create" data-confirm>
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
    <FileListItems ref="filelist" :filter="IMAGE_MIME_FILTER" @select="use($event)" embed />
  </CmsDialog>
</template>

<style scoped>
.section-title {
  align-items: center;
  background-color: rgb(var(--v-theme-background));
  color: rgb(var(--v-theme-on-background));
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
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
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
