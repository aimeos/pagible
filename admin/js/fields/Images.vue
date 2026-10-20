/** @license MIT, https://opensource.org/license/mit */

<script>
import { VueDraggable } from 'vue-draggable-plus'
import { IMAGE_MIME_FILTER, revokeBlob } from '../files'
import { required, minEntries, maxEntries } from '../rules'
import { moveStep, DRAG_ANIMATION } from '../utils'
import FileField, { components, useFileField } from '../filefield'

/**
 * Configuration:
 * - `hint`: string, description shown below the field while it has focus
 * - `accept`: string, accepted file types for uploads, "image/*" by default
 * - `max`: int, maximum number of images allowed
 * - `min`: int, minimum number of images required
 * - `required`: boolean, if true, at least one image is required
 */
export default {
  extends: FileField,

  components: {
    ...components,
    VueDraggable
  },

  props: {
    modelValue: { type: Array, default: () => [] }
  },

  setup() {
    return { ...useFileField(), DRAG_ANIMATION, IMAGE_MIME_FILTER }
  },

  data() {
    return {
      images: [],
      // IDs of the value whose files aren't loaded yet, kept in the value until they are known
      missing: [],
      // IDs of the last loaded value, keeps the position of the missing files
      order: []
    }
  },

  computed: {
    allPrivate() {
      return this.images.length > 0 && this.images.every((item) => item.disk === 'private')
    },

    isPrivate() {
      return this.images.some((item) => item.disk === 'private')
    },

    rules() {
      return [
        required(this.$gettext, this.config.required),
        minEntries(this.$ngettext, this.config.min),
        maxEntries(this.$ngettext, this.config.max)
      ]
    }
  },

  created() {
    // set if the protection changed while files were moved, they are moved again afterwards
    this.protectPending = false
  },

  beforeUnmount() {
    this.images.forEach(revokeBlob)
    this.images = []
  },

  methods: {
    add(files) {
      if (!this.user.can('file:add')) return this.messages.denied()

      if (!files?.length) {
        return
      }

      const promises = Array.from(files, (file) => {
        const { pending, promise } = this.upload(file)
        this.images.push(pending)

        return promise.then((item) => {
          const idx = this.images.indexOf(pending)

          if (idx === -1) {
            return
          }

          if (item) {
            this.images[idx] = item
            this.$emit('addFile', item)
          } else {
            this.images.splice(idx, 1)
          }
        })
      })

      return Promise.all(promises).then(() => this.change())
    },

    change() {
      this.$emit('update:modelValue', this.ids().map((id) => ({ id, type: 'file' })))
    },

    // IDs of the shown images and the missing files, each missing one stays after the image which
    // preceded it in the loaded value; pending uploads have no ID yet and are added after they are finished
    ids() {
      const list = this.images.filter((item) => item.id).map((item) => item.id)

      for (const id of this.missing) {
        const before = this.order.slice(0, this.order.indexOf(id))
        const prev = before.findLast((other) => list.includes(other))
        list.splice(prev ? list.indexOf(prev) + 1 : 0, 0, id)
      }

      return list
    },

    // moves the image by keyboard, the image keeps the focus
    move(ev, idx) {
      const step = this.readonly ? 0 : moveStep(ev, true, this.$vuetify.locale.isRtl) || moveStep(ev) * this.perRow(ev.currentTarget)

      if (!step) return

      // Alt+ArrowLeft would navigate back in the browser history otherwise
      ev.preventDefault()

      const pos = idx + step
      const target = ev.currentTarget

      if (pos < 0 || pos >= this.images.length) return

      this.images.splice(pos, 0, this.images.splice(idx, 1)[0])

      this.change()
      this.$nextTick(() => target.focus())
    },

    // number of images in the first row of the wrapping grid
    perRow(el) {
      const images = [...(el.closest('.images')?.querySelectorAll('.image') || [])]
      return Math.max(images.filter((image) => image.offsetTop === images[0].offsetTop).length, 1)
    },

    // moved here from another images field, the file may not be known by this element yet
    dropped(event) {
      const item = this.images[event.newDraggableIndex ?? event.newIndex]

      if (item?.id) {
        this.$emit('addFile', item)
      }

      this.change()
    },

    description(file) {
      return Object.values(file.description || {}).shift()
    },

    // shows the images of the value if it was replaced from outside, e.g. by reverting or restoring a version,
    // and ignores the values emitted by this field which list the same images; files that aren't loaded
    // yet stay in the value and are shown when they are added to the assets
    load(list, force = false) {
      const ids = (list || []).map((entry) => entry?.id).filter(Boolean)
      const current = this.images.filter((item) => item.id)
      const shown = this.ids()

      if (!force && ids.length === shown.length && ids.every((id, idx) => id === shown[idx])) {
        return
      }

      const known = new Map(current.map((item) => [item.id, item]))
      const images = []
      const missing = []

      for (const id of ids) {
        const item = known.get(id) || this.assets[id]
        item ? images.push(item) : missing.push(id)
      }

      current.filter((item) => !images.includes(item)).forEach(revokeBlob)
      // pending uploads are kept and added to the value when they are finished
      this.images = [...images, ...this.images.filter((item) => !item.id)]
      this.missing = missing
      this.order = ids
      this.protect = this.allPrivate
    },

    remove(idx) {
      const item = this.images[idx]

      revokeBlob(item)

      if (item?.id) {
        this.$emit('removeFile', item.id)
      }

      this.images.splice(idx, 1)
      this.change()
    },

    select(items) {
      if (!Array.isArray(items)) {
        items = [items]
      }

      items.forEach((item) => {
        this.images.push(item)
        this.$emit('addFile', item)
      })

      this.change()
      this.vfiles = false

      if (this.protect) {
        this.setProtect(true)
      } else if (!this.protecting) {
        // a running move to public keeps its target state, the moved files aren't private anymore
        this.protect = this.allPrivate
      }
    },

    async setProtect(value) {
      const protect = Boolean(value)

      this.protect = protect

      // overlapping moves could finish in any order, files added meanwhile are moved afterwards
      if (this.protecting) {
        this.protectPending = true
        return
      }

      const files = this.images.filter(
        (item) => item.id && (item.disk === 'private') !== protect
      )

      if (!files.length) {
        return
      }

      const { items, failed } = await this.relocate(files, protect)

      this.sync(files, items)

      if (failed) {
        this.protectPending = false
        this.protect = this.allPrivate
      } else if (this.protectPending) {
        this.protectPending = false
        return this.setProtect(this.protect)
      }
    },

    // replaces the changed files by updated copies, the file objects are shared with the assets
    sync(files, entries) {
      const ids = new Set(files.map((item) => item.id))
      const map = new Map((entries || []).filter((data) => ids.has(data.id)).map((data) => [data.id, data]))
      const updated = []

      this.images = this.images.map((item) => {
        if (!map.has(item.id)) {
          return item
        }

        const copy = { ...item, ...map.get(item.id) }
        updated.push(copy)
        return copy
      })

      if (updated.length) {
        this.$emit('addFile', updated)
      }
    }
  },

  watch: {
    assets() {
      if (this.missing.length) {
        this.load(this.modelValue, true)
      }
    },

    modelValue: {
      immediate: true,
      handler(list) {
        this.load(list)

        this.$emit(
          'error',
          !this.rules.every((rule) => {
            return rule(this.modelValue) === true
          })
        )
      }
    }
  }
}
</script>

