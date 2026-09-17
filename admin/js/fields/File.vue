/** @license MIT, https://opensource.org/license/mit */

<script>
import {
  mdiButtonCursor,
  mdiCreation,
  mdiLinkVariantPlus,
  mdiTrayArrowDown,
  mdiUpload
} from '@mdi/js'
import { createFile, RELOCATE_FILE } from '../files'
import { invalidateList } from '../graphql'
import { useUserStore, useMessageStore, useViewStack } from '../stores'
import {
  AUDIO_MIME_FILTER,
  IMAGE_MIME_FILTER,
  MEDIA_MIME_FILTER,
  VIDEO_MIME_FILTER,
  fileurl,
  filesrcset
} from '../utils'
import { defineAsyncComponent } from 'vue'
import FileActionMenu from '../components/FileActionMenu.vue'
import FileProtect from '../components/FileProtect.vue'

const FileUrlDialog = defineAsyncComponent(() => import('../components/FileUrlDialog.vue'))
const FileDialog = defineAsyncComponent(() => import('../components/FileDialog.vue'))
const FileAiDialog = defineAsyncComponent(() => import('../components/FileAiDialog.vue'))

const profiles = {
  audio: {
    accept: 'audio/*',
    filter: AUDIO_MIME_FILTER,
    mime: 'audio/'
  },
  file: {
    accept: '*',
    filter: {}
  },
  image: {
    accept: 'image/*',
    filter: IMAGE_MIME_FILTER,
    grid: true,
    imagine: true,
    mime: 'image/'
  },
  media: {
    accept: 'image/*,video/*',
    dropzone: false,
    filter: MEDIA_MIME_FILTER,
    grid: true,
    imagine: true
  },
  video: {
    accept: 'video/*',
    filter: VIDEO_MIME_FILTER,
    mime: 'video/'
  }
}

