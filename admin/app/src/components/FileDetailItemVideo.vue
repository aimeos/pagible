/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import { useUserStore, useMessageStore } from '../stores'
import { url } from '../utils'
import { mdiTooltipImage, mdiImagePlus } from '@mdi/js'

export default {
  props: {
    item: { type: Object, required: true },
    readonly: { type: Boolean, default: false }
  },

  emits: ['update:item'],


  data() {
    return {
      loading: {}
    }
  },

  setup() {
    const messages = useMessageStore()
    const user = useUserStore()

    return { user, messages, url, mdiTooltipImage, mdiImagePlus }
  },

  methods: {
    addCover() {
      if (this.readonly) {
        return this.messages.add(this.$gettext('Permission denied'), 'error')
      }

      const self = this
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
        function (blob) {
          const file = new File([blob], filename, { type: 'image/png' })

          self.loading.cover = true

          self.$apollo
            .mutate({
              mutation: gql`
                mutation ($id: ID!, $preview: Upload) {
                  saveFile(id: $id, input: {}, preview: $preview) {
                    id
                    latest {
                      data
                      created_at
                    }
                  }
                }
              `,
              variables: {
                id: self.item.id,
                preview: file
              },
              context: {
                hasUpload: true
              }
            })
            .then((response) => {
              if (response.errors) {
                throw response.errors
              }

              const latest = response.data?.saveFile?.latest

              if (latest) {
                self.item.previews = JSON.parse(latest.data || '{}')?.previews || {}
                self.item.updated_at = latest.created_at
              }
            })
            .catch((error) => {
              self.messages.add(self.$gettext('Error saving video cover') + ':\n' + error, 'error')
              self.$log(`FileDetailItemVideo::addCover(): Error saving video cover`, error)
            })
            .finally(() => {
              self.loading.cover = false
            })
        },
        'image/png',
        1
      )
    },

    removeCover() {
      if (this.readonly) {
        return this.messages.add(this.$gettext('Permission denied'), 'error')
      }

      this.loading.cover = true
      this.item.previews = {}

      this.$apollo
        .mutate({
          mutation: gql`
            mutation ($id: ID!, $preview: Boolean) {
              saveFile(id: $id, input: {}, preview: $preview) {
                id
                latest {
                  data
                  created_at
                }
              }
            }
          `,
          variables: {
            id: this.item.id,
            preview: false
          }
        })
        .then((response) => {
          if (response.errors) {
            throw response.errors
          }

          const latest = response.data?.saveFile?.latest

          if (latest) {
            this.item.previews = JSON.parse(latest.data || '{}')?.previews || {}
            this.item.updated_at = latest.created_at
          }
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error removing video cover') + ':\n' + error, 'error')
          this.$log(`FileDetailItemVideo::removeCover(): Error removing video cover`, error)
        })
        .finally(() => {
          this.loading.cover = false
        })
    },

    uploadCover(ev) {
      if (this.readonly) {
        return this.messages.add(this.$gettext('Permission denied'), 'error')
      }

      const file = ev.target.files[0]

      if (!file) {
        return this.messages.add(this.$gettext('No file selected'), 'error')
      }

      this.loading.cover = true

      this.$apollo
        .mutate({
          mutation: gql`
            mutation ($id: ID!, $preview: Upload) {
              saveFile(id: $id, input: {}, preview: $preview) {
                id
                latest {
                  data
                  created_at
                }
              }
            }
          `,
          variables: {
            id: this.item.id,
            preview: file
          },
          context: {
            hasUpload: true
          }
        })
        .then((response) => {
          if (response.errors) {
            throw response.errors
          }

          const latest = response.data?.saveFile?.latest

          if (latest) {
            this.item.previews = JSON.parse(latest.data || '{}')?.previews || {}
            this.item.updated_at = latest.created_at
          }
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error uploading video cover') + ':\n' + error, 'error')
          this.$log(`FileDetailItemVideo::uploadCover(): Error uploading video cover`, error)
        })
        .finally(() => {
          this.loading.cover = false
        })
    }
  }
}
</script>

<template>
  <div class="editor-container">
    <video
      ref="video"
      :src="url(item.path)"
      crossorigin="anonymous"
      class="element"
      controls
    ></video>

    <div v-if="!readonly" class="toolbar">
      <img
        v-if="Object.values(item.previews).length"
        class="video-preview"
        :src="url(Object.values(item.previews).shift())"
        :alt="item.name"
        @click="removeCover()"
      />
      <div v-else>
        <v-btn
          :icon="mdiTooltipImage"
          :loading="loading.cover"
          :title="$gettext('Use as cover image')"
          @click="addCover()"
        />
        <v-btn
          icon
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

.toolbar {
  gap: 8px;
  width: 100%;
  display: flex;
  padding: 10px;
  flex-wrap: wrap;
  justify-content: center;
  background-color: rgb(var(--v-theme-background));
}

@media (max-width: 768px) {
  .toolbar {
    width: auto;
  }
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
