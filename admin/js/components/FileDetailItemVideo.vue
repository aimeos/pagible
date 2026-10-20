/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import { invalidateList } from '../graphql'
import { canvasBlob, previewUrl } from '../files'
import { useConfirmStore, useMessageStore } from '../stores'
import { fileurl } from '../urls'
import { safeParse } from '../json'
import { mdiTooltipImage, mdiImagePlus } from '@mdi/js'

const SAVE_FILE_PREVIEW = gql`
  mutation ($id: ID!, $preview: Upload) {
    saveFile(id: $id, input: {}, preview: $preview) {
      id
      latest {
        id
        published
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
        id
        published
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

  // the cover is saved immediately, so the changes don't make the item dirty
  emits: ['sync'],

  data() {
    return {
      loading: {}
    }
  },

  setup() {
    const confirm = useConfirmStore()
    const messages = useMessageStore()

    return { confirm, messages, fileurl, previewUrl, mdiTooltipImage, mdiImagePlus }
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
      // another cover is still saved, e.g. after a double click
      if (this.loading.cover) return

      const video = this.$refs.video

      if (!video) {
        return this.messages.add(this.$gettext('No video element found'), 'error')
      }

      // no frame available before the video metadata is loaded
      if (video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA || !video.videoWidth || !video.videoHeight) {
        return this.messages.add(this.$gettext('Video not loaded yet'), 'info')
      }

      const filename = this.item.path
        .replace(/\.[A-Za-z0-9]+$/, '.png')
        .split('/')
        .pop()
      const canvas = document.createElement('canvas')
      const context = canvas.getContext('2d')

      const msg = this.$gettext('Error saving video cover')

      canvas.width = video.videoWidth
      canvas.height = video.videoHeight

      // blocks further clicks while the frame is converted, before cover() saves it
      this.loading.cover = true

      try {
        context.drawImage(video, 0, 0, video.videoWidth, video.videoHeight)

        canvasBlob(canvas, 'image/png', 1).then((blob) => {
          this.cover(new File([blob], filename, { type: 'image/png' }), msg)
        }).catch((error) => {
          // the canvas is tainted by a cross-origin video or too large to encode
          this.loading.cover = false
          this.messages.error(msg, error)
        }).finally(() => {
          canvas.width = 0
          canvas.height = 0
        })
      } catch (error) {
        canvas.width = 0
        canvas.height = 0
        this.loading.cover = false
        this.messages.error(msg, error)
      }
    },

    // saves the uploaded cover image or removes the cover if "preview" is false
    cover(preview, msg) {
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
            // the new version is the base of the next save, which would otherwise report a conflict
            this.$emit('sync', {
              previews: safeParse(latest.data)?.previews || {},
              latestId: latest.id,
              published: latest.published,
              updated_at: latest.created_at
            })
          }
        })
        .catch((error) => this.messages.error(msg, error))
        .finally(() => {
          this.loading.cover = false
        })
    },

    async removeCover() {
      if (this.readonly) return this.messages.denied()
      if (this.loading.cover) return

      if (!(await this.confirm.ask(this.$gettext('Remove cover image'), this.$gettext('Remove the cover image of this video?')))) {
        return
      }

      // another cover change may have started while the dialog was open
      if (this.loading.cover) return

      // the previews are synced from the response after the cover is removed
      return this.cover(false, this.$gettext('Error removing video cover'))
    },

    uploadCover(ev) {
      if (this.readonly) return this.messages.denied()

      const file = ev.target.files[0]

      // allows selecting the same file again, e.g. after a failed upload
      ev.target.value = ''

      if (this.loading.cover) return

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
      <button
        v-if="Object.values(item.previews || {}).length"
        :aria-label="$gettext('Remove cover image')"
        :title="$gettext('Remove cover image')"
        :disabled="loading.cover"
        class="btn-cover-remove"
        type="button"
        @click="removeCover()"
      >
        <img class="video-preview" :src="previewUrl(item)" :alt="item.name" />
      </button>
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
          <input ref="coverInput" type="file" accept="image/*" class="cover-input" @change="uploadCover($event)" />
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

.btn-cover-remove {
  display: inline-flex;
  padding: 0;
  border: 0;
  border-radius: 8px;
  background: none;
  cursor: pointer;
}

.btn-cover-remove:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

img.video-preview {
  width: 100px;
  border-radius: 8px;
}

.cover-input {
  display: none;
}
</style>
