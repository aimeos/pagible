/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import Cropper from 'cropperjs'
import 'cropperjs/dist/cropper.css'
import { useUserStore, useMessageStore } from '../stores'
import { toBlob, url } from '../utils'
import {
  mdiClose,
  mdiCropFree,
  mdiCrop,
  mdiEraser,
  mdiImageEdit,
  mdiImageFilterBlackWhite,
  mdiArrowExpandAll,
  mdiMagnifyExpand,
  mdiRotateLeft,
  mdiRotateRight,
  mdiFlipHorizontal,
  mdiFlipVertical,
  mdiDownload,
  mdiHistory
} from '@mdi/js'

export default {
  props: {
    item: { type: Object, required: true },
    readonly: { type: Boolean, default: false }
  },

  emits: ['update:file', 'use'],

  data() {
    return {
      destroyed: false,
      selected: false,
      loading: {},
      edittext: null,
      cropLabel: null,
      cropper: null,
      images: [],
      menu: {},
      width: 0,
      height: 0,
      extend: {
        top: 0,
        right: 0,
        bottom: 0,
        left: 0
      }
    }
  },

  setup() {
    const messages = useMessageStore()
    const user = useUserStore()

    return {
      user,
      messages,
      toBlob,
      url,
      mdiClose,
      mdiCropFree,
      mdiCrop,
      mdiEraser,
      mdiImageEdit,
      mdiImageFilterBlackWhite,
      mdiArrowExpandAll,
      mdiMagnifyExpand,
      mdiRotateLeft,
      mdiRotateRight,
      mdiFlipHorizontal,
      mdiFlipVertical,
      mdiDownload,
      mdiHistory
    }
  },

  mounted() {
    this.cropper = this.init()
  },

  beforeUnmount() {
    this.destroyed = true

    if (this.cropper) {
      this.cropper.destroy()
    }

    this.images.forEach((img) => {
      URL.revokeObjectURL(img.url)
    })
  },

  computed: {
    ratio() {
      if (!this.cropper) {
        return NaN
      }

      const imageData = this.cropper.getImageData()
      return imageData.naturalWidth / imageData.naturalHeight
    }
  },

  methods: {
    aspect(ratio) {
      this.cropper.setAspectRatio(ratio)
      this.cropper.setDragMode('crop')

      this.$nextTick(() => {
        if (this.destroyed) return

        const cropBox = this.cropper.cropper.querySelector('.cropper-crop-box')

        if (cropBox && !this.cropLabel) {
          const label = document.createElement('div')
          label.className = 'crop-label'
          cropBox.appendChild(label)
          this.cropLabel = label
        }
      })
    },

    clear() {
      if (this.destroyed) return

      this.cropper.setDragMode('none')
      this.cropper.clear()
      this.selected = false
      this.cropLabel = null
    },

    crop() {
      this.updateFile()
      this.clear()
    },

    download() {
      this.cropper.getCroppedCanvas().toBlob((blob) => {
        const url = URL.createObjectURL(blob)
        const link = document.createElement('a')

        link.href = url
        link.download = this.item.name || 'download'
        link.click()

        URL.revokeObjectURL(url)
      })
    },

    erase() {
      this.image().then((blob) => {
        this.mask().toBlob((mask) => {
          this.mutate(
            'image:erase',
            gql`
              mutation ($file: Upload!, $mask: Upload!) {
                erase(file: $file, mask: $mask)
              }
            `,
            {
              file: new File([blob], 'image', { type: this.item.mime }),
              mask: new File([mask], 'mask', { type: 'image/png' })
            }
          )
            .then((response) => this.replace(this.toBlob(response.data?.erase)))
            .catch((error) => {
              this.messages.add(this.$gettext('Error erasing image part') + ':\n' + error, 'error')
              this.$log('FileDetailItemImage::erase(): Error erasing image part', error)
            })
            .finally(() => this.clear())
        })
      })
    },

    flipX() {
      this.cropper.scaleX(-1)
      this.updateFile()
    },

    flipY() {
      this.cropper.scaleY(-1)
      this.updateFile()
    },

    image() {
      if (this.images[0]?.blob) {
        return Promise.resolve(this.images[0]?.blob)
      }

      return fetch(this.url(this.item.path, true), {credentials: 'include'}).then((response) => {
        if (!response.ok) {
          throw new Error('Network error: ' + response.statusText)
        }
        return response.blob()
      })
    },

    init() {
      if (this.readonly || this.destroyed) {
        return null
      }

      if (this.cropper) {
        this.cropper.destroy()
      }

      const self = this

      return new Cropper(this.$refs.image, {
        aspectRatio: NaN,
        background: true,
        dragMode: 'none',
        movable: false,
        autoCrop: false,
        zoomable: false,
        responsive: false,
        zoomOnWheel: false,
        zoomOnTouch: false,
        touchDragZoom: false,
        checkCrossOrigin: false,
        checkOrientation: false,
        viewMode: 1,
        crop(event) {
          if (!self.cropLabel) return

          const { width, height } = event.detail
          self.cropLabel.textContent = `${Math.round(width)} × ${Math.round(height)}`
          self.selected = true
        },
        ready() {
          const imageData = this.cropper.getImageData()
          self.height = imageData.naturalHeight
          self.width = imageData.naturalWidth
        }
      })
    },

    inpaint() {
      if (!this.edittext?.trim()) {
        return
      }

      this.image().then((blob) => {
        this.mask().toBlob((mask) => {
          this.mutate(
            'image:inpaint',
            gql`
              mutation ($file: Upload!, $mask: Upload!, $prompt: String!) {
                inpaint(file: $file, mask: $mask, prompt: $prompt)
              }
            `,
            {
              file: new File([blob], 'image', { type: this.item.mime }),
              mask: new File([mask], 'mask', { type: 'image/png' }),
              prompt: this.edittext
            }
          )
            .then((response) => this.replace(this.toBlob(response.data?.inpaint)))
            .catch((error) => {
              this.messages.add(this.$gettext('Error editing image part') + ':\n' + error, 'error')
              this.$log('FileDetailItemImage::inpaint(): Error editing image part', error)
            })
            .finally(() => this.clear())
        })
      })
    },

    isolate() {
      this.cropper.getCroppedCanvas().toBlob((blob) => {
        this.mutate(
          'image:isolate',
          gql`
            mutation ($file: Upload!) {
              isolate(file: $file)
            }
          `,
          {
            file: new File([blob], 'image.png', { type: 'image/png' })
          }
        )
          .then((response) => this.replace(this.toBlob(response.data?.isolate)))
          .catch((error) => {
            this.messages.add(this.$gettext('Error removing background') + ':\n' + error, 'error')
            this.$log('FileDetailItemImage::isolate(): Error removing background', error)
          })
      })
    },

    mutate(action, mutation, variables) {
      if (this.readonly || !this.user.can(action)) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return Promise.reject()
      }

      this.loading[action] = true

      return this.$apollo
        .mutate({
          mutation,
          variables,
          context: { hasUpload: true }
        })
        .then((response) => {
          if (response.errors) throw response.errors
          return response
        })
        .finally(() => {
          this.loading[action] = false
        })
    },

    mask() {
      const canvas = document.createElement('canvas')
      const context = canvas.getContext('2d')

      const data = this.cropper.getImageData()
      const crop = this.cropper.getData()

      canvas.width = data.naturalWidth
      canvas.height = data.naturalHeight

      context.fillStyle = 'black'
      context.fillRect(0, 0, canvas.width, canvas.height)

      context.fillStyle = 'white'
      context.fillRect(crop.x, crop.y, crop.width, crop.height)

      return canvas
    },

    painted() {
      this.selected ? this.inpaint() : this.repaint()
      this.menu['paint'] = false
    },

    repaint() {
      if (!this.edittext?.trim()) {
        return
      }

      this.image().then((blob) => {
        this.mutate(
          'image:repaint',
          gql`
            mutation ($file: Upload!, $prompt: String!) {
              repaint(file: $file, prompt: $prompt)
            }
          `,
          {
            file: new File([blob], 'image', { type: this.item.mime }),
            prompt: this.edittext
          }
        )
          .then((response) => this.replace(this.toBlob(response.data?.repaint)))
          .catch((error) => {
            this.messages.add(this.$gettext('Error editing image') + ':\n' + error, 'error')
            this.$log('FileDetailItemImage::repaint(): Error editing image', error)
          })
          .finally(() => this.clear())
      })
    },

    replace(blob, idx = null) {
      if (this.destroyed) return

      let file = null

      if (blob) {
        const image = URL.createObjectURL(blob)

        this.cropper.replace(image)

        if (idx !== null) {
          this.images.unshift(...this.images.splice(idx, 1))
        } else {
          this.images.unshift({ blob: blob, url: image })
        }

        this.images.splice(10).forEach((img) => {
          URL.revokeObjectURL(img.url)
        })

        file = new File([blob], this.item.path.split('/').pop(), { type: 'image/png' })
      }

      this.$emit('update:file', file)
      this.reset()
    },

    reset() {
      if (this.destroyed) return

      this.selected = false
      this.cropper.reset()
      this.cropper.clear()
    },

    rotate(deg) {
      this.cropper.rotate(deg)
      this.updateFile()

      this.$nextTick(() => {
        if (this.destroyed) return

        const container = this.cropper.getContainerData()
        const image = this.cropper.getImageData()
        let scaleX, scaleY

        if (Math.abs(Math.abs(image.rotate) - 180) === 90) {
          scaleX = container.width / image.naturalHeight
          scaleY = container.height / image.naturalWidth
        } else {
          scaleX = container.width / image.naturalWidth
          scaleY = container.height / image.naturalHeight
        }

        this.cropper.zoomTo(Math.min(scaleX, scaleY))
      })
    },

    uncrop() {
      if (!this.extend.top && !this.extend.right && !this.extend.bottom && !this.extend.left) {
        return
      }

      this.cropper.getCroppedCanvas().toBlob((blob) => {
        this.mutate(
          'image:uncrop',
          gql`
            mutation ($file: Upload!, $top: Int!, $right: Int!, $bottom: Int, $left: Int) {
              uncrop(file: $file, top: $top, right: $right, bottom: $bottom, left: $left)
            }
          `,
          {
            file: new File([blob], 'image.png', { type: 'image/png' }),
            top: this.extend.top ?? 0,
            right: this.extend.right ?? 0,
            bottom: this.extend.bottom ?? 0,
            left: this.extend.left ?? 0
          }
        )
          .then((response) => this.replace(this.toBlob(response.data?.uncrop)))
          .catch((error) => {
            this.messages.add(this.$gettext('Error uncropping image') + ':\n' + error, 'error')
            this.$log('FileDetailItemImage::uncrop(): Error uncropping image', error)
          })
      })
    },

    uncropped() {
      this.uncrop(this.extend.top, this.extend.right, this.extend.bottom, this.extend.left)
      this.menu['uncrop'] = false
    },

    updateFile() {
      if (!this.readonly && !this.destroyed) {
        this.cropper.getCroppedCanvas().toBlob((blob) => {
          const url = URL.createObjectURL(blob)

          this.images.unshift({ blob: blob, url: url })
          this.images.splice(10).forEach((img) => {
            URL.revokeObjectURL(img.url)
          })

          this.cropper.replace(url)
          this.$emit(
            'update:file',
            new File([blob], this.item.path.split('/').pop(), { type: 'image/png' })
          )
        })
      }
    },

    upscale(factor) {
      this.cropper.getCroppedCanvas().toBlob((blob) => {
        this.mutate(
          'image:upscale',
          gql`
            mutation ($file: Upload!, $factor: Int!) {
              upscale(file: $file, factor: $factor)
            }
          `,
          {
            file: new File([blob], 'image.png', { type: 'image/png' }),
            factor: factor
          }
        )
          .then((response) => this.replace(this.toBlob(response.data?.upscale)))
          .catch((error) => {
            this.messages.add(this.$gettext('Error upscaling image') + ':\n' + error, 'error')
            this.$log('FileDetailItemImage::upscale(): Error upscaling image', error)
          })
      })
    },

    use(items) {
      if (!items?.length) {
        return
      }

      this.cropper.replace(this.url(items[0].path, true))
      this.$emit('update:file', null)
      this.$emit('use', items)
      this.reset()
    }
  },

  watch: {
    item: function (item, old) {
      if (item.path !== old.path) {
        this.$nextTick(() => {
          this.init()
        })
      }
    }
  }
}
</script>

