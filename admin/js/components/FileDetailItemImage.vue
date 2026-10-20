/** @license MIT, https://opensource.org/license/mit */

<script>
import { markRaw } from 'vue'
import ActionItem from './ActionItem.vue'
import ActionMenu from './ActionMenu.vue'
import CmsDialog from './Dialog.vue'
import { useUserStore, useMessageStore } from '../stores'
import { canvasBlob } from '../files'
import { fileurl } from '../urls'
import { imageAi } from '../imageai'
import {
  mdiClose,
  mdiCropFree,
  mdiCrop,
  mdiEraser,
  mdiImageEdit,
  mdiImageFilterBlackWhite,
  mdiInvertColors,
  mdiArrowExpandAll,
  mdiMagnifyExpand,
  mdiRotateLeft,
  mdiRotateRight,
  mdiFlipHorizontal,
  mdiFlipVertical,
  mdiDownload,
  mdiHistory
} from '@mdi/js'

// number of image versions kept for undo
const HISTORY_SIZE = 10
// maximum width and height upscaled images can have
const MAX_UPSCALE_SIZE = 4096

export default {
  components: { ActionItem, ActionMenu, CmsDialog },

  mixins: [imageAi],

  props: {
    item: { type: Object, required: true },
    readonly: { type: Boolean, default: false }
  },

  emits: ['update:file', 'use'],

  data() {
    return {
      destroyed: false,
      selected: false,
      cropLabel: null,
      cropper: null,
      images: [],
      // URL of an image from the server shown instead of the last edit
      source: null,
      width: 0,
      height: 0
    }
  },

  setup() {
    const messages = useMessageStore()
    const user = useUserStore()

    return {
      user,
      messages,
      fileurl,
      maxUpscaleSize: MAX_UPSCALE_SIZE,
      mdiClose,
      mdiCropFree,
      mdiCrop,
      mdiEraser,
      mdiImageEdit,
      mdiImageFilterBlackWhite,
      mdiInvertColors,
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

  created() {
    // cropper.js class, loaded on demand
    this.Cropper = null
  },

  mounted() {
    if (!this.readonly && !this.svg) {
      Promise.all([
        import('cropperjs'),
        import('cropperjs/dist/cropper.css')
      ]).then(([mod]) => {
        if (!this.destroyed) {
          this.Cropper = markRaw(mod.default)
          this.cropper = markRaw(this.init())
        }
      })
    }
  },

  beforeUnmount() {
    this.destroyed = true
    this.images.forEach((img) => URL.revokeObjectURL(img.url))
    this.cropper?.destroy()
  },

  computed: {
    aspects() {
      return [
        [this.$gettext('Original ratio'), this.ratio],
        [this.$gettext('No ratio'), NaN],
        [this.$gettext('Square'), 1],
        ['3:2', 3 / 2],
        ['4:3', 4 / 3],
        ['5:3', 5 / 3],
        ['16:9', 16 / 9]
      ]
    },

    // updated by the cropper's ready() handler whenever an image is loaded
    ratio() {
      return this.width && this.height ? this.width / this.height : NaN
    },

    svg() {
      return this.item.mime?.startsWith('image/svg')
    }
  },

  methods: {
    aspect(ratio) {
      if (!this.cropper) return

      this.cropper.setAspectRatio(ratio)
      this.cropper.setDragMode('crop')
    },

    clear() {
      if (this.destroyed || !this.cropper) return

      this.cropper.setDragMode('none')
      this.cropper.clear()
      this.selected = false
      this.cropLabel?.remove()
      this.cropLabel = null
    },

    crop() {
      // the result of a running AI operation would replace the edited image
      if (this.aiBusy) return

      this.updateFile()
      this.clear()
    },

    download() {
      if (!this.cropper) return
      canvasBlob(this.cropper.getCroppedCanvas()).then((blob) => {
        const url = URL.createObjectURL(blob)
        const link = document.createElement('a')

        link.href = url
        link.download = this.item.name || 'download'
        link.click()

        URL.revokeObjectURL(url)
      }).catch(() => {
        // the canvas was too large to encode
        this.messages.add(this.$gettext('Error downloading image'), 'error')
      })
    },

    flipX() {
      if (!this.cropper || this.aiBusy) return
      this.cropper.scaleX(-1)
      this.updateFile()
    },

    flipY() {
      if (!this.cropper || this.aiBusy) return
      this.cropper.scaleY(-1)
      this.updateFile()
    },

    // returns the image shown in the editor
    image() {
      if (!this.source && this.images[0]?.blob) {
        return Promise.resolve(this.images[0].blob)
      }

      return fetch(this.source || this.fileurl(this.item, this.item.path, true), {credentials: 'same-origin'}).then((response) => {
        if (!response.ok) {
          throw new Error('Network error: ' + response.statusText)
        }

        return response.blob()
      })
    },

    init() {
      if (this.readonly || this.destroyed || !this.Cropper) {
        return null
      }

      if (this.cropper) {
        this.cropper.destroy()

        // destroy() restores the <img> to cropperjs' originalUrl, so point it
        // back at the current path before re-initialising the cropper
        this.$refs.image.src = this.fileurl(this.item, this.item.path, !this.svg)
      }

      const self = this

      return new this.Cropper(this.$refs.image, {
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
          const cropBox = self.cropper?.cropBox

          if (!cropBox) return

          if (!self.cropLabel || self.cropLabel.parentNode !== cropBox) {
            const label = document.createElement('div')

            label.className = 'crop-label'
            cropBox.appendChild(label)
            self.cropLabel = markRaw(label)
          }

          const { width, height } = event.detail

          self.cropLabel.textContent = `${Math.round(width)} × ${Math.round(height)}`
          self.selected = true
        },
        ready() {
          const imageData = self.cropper?.getImageData()

          if (!imageData) return

          self.height = imageData.naturalHeight
          self.width = imageData.naturalWidth
        }
      })
    },

    monochrome() {
      if (!this.cropper || this.aiBusy) return

      this.clear()

      const canvas = this.cropper.getCroppedCanvas()
      const context = canvas.getContext('2d')
      const imageData = context.getImageData(0, 0, canvas.width, canvas.height)
      const data = imageData.data

      for (let i = 0; i < data.length; i += 4) {
        const gray = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114
        data[i] = gray
        data[i + 1] = gray
        data[i + 2] = gray
      }

      context.putImageData(imageData, 0, 0)

      canvasBlob(canvas).then((blob) => this.replace(blob)).catch(() => {
        // the canvas was too large to encode
        this.messages.add(this.$gettext('Error editing image'), 'error')
      })
    },

    replace(blob, idx = null) {
      // restoring an earlier edit while an AI operation is running would be overwritten by its result
      if (this.destroyed || !this.cropper || (idx !== null && this.aiBusy)) return

      let file = null

      if (blob) {
        if (idx !== null) {
          URL.revokeObjectURL(this.images.splice(idx, 1)[0].url)
        }

        this.cropper.replace(this.remember(blob))
        file = this.pngFile(blob)
      }

      this.$emit('update:file', file)
      this.reset()
    },

    // adds the image to the undo history and returns its object URL
    remember(blob) {
      const url = URL.createObjectURL(blob)

      this.source = null
      this.images.unshift({ blob: blob, url: url })
      this.images.splice(HISTORY_SIZE).forEach((img) => URL.revokeObjectURL(img.url))

      return url
    },

    reset() {
      if (this.destroyed || !this.cropper) return

      this.selected = false
      this.cropper.reset()
      this.cropper.clear()
    },

    rotate(deg) {
      if (!this.cropper || this.aiBusy) return

      this.cropper.rotate(deg)
      this.updateFile()

      this.$nextTick(() => {
        if (this.destroyed || !this.cropper) return

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

    // edited images are always encoded as PNG, so the file name must have the matching extension
    pngFile(blob) {
      const name = this.item.path.split('/').pop().replace(/\.[^.]*$/, '')
      return new File([blob], (name || 'image') + '.png', { type: 'image/png' })
    },

    updateFile() {
      if (this.readonly || this.destroyed || !this.cropper) return

      canvasBlob(this.cropper.getCroppedCanvas()).then((blob) => {
        // unmounted while encoding
        if (this.destroyed || !this.cropper) return

        this.cropper.replace(this.remember(blob))
        this.$emit('update:file', this.pngFile(blob))
      }).catch(() => {
        // the canvas was too large to encode
        if (!this.destroyed) {
          this.messages.add(this.$gettext('Error editing image'), 'error')
        }
      })
    },

    use(items) {
      if (!items?.length || !this.cropper || this.aiBusy) {
        return
      }

      this.source = this.fileurl(items[0], items[0].path, true)
      this.cropper.replace(this.source)
      this.$emit('update:file', null)
      this.$emit('use', items)
      this.reset()
    }
  },

  watch: {
    'item.path': function (path, old) {
      if (path === old || this.svg || this.readonly) {
        return
      }

      this.images.forEach((img) => URL.revokeObjectURL(img.url))
      this.images = []
      this.source = null

      this.$nextTick(() => {
        if (this.destroyed || !this.Cropper) return
        this.cropper = markRaw(this.init())
      })
    }
  }
}
</script>

<template>
  <div class="editor-container">
    <img
      ref="image"
      :src="fileurl(item, item.path, !svg)"
      :alt="item.name"
      class="element"
      :class="{ checkered: svg }"
      :crossorigin="svg ? undefined : 'anonymous'"
    />

    <div v-if="!readonly && !svg" class="toolbar">
      <v-btn
        v-if="selected"
        @click="clear()"
        :title="$gettext('Cancel')"
        :icon="mdiClose"
        class="no-rtl"
      />
      <ActionMenu
        v-else
        :title="$gettext('Select area')"
      >
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            :title="$gettext('Select area')"
            :icon="mdiCropFree"
            class="no-rtl"
          />
        </template>

        <ActionItem
          v-for="[label, value] in aspects"
          :key="label"
          :prepend-icon="mdiCropFree"
          class="no-rtl"
          @click="aspect(value)"
          >{{ label }}</ActionItem
        >
      </ActionMenu>

      <v-btn
        @click="crop()"
        :disabled="!selected || aiBusy"
        :title="$gettext('Crop selected area')"
        :icon="mdiCrop"
        class="btn-crop no-rtl"
      />

      <v-btn
        v-if="user.can('image:erase')"
        @click="erase()"
        :disabled="!selected || aiBusy"
        :loading="loading['image:erase']"
        :title="$gettext('Erase selected area')"
        :icon="mdiEraser"
        class="btn-erase no-rtl"
      />

      <template
        v-if="(selected && user.can('image:inpaint')) || (!selected && user.can('image:repaint'))"
      >
        <v-btn
          @click="menu['paint'] = true"
          :disabled="aiBusy"
          :loading="loading['image:inpaint'] || loading['image:repaint']"
          :title="$gettext('Edit image')"
          :icon="mdiImageEdit"
          class="no-rtl"
        />

        <CmsDialog
          v-model="menu['paint']"
          :title="$gettext('Edit image')"
          transition="scale-transition"
          max-width="600"
        >
          <v-textarea
            v-model="edittext"
            :label="$gettext('Describe the changes') + ' ‒ ' + $gettext('Describe what should be changed in the image, e.g. make the sky blue')"
            variant="underlined"
            autofocus
            clearable
            auto-grow
          ></v-textarea>

          <template #actions>
            <v-btn variant="tonal" color="primary" :disabled="!edittext" @click="painted">{{
              $gettext('Edit image')
            }}</v-btn>
          </template>
        </CmsDialog>
      </template>

      <v-btn
        v-if="user.can('image:isolate')"
        @click="isolate()"
        :disabled="aiBusy"
        :title="$gettext('Remove background')"
        :loading="loading['image:isolate']"
        :icon="mdiImageFilterBlackWhite"
        class="btn-remove-bg no-rtl"
      />

      <template v-if="user.can('image:uncrop')">
        <v-btn
          @click="menu['uncrop'] = true"
          :disabled="aiBusy"
          :loading="loading['image:uncrop']"
          :title="$gettext('Expand image')"
          :icon="mdiArrowExpandAll"
          class="btn-expand no-rtl"
        />

        <CmsDialog
          v-model="menu['uncrop']"
          :title="$gettext('Expand image')"
          content-class="uncrop"
          transition="scale-transition"
          max-width="300"
        >
          <v-row v-for="(row, idx) in edges" :key="idx" :class="{ single: row.length === 1 }">
            <v-col v-for="[key, label] in row" :key="key" cols="6">
              <v-number-input
                v-model="extend[key]"
                variant="outlined"
                controlVariant="hidden"
                :label="label"
                :max="2000"
                :min="0"
              />
            </v-col>
          </v-row>

          <template #actions>
            <v-btn variant="tonal" color="primary" @click="uncropped">{{
              $gettext('Expand image')
            }}</v-btn>
          </template>
        </CmsDialog>
      </template>

      <ActionMenu
        v-if="user.can('image:upscale')"
        :title="$gettext('Upscale image')"
      >
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            :loading="loading['image:upscale']"
            :disabled="aiBusy || (width >= maxUpscaleSize && height >= maxUpscaleSize)"
            :title="$gettext('Upscale image')"
            :icon="mdiMagnifyExpand"
            class="btn-upscale no-rtl"
          />
        </template>

        <ActionItem
          v-for="factor in factors"
          :key="factor"
          :prepend-icon="mdiMagnifyExpand"
          class="no-rtl"
          @click="upscale(factor)"
        >
          {{ $gettext('Scale %{factor}', { factor: factor + 'x' }) }}
        </ActionItem>
      </ActionMenu>

      <v-btn
        :icon="mdiRotateLeft"
        class="btn-rotate-ccw no-rtl"
        :disabled="aiBusy"
        @click="rotate(-90)"
        :title="$gettext('Rotate counter-clockwise')"
      />
      <v-btn
        :icon="mdiRotateRight"
        class="btn-rotate-cw no-rtl"
        :disabled="aiBusy"
        @click="rotate(90)"
        :title="$gettext('Rotate clockwise')"
      />

      <v-btn
        :icon="mdiFlipHorizontal"
        class="btn-flip-h no-rtl"
        :disabled="aiBusy"
        @click="flipX"
        :title="$gettext('Flip horizontally')"
      />
      <v-btn
        :icon="mdiFlipVertical"
        class="btn-flip-v no-rtl"
        :disabled="aiBusy"
        @click="flipY"
        :title="$gettext('Flip vertically')"
      />

      <v-btn
        :icon="mdiInvertColors"
        class="btn-monochrome no-rtl"
        :disabled="aiBusy"
        @click="monochrome()"
        :title="$gettext('Convert to monochrome')"
      />

      <v-btn :icon="mdiDownload" class="btn-download no-rtl" @click="download()" :title="$gettext('Download')" />

      <ActionMenu :title="$gettext('Undo')">
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            :disabled="!images.length || aiBusy"
            :title="$gettext('Undo')"
            :icon="mdiHistory"
            class="btn-undo no-rtl"
          />
        </template>

        <v-list-item v-for="(img, idx) in images" :key="img.url" @click="replace(img.blob, idx)">
          <v-img :src="img.url" :alt="$gettext('Previous edit')" />
        </v-list-item>
        <v-list-item @click="use([item])">
          <v-img :src="fileurl(item)" :alt="$gettext('Original')" />
        </v-list-item>
      </ActionMenu>
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
  display: block;
  margin: auto;
}

.element.checkered {
  width: 100%;
  min-height: 180px;
  object-fit: contain;
}

:deep(.crop-label) {
  position: absolute;
  top: calc(50% + 16px);
  left: 50%;
  color: rgb(var(--v-theme-on-background));
  font-size: 14px;
  line-height: 1.2;
  padding: 12px 6px;
  border-radius: 4px;
  white-space: nowrap;
  pointer-events: none;
  transform: translate(-50%, -50%);
  background: rgba(var(--v-theme-background), 0.72);
  backdrop-filter: blur(8px);
}

.uncrop .single {
  justify-content: center;
}

.uncrop .v-number-input :deep(.v-field__input) {
  text-align: center;
}
</style>