<template>
  <FileProtect
    :disabled="protecting"
    :labelled="!!label || !!$slots.label"
    :loading="protecting"
    :model-value="protect"
    :name="label"
    :locked="isPrivate"
    :readonly="readonly"
    @update:model-value="setProtect($event)"
  >
    <slot name="label" />
  </FileProtect>

  <VueDraggable
    v-model="images"
    :disabled="readonly"
    @update="change()"
    @add="dropped($event)"
    @remove="change()"
    draggable=".image"
    group="images"
    class="images"
    :animation="DRAG_ANIMATION"
  >
    <div
      v-for="(item, idx) in images"
      :key="item.id || item.path"
      :class="{ readonly: readonly }"
      class="image checkered"
      :title="description(item)"
    >
      <v-progress-linear v-if="item.uploading" color="primary" height="5" indeterminate rounded />
      <button
        v-if="item.path"
        type="button"
        class="image-preview"
        :aria-label="$gettext('Edit')"
        :aria-keyshortcuts="readonly ? undefined : 'Alt+ArrowLeft Alt+ArrowRight Alt+ArrowUp Alt+ArrowDown'"
        :disabled="!item.id"
        @click="open(item)"
        @keydown="move($event, idx)"
      >
        <v-img
          :srcset="filesrcset(item)"
          :src="previewUrl(item)"
          :alt="description(item)"
          draggable="false"
        />
      </button>

      <FileActionMenu
        v-if="item.id && !readonly"
        :editable="user.can('file:view')"
        @edit="open(item)"
        @remove="remove(idx)"
      />
    </div>

    <div v-if="!readonly" class="add">
      <div class="actions">
        <div class="icon-group">
          <v-btn
            v-if="user.can('file:view')"
            @click="vfiles = true"
            :title="$gettext('Add files')"
            :icon="mdiButtonCursor"
            class="btn-add"
            variant="text"
          />
          <v-btn
            @click="vurls = true"
            :title="$gettext('Add files from URLs')"
            :icon="mdiLinkVariantPlus"
            class="btn-add-urls"
            variant="text"
          />
        </div>
        <div class="icon-group">
          <v-btn
            v-if="user.can('image:imagine')"
            @click="vcreate = true"
            :title="$gettext('Create file')"
            :icon="mdiCreation"
            class="btn-create"
            variant="text"
          />
          <v-btn
            @click="$refs.upload.click()"
            :title="$gettext('Add files')"
            :icon="mdiUpload"
            class="btn-upload"
            variant="text"
          />
        </div>
      </div>

      <div
        class="dropzone"
        :class="{ dragover: dragging }"
        @dragenter.prevent="dragging = true"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="drop($event)"
      >
        <v-icon :icon="mdiTrayArrowDown" />
        <span>{{ $gettext('Drop files here') }}</span>
      </div>
    </div>
  </VueDraggable>

  <!-- Kept outside <VueDraggable> on purpose: a Vuetify input nested in the
       draggable's persistent "add" tile loses its ref owner context when
       vue-draggable-plus re-patches the tile on model changes. -->
  <input
    ref="upload"
    type="file"
    :accept="config.accept || 'image/*'"
    multiple
    hidden
    @change="add($event.target.files); $event.target.value = null"
  />

  <Teleport to="body">
    <FileDialog v-model="vfiles" @add="select($event)" :filter="IMAGE_MIME_FILTER" grid />
    <FileAiDialog
      v-model="vcreate"
      :context="context"
      :disk="protect ? 'private' : 'public'"
      @add="select($event); vcreate = false"
    />
    <FileUrlDialog
      v-model="vurls"
      :disk="protect ? 'private' : 'public'"
      @add="select($event)"
      mime="image/"
      multiple
    />
  </Teleport>