<template>
  <div ref="editorContainer" class="editor-container">
    <img
      ref="image"
      :src="url(item.path, true)"
      :alt="item.name"
      class="element"
      crossorigin="anonymous"
    />

    <div v-if="!readonly" class="toolbar">
      <v-btn
        v-if="selected"
        @click="clear()"
        :title="$gettext('Cancel')"
        :icon="mdiClose"
        class="no-rtl"
      />
      <component
        v-else
        :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
        :aria-label="$gettext('Select area')"
        v-model="menu['select']"
        transition="scale-transition"
        location="end center"
        max-width="300"
      >
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            :title="$gettext('Select area')"
            :icon="mdiCropFree"
            class="no-rtl"
          />
        </template>

        <v-card>
          <v-toolbar density="compact">
            <v-toolbar-title>{{ $gettext('Select area') }}</v-toolbar-title>
            <v-btn
              :icon="mdiClose"
              :aria-label="$gettext('Close')"
              @click="menu['select'] = false"
            />
          </v-toolbar>

          <v-list @click="menu['select'] = false">
            <v-list-item>
              <v-btn
                :prepend-icon="mdiCropFree"
                class="no-rtl"
                variant="text"
                @click="aspect(ratio)"
                >{{ $gettext('Original ratio') }}</v-btn
              >
            </v-list-item>
            <v-list-item>
              <v-btn
                :prepend-icon="mdiCropFree"
                class="no-rtl"
                variant="text"
                @click="aspect(NaN)"
                >{{ $gettext('No ratio') }}</v-btn
              >
            </v-list-item>
            <v-list-item>
              <v-btn :prepend-icon="mdiCropFree" class="no-rtl" variant="text" @click="aspect(1)">{{
                $gettext('Square')
              }}</v-btn>
            </v-list-item>
            <v-list-item>
              <v-btn
                :prepend-icon="mdiCropFree"
                class="no-rtl"
                variant="text"
                @click="aspect(3 / 2)"
                >3:2</v-btn
              >
            </v-list-item>
            <v-list-item>
              <v-btn
                :prepend-icon="mdiCropFree"
                class="no-rtl"
                variant="text"
                @click="aspect(4 / 3)"
                >4:3</v-btn
              >
            </v-list-item>
            <v-list-item>
              <v-btn
                :prepend-icon="mdiCropFree"
                class="no-rtl"
                variant="text"
                @click="aspect(5 / 3)"
                >5:3</v-btn
              >
            </v-list-item>
            <v-list-item>
              <v-btn
                :prepend-icon="mdiCropFree"
                class="no-rtl"
                variant="text"
                @click="aspect(16 / 9)"
                >16:9</v-btn
              >
            </v-list-item>
          </v-list>
        </v-card>
      </component>

      <v-btn
        @click="crop()"
        :disabled="!selected"
        :title="$gettext('Crop selected area')"
        :icon="mdiCrop"
        class="no-rtl"
      />

      <v-btn
        v-if="user.can('image:erase')"
        @click="erase()"
        :disabled="!selected"
        :loading="loading['image:erase']"
        :title="$gettext('Erase selected area')"
        :icon="mdiEraser"
        class="no-rtl"
      />

      <v-dialog
        v-if="(selected && user.can('image:inpaint')) || (!selected && user.can('image:repaint'))"
        v-model="menu['paint']"
        transition="scale-transition"
        max-width="600"
      >
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            :loading="loading['image:inpaint'] || loading['image:repaint']"
            :title="$gettext('Edit image')"
            :icon="mdiImageEdit"
            class="no-rtl"
          />
        </template>

        <v-card>
          <v-toolbar density="compact">
            <v-toolbar-title>{{ $gettext('Edit image') }}</v-toolbar-title>
            <v-btn
              :icon="mdiClose"
              :aria-label="$gettext('Close')"
              @click="menu['paint'] = false"
            />
          </v-toolbar>

          <v-card-text>
            <v-textarea
              v-model="edittext"
              :label="$gettext('Describe the changes')"
              variant="underlined"
              autofocus
              clearable
              auto-grow
            ></v-textarea>
          </v-card-text>

          <v-card-actions>
            <v-btn variant="outlined" :disabled="!edittext" @click="painted">{{
              $gettext('Edit image')
            }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <v-btn
        v-if="user.can('image:isolate')"
        @click="isolate()"
        :title="$gettext('Remove background')"
        :loading="loading['image:isolate']"
        :icon="mdiImageFilterBlackWhite"
        class="no-rtl"
      />

      <v-dialog
        v-if="user.can('image:uncrop')"
        v-model="menu['uncrop']"
        transition="scale-transition"
        max-width="300"
      >
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            :loading="loading['image:uncrop']"
            :title="$gettext('Expand image')"
            :icon="mdiArrowExpandAll"
            class="no-rtl"
          />
        </template>

        <v-card class="uncrop">
          <v-toolbar density="compact">
            <v-toolbar-title>{{ $gettext('Expand image') }}</v-toolbar-title>
            <v-btn
              :icon="mdiClose"
              :aria-label="$gettext('Close')"
              @click="menu['uncrop'] = false"
            />
          </v-toolbar>

          <v-card-text>
            <v-row class="single">
              <v-col cols="6">
                <v-number-input
                  v-model="extend.top"
                  variant="outlined"
                  controlVariant="hidden"
                  :label="$gettext('Top')"
                  :max="2000"
                  :min="0"
                />
              </v-col>
            </v-row>
            <v-row>
              <v-col cols="6">
                <v-number-input
                  v-model="extend.left"
                  variant="outlined"
                  controlVariant="hidden"
                  :label="$gettext('Left')"
                  :max="2000"
                  :min="0"
                />
              </v-col>
              <v-col cols="6">
                <v-number-input
                  v-model="extend.right"
                  variant="outlined"
                  controlVariant="hidden"
                  :label="$gettext('Right')"
                  :max="2000"
                  :min="0"
                />
              </v-col>
            </v-row>
            <v-row class="single">
              <v-col cols="6">
                <v-number-input
                  v-model="extend.bottom"
                  variant="outlined"
                  controlVariant="hidden"
                  :label="$gettext('Bottom')"
                  :max="2000"
                  :min="0"
                />
              </v-col>
            </v-row>
          </v-card-text>

          <v-card-actions>
            <v-btn variant="outlined" @click="uncropped">{{ $gettext('Expand image') }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <component
        v-if="user.can('image:upscale')"
        :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
        :aria-label="$gettext('Upscale')"
        v-model="menu['upscale']"
        transition="scale-transition"
        location="end center"
        max-width="300"
      >
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            :loading="loading['image:upscale']"
            :disabled="width >= 4096 && height >= 4096"
            :title="$gettext('Upscale image')"
            :icon="mdiMagnifyExpand"
            class="no-rtl"
          />
        </template>

        <v-card>
          <v-toolbar density="compact">
            <v-toolbar-title>{{ $gettext('Upscale image') }}</v-toolbar-title>
            <v-btn
              :icon="mdiClose"
              :aria-label="$gettext('Close')"
              @click="menu['upscale'] = false"
            />
          </v-toolbar>

          <v-list @click="menu['upscale'] = false">
            <v-list-item v-if="width * 16 <= 4096 && height * 16 <= 4096">
              <v-btn
                :prepend-icon="mdiMagnifyExpand"
                class="no-rtl"
                variant="text"
                @click="upscale(16)"
              >
                {{ $gettext('Scale %{factor}', { factor: '16x' }) }}
              </v-btn>
            </v-list-item>
            <v-list-item v-if="width * 8 <= 4096 && height * 8 <= 4096">
              <v-btn
                :prepend-icon="mdiMagnifyExpand"
                class="no-rtl"
                variant="text"
                @click="upscale(8)"
              >
                {{ $gettext('Scale %{factor}', { factor: '8x' }) }}
              </v-btn>
            </v-list-item>
            <v-list-item v-if="width * 4 <= 4096 && height * 4 <= 4096">
              <v-btn
                :prepend-icon="mdiMagnifyExpand"
                class="no-rtl"
                variant="text"
                @click="upscale(4)"
              >
                {{ $gettext('Scale %{factor}', { factor: '4x' }) }}
              </v-btn>
            </v-list-item>
            <v-list-item v-if="width * 2 <= 4096 && height * 2 <= 4096">
              <v-btn
                :prepend-icon="mdiMagnifyExpand"
                class="no-rtl"
                variant="text"
                @click="upscale(2)"
              >
                {{ $gettext('Scale %{factor}', { factor: '2x' }) }}
              </v-btn>
            </v-list-item>
          </v-list>
        </v-card>
      </component>

      <v-btn
        :icon="mdiRotateLeft"
        class="no-rtl"
        @click="rotate(-90)"
        :title="$gettext('Rotate counter-clockwise')"
      />
      <v-btn
        :icon="mdiRotateRight"
        class="no-rtl"
        @click="rotate(90)"
        :title="$gettext('Rotate clockwise')"
      />

      <v-btn
        :icon="mdiFlipHorizontal"
        class="no-rtl"
        @click="flipX"
        :title="$gettext('Flip horizontally')"
      />
      <v-btn
        :icon="mdiFlipVertical"
        class="no-rtl"
        @click="flipY"
        :title="$gettext('Flip vertically')"
      />

      <v-btn :icon="mdiDownload" class="no-rtl" @click="download()" :title="$gettext('Download')" />

      <component
        :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
        :aria-label="$gettext('Undo')"
        v-model="menu['undo']"
        transition="scale-transition"
        location="end center"
        max-width="300"
      >
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            :disabled="!images.length"
            :title="$gettext('Undo')"
            :icon="mdiHistory"
            class="no-rtl"
          />
        </template>

        <v-card>
          <v-toolbar density="compact">
            <v-toolbar-title>{{ $gettext('Undo') }}</v-toolbar-title>
            <v-btn :icon="mdiClose" :aria-label="$gettext('Close')" @click="menu['undo'] = false" />
          </v-toolbar>

          <v-list @click="menu['undo'] = false">
            <v-list-item v-for="(img, idx) in images.slice(1)" :key="img.url">
              <v-img
                :src="img.url"
                :alt="$gettext('Previous edit')"
                @click="replace(img.blob, idx + 1)"
              />
            </v-list-item>
            <v-list-item>
              <v-img :src="url(item.path)" :alt="$gettext('Original')" @click="use([item])" />
            </v-list-item>
          </v-list>
        </v-card>
      </component>
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

:deep(.cropper-bg) {
  background-repeat: repeat;
}

:deep(.crop-label) {
  position: absolute;
  top: calc(50% + 16px);
  left: 50%;
  color: #fff;
  font-size: 100%;
  padding: 12px 6px;
  border-radius: 4px;
  white-space: nowrap;
  pointer-events: none;
  transform: translate(-50%, -50%);
  background: rgba(0, 0, 0, 0.6);
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

.uncrop .single,
.v-card.uncrop .v-card-actions {
  justify-content: center;
}

.uncrop .v-number-input :deep(.v-field__input) {
  text-align: center;
}

.v-dialog .v-btn {
  display: block;
  margin: auto;
}
</style>
