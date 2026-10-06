/** @license MIT, https://opensource.org/license/mit */

<script>
import { VueDraggable } from 'vue-draggable-plus'
import { createFile, FETCH_FILE_DISKS, relocateFiles, revokeBlob } from '../files'
import { required, minEntries, maxEntries } from '../rules'
import { invalidateList } from '../graphql'
import { IMAGE_MIME_FILTER } from '../utils'
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
    return { ...useFileField(), IMAGE_MIME_FILTER }
  },

  data() {
    return {
      images: []
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

  beforeUnmount() {
    this.images.forEach(revokeBlob)
    this.images = []
  },

  methods: {
    add(files) {
      if (!this.user.can('file:add')) return this.messages.denied()

      const promises = []

      if (!files?.length) {
        return
      }

      for (const file of files) {
        const path = URL.createObjectURL(file)
        const disk = this.protect ? 'private' : 'public'
        const pending = { disk, path, uploading: true }
        this.images.push(pending)

        const promise = createFile(this.$apollo, { disk, file })
          .then((item) => {
            const idx = this.images.indexOf(pending)

            if (idx !== -1) {
              this.images[idx] = item
              this.$emit('addFile', item)
            }
          })
          .catch((error) => {
            const idx = this.images.indexOf(pending)

            if (idx !== -1) {
              this.images.splice(idx, 1)
            }
            this.messages.error(this.$gettext(`Error adding file %{path}`, { path: file.name }), error, file)
          })
          .finally(() => {
            URL.revokeObjectURL(path)
          })

        promises.push(promise)
      }

      return Promise.all(promises).then(() => {
        invalidateList('files')
        this.change()
      })
    },

    change() {
      this.$emit(
        'update:modelValue',
        this.images.map((item) => ({ id: item.id, type: 'file' }))
      )
    },

    description(file) {
      return Object.values(file.description || {}).shift()
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
      } else {
        this.protect = this.allPrivate
      }
    },

    async setProtect(value) {
      const protect = Boolean(value)
      const files = this.images.filter(
        (item) => item.id && (item.disk === 'private') !== protect
      )

      this.protect = protect

      if (!files.length) {
        return
      }

      this.protecting = true

      try {
        const items = await relocateFiles(this.$apollo, files.map((item) => item.id), protect ? 'private' : 'public')
        this.sync(files, items)
      } catch (error) {
        try {
          const response = await this.$apollo.query({
            query: FETCH_FILE_DISKS,
            variables: { id: files.map((item) => item.id) },
            fetchPolicy: 'no-cache'
          })

          this.sync(files, response.data?.files?.data)
        } catch (reloadError) {
          this.$log(`Images::setProtect(): Error reloading files`, reloadError)
        }

        this.protect = this.allPrivate
        this.messages.error(this.$gettext(`Error saving file`), error)
      } finally {
        this.protecting = false
      }
    },

    sync(files, entries) {
      const map = new Map(files.map((item) => [item.id, item]))
      const updated = []

      for (const data of entries || []) {
        const item = map.get(data.id)

        if (item) {
          Object.assign(item, data)
          updated.push(item)
        }
      }

      if (updated.length) {
        this.$emit('addFile', updated)
      }
    }
  },

  watch: {
    modelValue: {
      immediate: true,
      handler(list) {
        if (!this.images.length) {
          for (const entry of list || []) {
            if (this.assets[entry.id]) {
              this.images.push(this.assets[entry.id])
            }
          }

          this.protect = this.allPrivate
        }

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
    draggable=".image"
    group="images"
    class="images"
    animation="500"
  >
    <div
      v-for="(item, idx) in images"
      :key="idx"
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
        :disabled="!item.id"
        @click="open(item)"
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
