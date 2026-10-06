/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import { invalidateList } from '../graphql'
import { previewUrl } from '../files'
import { useMessageStore } from '../stores'
import { fileurl, safeParse } from '../utils'
import { mdiTooltipImage, mdiImagePlus } from '@mdi/js'

const SAVE_FILE_PREVIEW = gql`
  mutation ($id: ID!, $preview: Upload) {
    saveFile(id: $id, input: {}, preview: $preview) {
      id
      latest {
        data
        created_at
      }
    }
  }
`

const REMOVE_FILE_PREVIEW = gql`
  mutation ($id: ID!, $preview: Boolean) {
    saveFile(id: $id, input: {}, preview: $preview) {
      id
      latest {
        data
        created_at
      }
    }
  }
`

export default {
  props: {
    item: { type: Object, required: true },
    readonly: { type: Boolean, default: false }
  },

  data() {
    return {
      loading: {}
    }
  },

  setup() {
    const messages = useMessageStore()

    return { messages, fileurl, previewUrl, mdiTooltipImage, mdiImagePlus }
  },

  beforeUnmount() {
    const video = this.$refs.video
    if (video) {
      video.pause()
      video.removeAttribute('src')
      video.load()
    }
  },

  methods: {
    addCover() {
      if (this.readonly) return this.messages.denied()

      const video = this.$refs.video

      if (!video) {
        return this.messages.add(this.$gettext('No video element found'), 'error')
      }

      const filename = this.item.path
        .replace(/\.[A-Za-z0-9]+$/, '.png')
        .split('/')
        .pop()
      const canvas = document.createElement('canvas')
      const context = canvas.getContext('2d')

      canvas.width = video.videoWidth
      canvas.height = video.videoHeight
      context.drawImage(video, 0, 0, video.videoWidth, video.videoHeight)

      canvas.toBlob(
        (blob) => {
          canvas.width = 0
          canvas.height = 0

          this.cover(
            new File([blob], filename, { type: 'image/png' }),
            this.$gettext('Error saving video cover')
          )
        },
        'image/png',
        1
      )
    },

    // saves the uploaded cover image or removes the cover if "preview" is false
    cover(preview, error) {
      this.loading.cover = true

      return this.$apollo
        .mutate({
          mutation: preview === false ? REMOVE_FILE_PREVIEW : SAVE_FILE_PREVIEW,
          variables: { id: this.item.id, preview },
          context: { hasUpload: preview !== false }
        })
        .then((response) => {
          invalidateList('files')
          const latest = response.data?.saveFile?.latest

          if (latest) {
            this.item.previews = safeParse(latest.data)?.previews || {}
            this.item.updated_at = latest.created_at
          }
        })
        .catch((err) => this.messages.error(error, err))
        .finally(() => {
          this.loading.cover = false
        })
    },

    removeCover() {
      if (this.readonly) return this.messages.denied()

      this.item.previews = {}
      this.cover(false, this.$gettext('Error removing video cover'))
    },

    uploadCover(ev) {
      if (this.readonly) return this.messages.denied()

      const file = ev.target.files[0]

      if (!file) {
        return this.messages.add(this.$gettext('No file selected'), 'error')
      }

      this.cover(file, this.$gettext('Error uploading video cover'))
    }
  }
}
</script>

<template>
  <div class="editor-container">
    <video
      ref="video"
      :src="fileurl(item)"
      crossorigin="anonymous"
      class="element"
      controls
    ></video>

    <div v-if="!readonly" class="toolbar">
      <img
        v-if="Object.values(item.previews).length"
        class="video-preview"
        :src="previewUrl(item)"
        :alt="item.name"
        @click="removeCover()"
      />
      <div v-else>
        <v-btn
          :icon="mdiTooltipImage"
          :loading="loading.cover"
          :title="$gettext('Use as cover image')"
          class="btn-cover-use"
          @click="addCover()"
        />
        <v-btn
          icon
          class="btn-cover-upload"
          :loading="loading.cover"
          :title="$gettext('Upload cover image')"
          @click="$refs.coverInput.click()"
        >
          <v-icon :icon="mdiImagePlus" />
          <input ref="coverInput" type="file" class="cover-input" @change="uploadCover($event)" />
        </v-btn>
      </div>
    </div>
  </div>
</template>

<style scoped>
.editor-container {
  width: 100%;
}

.element {
  max-width: 100%;
  max-height: 100%;
}

img.video-preview {
  width: 100px;
  cursor: pointer;
  border-radius: 8px;
}

.cover-input {
  display: none;
}
</style>
