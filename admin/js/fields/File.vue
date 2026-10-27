/** @license MIT, https://opensource.org/license/mit */

<script>
import { mdiFileOutline } from '@mdi/js'
import { AUDIO_MIME_FILTER, IMAGE_MIME_FILTER, MEDIA_MIME_FILTER, revokeBlob, VIDEO_MIME_FILTER } from '../files'
import { fileurl } from '../urls'
import { formatDate } from '../utils'
import FileField, { components, useFileField } from '../filefield'

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
  extends: FileField,

  components,

  props: {
    modelValue: { type: [Object, null], default: () => null }
  },

  data() {
    return {
      file: {},
      protectSet: false,
      selected: null
    }
  },

  setup() {
    return { ...useFileField(), fileurl, mdiFileOutline }
  },

  unmounted() {
    revokeBlob(this.file)
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
      if (!this.user.canAdd('file:add')) return this.messages.denied()

      // only one upload at a time, a second one would race the first
      if (!file || this.file.uploading) {
        return
      }

      const { pending, promise } = this.upload(file)
      this.file = pending

      return promise.then((item) => {
        if (this.file.path === pending.path) {
          this.file = {}
        }
        this.selected = null

        // shows the previous file again if the upload failed
        return item ? this.select([item]) : this.load()
      })
    },

    formatDate(dateStr) {
      return formatDate(dateStr, this.$vuetify.locale.current)
    },

    // shows the file of the value if it was replaced from outside, e.g. by reverting or restoring a version,
    // a pending upload has no ID yet and is kept until it's finished
    load() {
      const id = this.modelValue?.id || null

      if (!this.file.uploading && (this.file.id || null) !== id && (!id || this.assets[id])) {
        revokeBlob(this.file)
        this.file = id ? this.assets[id] : {}
        this.protect = this.file.disk === 'private'
        this.protectSet = false
      }

      this.$emit('error', !this.rules.every((rule) => rule(this.file) === true))
    },

    remove() {
      revokeBlob(this.file)

      if (this.file.id) {
        this.$emit('removeFile', this.file.id)
      }

      this.$emit('update:modelValue', null)
      this.file = {}
      this.protect = false
      this.protectSet = false
    },

    select(items) {
      const protect = this.protectSet ? this.protect : null
      const item = [].concat(items)[0]

      if (!item?.id) {
        this.$log(`File::select(): Invalid item without ID`, items)
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

    async setProtect(value) {
      this.protect = Boolean(value)

      if (!this.file.id) {
        this.protectSet = true
        return
      }

      const previous = this.file.disk === 'private'

      if (previous === this.protect || this.protecting) {
        return
      }

      const { items } = await this.relocate([this.file], this.protect)

      if (items[0]) {
        this.file = { ...this.file, ...items[0] }
        this.$emit('addFile', this.file)
      }

      this.protect = this.file.disk === 'private'
      this.protectSet = false
    }
  },

  watch: {
    assets: 'load',

    modelValue: {
      immediate: true,
      handler: 'load'
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

  <v-row class="file-field" :class="{ 'field-columns': file.path && !file.uploading }">
    <v-col cols="12" md="6">
      <div class="files" :class="{ readonly: readonly }">
        <div v-if="file.id || file.uploading" class="file">
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
            <v-icon :icon="mdiFileOutline" />
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
              :src="previewUrl(file)"
              :alt="file.name"
              :draggable="false"
              class="checkered"
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
    <v-col cols="12" md="6" v-if="file.path && !file.uploading" class="meta">
      <v-row
        v-for="row in [
          [$gettext('name'), file.name],
          [$gettext('description'), description],
          [$gettext('MIME'), file.mime],
          [$gettext('editor'), file.editor],
          [$gettext('updated'), formatDate(file.updated_at)]
        ]"
        :key="row[0]"
      >
        <v-col cols="12" md="3" class="name">{{ row[0] }}:</v-col>
        <v-col cols="12" md="9">{{ row[1] }}</v-col>
      </v-row>
    </v-col>
  </v-row>

  <Teleport to="body">
    <FileDialog
      v-model="vfiles"
      :filter="profile.filter"
      :grid="profile.grid"
      @add="select($event); vfiles = false"
    />
    <FileAiDialog
      v-if="profile.imagine"
      v-model="vcreate"
      :context="context"
      :disk="protect ? 'private' : 'public'"
      @add="select($event); vcreate = false"
    />
    <FileUrlDialog
      v-model="vurls"
      :disk="protect ? 'private' : 'public'"
      :mime="profile.mime"
      @add="select($event)"
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

/* Layout depends on the width available to the field, not on the viewport */
.file-field {
  container-type: inline-size;
}

@container (width < 576px) {
  .file-field > .v-col {
    flex: 0 0 100%;
    max-width: 100%;
  }
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