</template>

<style scoped>
.images {
  display: flex;
  justify-content: start;
  flex-wrap: wrap;
}

.images .add,
.images .image {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(var(--v-border-color), var(--v-medium-emphasis-opacity));
  border-radius: 4px;
  position: relative;
  height: 180px;
  width: 180px;
  margin: 1px;
}

.images .image-preview {
  background: transparent;
  border: 0;
  cursor: pointer;
  height: 100%;
  padding: 0;
  width: 100%;
}

.images .image-preview:disabled {
  cursor: default;
}

.images .image-preview .v-img {
  height: 100%;
  width: 100%;
}

.images .add {
  border: 1px dashed rgba(var(--v-border-color), var(--v-medium-emphasis-opacity));
  flex-flow: column;
  padding: 0;
  overflow: hidden;
}

/* Upper half: the add/url/create/upload buttons. */
.images .add .actions {
  flex: 1 1 50%;
  display: flex;
  flex-flow: column;
  align-items: center;
  justify-content: center;
  width: 100%;
}

/* Lower half: the drop target, filling the remaining 50% of the tile. */
.images .add .dropzone {
  flex: 1 1 50%;
  display: flex;
  flex-flow: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 4px;
  width: 100%;
  font-size: 0.75rem;
  border-top: 1px dashed rgba(var(--v-border-color), var(--v-medium-emphasis-opacity));
}

.images .add :deep(.v-icon) {
  --v-medium-emphasis-opacity: 1;
}

.v-progress-linear {
  position: absolute;
  z-index: 1;
}
</style>