export default {
  inheritAttrs: false,

  components: {
    FileAiDialog,
    FileActionMenu,
    FileProtect,
    FileUrlDialog,
    FileDialog
  },

  props: {
    modelValue: { type: [Object, null], default: () => null },
    config: { type: Object, default: () => {} },
    assets: { type: Object, default: () => {} },
    label: { type: String, default: '' },
    readonly: { type: Boolean, default: false },
    context: { type: Object }
  },

  emits: ['update:modelValue', 'error', 'addFile', 'removeFile'],

  inject: {
    update: { default: null }
  },

  data() {
    return {
      dragging: false,
      file: {},
      protect: false,
      protectSet: false,
      protecting: false,
      selected: null,
      vcreate: false,
      vfiles: false,
      vurls: false
    }
  },

  setup() {
    const viewStack = useViewStack()
    const messages = useMessageStore()
    const user = useUserStore()

    return {
      user,
      messages,
      viewStack,
      fileurl,
      filesrcset,
      mdiButtonCursor,
      mdiCreation,
      mdiLinkVariantPlus,
      mdiTrayArrowDown,
      mdiUpload
    }
  },

  unmounted() {
    if (this.file?.path?.startsWith('blob:')) {
      URL.revokeObjectURL(this.file.path)
    }
  },

  computed: {
    description() {
      return Object.values(this.file.description || {}).shift() || ''
    },

    kind() {
      return 'file'
    },

    profile() {
      return profiles[this.kind] || profiles.file
    },

    rules() {
      return [(v) => !this.config.required || !!v?.path || this.$gettext(`File is required`)]
    }
  },

  methods: {
    add(file) {
      if (!this.user.can('file:add')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

      if (!file) {
        return
      }

      const path = URL.createObjectURL(file)
      const disk = this.protect ? 'private' : 'public'
      this.file = { disk, path: path, uploading: true }

      return createFile(this.$apollo, { disk, file })
        .then((item) => {
          invalidateList(this.$apollo.provider.defaultClient.cache, 'files')
          return this.select([item])
        })
        .catch((error) => {
          this.messages.add(
            this.$gettext(`Error adding file %{path}`, { path: file.name }) + ':\n' + error,
            'error'
          )
          this.$log(`File::addFile(): Error adding file`, file, error)
        })
        .finally(() => {
          if (this.file.path === path) {
            this.file = {}
          }
          URL.revokeObjectURL(path)
          this.selected = null
        })
    },

    addFromDialog(event) {
      this.select([event])
      this.vfiles = false
    },

    addFromAi(event) {
      this.select(event)
      this.vcreate = false
    },

    addFromUrl(event) {
      this.select(event)
      this.vurls = false
    },

    drop(event) {
      this.dragging = false

      const file = event.dataTransfer?.files?.[0]

      if (file) {
        this.add(file)
      }
    },

    formatDate(dateStr) {
      return new Date(dateStr).toLocaleString()
    },

    async open(item) {
      // Editing the image in the stacked FileDetail only updates the file's own
      // (already persisted) draft, not the page content, so just refresh the
      // preview when FileDetail saves.
      const { default: FileDetail } = await import('../views/FileDetail.vue')

      this.viewStack.openView(FileDetail, {
        item: item,
        stacked: true,
        onSaved: () => this.update?.()
      })
    },

    remove() {
      if (this.file.path.startsWith('blob:')) {
        URL.revokeObjectURL(this.file.path)
      }

      if (this.file.id) {
        this.$emit('removeFile', this.file.id)
      }

      this.$emit('update:modelValue', null)
      this.file = {}
      this.protect = false
      this.protectSet = false
    },

    select(items) {
      if (!Array.isArray(items) || !items.length) {
        this.$log(`File::select(): Items must be a non-empty array`, items)
        return
      }

      const protect = this.protectSet ? this.protect : null
      const item = items[0]

      if (!item?.id) {
        this.$log(`File::select(): Invalid item without ID`, item)
        return
      }

      this.file = { ...item }
      this.protect = item.disk === 'private'
      this.protectSet = false
      this.$emit('addFile', item)
      this.$emit('update:modelValue', { id: item.id, type: 'file' })

      if (protect !== null && protect !== this.protect) {
        return this.setProtect(protect)
      }

      return item
    },

    setProtect(value) {
      this.protect = Boolean(value)

      if (!this.file.id) {
        this.protectSet = true
        return
      }

      const previous = this.file.disk === 'private'

      if (previous === this.protect || this.protecting) {
        return
      }

      this.protecting = true

      return this.$apollo
        .mutate({
          mutation: RELOCATE_FILE,
          variables: {
            id: [this.file.id],
            disk: this.protect ? 'private' : 'public'
          }
        })
        .then((response) => {
          if (response.errors) {
            throw response.errors
          }

          const data = response.data?.relocateFile?.[0] || {}
          this.file = { ...this.file, ...data }
          invalidateList(this.$apollo.provider.defaultClient.cache, 'files')
          this.$emit('addFile', this.file)
        })
        .catch((error) => {
          this.protect = previous
          this.messages.add(this.$gettext(`Error saving file`) + ':\n' + error, 'error')
          this.$log(`File::setProtect(): Error relocating file`, this.file, error)
        })
        .finally(() => {
          this.protecting = false
          this.protectSet = false
        })
    }
  },

  watch: {
    assets: {
      handler(assets) {
        if (!this.file.path && this.modelValue && assets[this.modelValue.id]) {
          this.file = assets[this.modelValue.id]
          this.protect = this.file.disk === 'private'
        }

        this.$emit(
          'error',
          !this.rules.every((rule) => {
            return rule(this.file) === true
          })
        )
      }
    },

    modelValue: {
      immediate: true,
      handler(data) {
        if (!this.file.path && data && this.assets[data.id]) {
          this.file = this.assets[data.id]
          this.protect = this.file.disk === 'private'
        }

        this.$emit(
          'error',
          !this.rules.every((rule) => {
            return rule(this.file) === true
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
    :locked="file.disk === 'private'"
    :readonly="readonly"
    @update:model-value="setProtect($event)"
  >
    <slot name="label" />
  </FileProtect>

  <v-row :class="{ 'field-columns': file.path }">
    <v-col cols="12" md="6">
      <div class="files" :class="{ readonly: readonly }">
        <div v-if="file.id" class="file">
          <v-progress-linear
            v-if="file.uploading"
            color="primary"
            height="5"
            indeterminate
            rounded
          />
          <button
            v-if="kind === 'file'"
            type="button"
            class="file-preview"
            :aria-label="$gettext('Edit')"
            :title="$gettext('Edit')"
            @click="open(file)"
          >
            <svg
              draggable="false"
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              fill="currentColor"
              class="bi bi-file-earmark-binary"
              viewBox="0 0 16 16"
            >
              <path
                d="M7.05 11.885c0 1.415-.548 2.206-1.524 2.206C4.548 14.09 4 13.3 4 11.885c0-1.412.548-2.203 1.526-2.203.976 0 1.524.79 1.524 2.203m-1.524-1.612c-.542 0-.832.563-.832 1.612q0 .133.006.252l1.559-1.143c-.126-.474-.375-.72-.733-.72zm-.732 2.508c.126.472.372.718.732.718.54 0 .83-.563.83-1.614q0-.129-.006-.25zm6.061.624V14h-3v-.595h1.181V10.5h-.05l-1.136.747v-.688l1.19-.786h.69v3.633z"
              />
              <path
                d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2M9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"
              />
            </svg>
            {{ file.name }}
          </button>
          <audio
            v-else-if="kind === 'audio' && file.path"
            :src="fileurl(file)"
            :draggable="false"
            controls
          />
          <video
            v-else-if="
              (kind === 'video' || file.mime?.startsWith('video/')) && file.path
            "
            :src="fileurl(file)"
            :draggable="false"
            controls
          />
          <button
            v-else-if="file.path"
            type="button"
            class="file-preview"
            :aria-label="$gettext('Edit')"
            :title="$gettext('Edit')"
            @click="open(file)"
          >
            <v-img
              :srcset="filesrcset(file)"
              :src="fileurl(file, Object.values(file.previews || {})[0] ?? file.path)"
              :alt="file.name"
              :draggable="false"
            />
          </button>

          <FileActionMenu
            v-if="file.id && !readonly"
            :editable="user.can('file:view')"
            @edit="open(file)"
            @remove="remove()"
          />
        </div>

        <div v-else-if="!readonly" class="file file-empty">
          <div class="actions">
            <v-btn
              v-if="user.can('file:view')"
              @click="vfiles = true"
              :title="$gettext('Add file')"
              :icon="mdiButtonCursor"
              class="btn-add"
              variant="text"
            />
            <v-btn
              @click="vurls = true"
              :title="$gettext('Add file from URL')"
              :icon="mdiLinkVariantPlus"
              class="btn-add-url"
              variant="text"
            />
            <v-btn
              v-if="profile.imagine && user.can('image:imagine')"
              @click="vcreate = true"
              :title="$gettext('Create file')"
              :icon="mdiCreation"
              class="btn-create"
              variant="text"
            />
            <v-btn
              :title="$gettext('Upload file')"
              :icon="mdiUpload"
              class="btn-upload"
              variant="text"
            >
              <v-file-input
                v-model="selected"
                @update:modelValue="add($event)"
                :accept="config.accept || profile.accept"
                :hide-input="true"
                :prepend-icon="mdiUpload"
              />
            </v-btn>
          </div>

          <div
            v-if="profile.dropzone !== false"
            class="dropzone"
            :class="{ dragover: dragging }"
            @dragenter.prevent="dragging = true"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="drop($event)"
          >
            <v-icon :icon="mdiTrayArrowDown" />
            <span>{{ $gettext('Drop file here to upload') }}</span>
          </div>
        </div>
      </div>
    </v-col>
    <v-col cols="12" md="6" v-if="file.path" class="meta">
      <v-row>
        <v-col cols="12" md="3" class="name">{{ $gettext('name') }}:</v-col>
        <v-col cols="12" md="9">{{ file.name }}</v-col>
      </v-row>
      <v-row>
        <v-col cols="12" md="3" class="name">{{ $gettext('description') }}:</v-col>
        <v-col cols="12" md="9">{{ description }}</v-col>
      </v-row>
      <v-row>
        <v-col cols="12" md="3" class="name">{{ $gettext('MIME') }}:</v-col>
        <v-col cols="12" md="9">{{ file.mime }}</v-col>
      </v-row>
      <v-row>
        <v-col cols="12" md="3" class="name">{{ $gettext('editor') }}:</v-col>
        <v-col cols="12" md="9">{{ file.editor }}</v-col>
      </v-row>
      <v-row>
        <v-col cols="12" md="3" class="name">{{ $gettext('updated') }}:</v-col>
        <v-col cols="12" md="9">{{ formatDate(file.updated_at) }}</v-col>
      </v-row>
    </v-col>
  </v-row>

  <Teleport to="body">
    <FileDialog
      v-model="vfiles"
      :filter="profile.filter"
      :grid="profile.grid"
      @add="addFromDialog"
    />
  </Teleport>

  <Teleport to="body">
    <FileAiDialog
      v-if="profile.imagine"
      v-model="vcreate"
      :context="context"
      :disk="protect ? 'private' : 'public'"
      @add="addFromAi"
    />
  </Teleport>

  <Teleport to="body">
    <FileUrlDialog
      v-model="vurls"
      :disk="protect ? 'private' : 'public'"
      :mime="profile.mime"
      @add="addFromUrl"
    />
  </Teleport>
</template>

<style>
.files {
  border: 1px dashed rgba(var(--v-border-color), var(--v-medium-emphasis-opacity));
  border-radius: 8px;
}

.files .file {
  justify-content: center;
  align-items: center;
  position: relative;
  display: flex;
  min-height: 48px;
  max-height: 200px;
  max-width: 100%;
  width: 100%;
}

.files .file-preview {
  justify-content: center;
  align-items: center;
  align-self: stretch;
  background: transparent;
  border: 0;
  color: inherit;
  cursor: pointer;
  display: flex;
  font: inherit;
  max-height: 200px;
  max-width: 100%;
  min-height: 48px;
  padding: 0;
  width: 100%;
}

.files .file-preview .v-responsive.v-img {
  background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQAQMAAAAlPW0iAAAAA3NCSVQICAjb4U/gAAAABlBMVEXMzMz////TjRV2AAAACXBIWXMAAArrAAAK6wGCiw1aAAAAHHRFWHRTb2Z0d2FyZQBBZG9iZSBGaXJld29ya3MgQ1M26LyyjAAAABFJREFUCJlj+M/AgBVhF/0PAH6/D/HkDxOGAAAAAElFTkSuQmCC);
  background-repeat: repeat;
  max-width: 100%;
  height: 180px;
  width: 270px;
}

.files audio,
.files video {
  max-height: 200px;
  max-width: 100%;
}

.files .file.file-empty {
  flex-direction: column;
  max-height: none;
  cursor: default;
  gap: 8px;
  padding: 8px;
}

.files .file-empty .actions {
  justify-content: center;
  align-items: center;
  display: flex;
}

.files .file-empty .dropzone {
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  display: flex;
  gap: 4px;
  width: 100%;
  padding: 16px;
  border-radius: 8px;
  border: 1px dashed rgba(var(--v-border-color), var(--v-medium-emphasis-opacity));
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  cursor: copy;
  transition:
    background-color 0.2s,
    border-color 0.2s,
    color 0.2s;
}

.files .file-empty .dropzone.dragover {
  border-color: rgb(var(--v-theme-primary));
  background-color: rgba(var(--v-theme-primary), 0.08);
  color: rgb(var(--v-theme-primary));
}

.files .file-empty .dropzone * {
  pointer-events: none;
}

.files .v-input__prepend > .v-icon {
  opacity: 1;
}

.files .v-file-input {
  height: 48px;
  width: 48px;
}

.files .file .v-progress-linear {
  position: absolute;
  z-index: 1;
}

.field-columns > .v-col {
  flex: 1 1 360px;
  max-width: 100%;
}

.meta .v-row {
  margin-top: 8px !important;
  margin-bottom: 8px !important;
}

.meta .name {
  text-transform: capitalize;
  font-weight: bold;
}
</style>
