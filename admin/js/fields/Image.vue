/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import {
  mdiDotsVertical,
  mdiPencil,
  mdiTrashCan,
  mdiButtonCursor,
  mdiLinkVariantPlus,
  mdiCreation,
  mdiUpload
} from '@mdi/js'
import File from './File.vue'
import FileAiDialog from '../components/FileAiDialog.vue'

export default {
  extends: File,

  components: {
    FileAiDialog
  },

  setup() {
    return {
      ...File.setup(),
      mdiDotsVertical,
      mdiPencil,
      mdiTrashCan,
      mdiButtonCursor,
      mdiLinkVariantPlus,
      mdiCreation,
      mdiUpload
    }
  },

  data() {
    return {
      vcreate: false
    }
  },

  methods: {
    addFromAi(event) {
      this.select(event)
      this.vcreate = false
    },

    handle(data, path) {
      return new Promise((resolve, reject) => {
        const image = new Image()
        image.onload = resolve
        image.onerror = reject
        image.src = this.url(Object.values(data.previews).shift() || data.path)
      })
        .then(() => {
          return File.methods.handle.call(this, data, path)
        })
        .catch((error) => {
          this.$log(error)
          return false
        })
    }
  }
}
</script>

<template>
  <v-row>
    <v-col cols="12" md="6">
      <div class="files" :class="{ readonly: readonly }">
        <div
          v-if="file.id"
          class="file"
          @click="open(file)"
          @keydown.enter="open(file)"
          @keydown.space.prevent="open(file)"
          role="button"
          tabindex="0"
          :title="$gettext('Edit')"
        >
          <v-progress-linear
            v-if="file.uploading"
            color="primary"
            height="5"
            indeterminate
            rounded
          />
          <v-img
            v-if="file.path"
            :srcset="srcset(file.previews)"
            :src="url(file.path)"
            :alt="file.name"
            :draggable="false"
          />

          <v-menu v-if="file.id && !readonly" location="start">
            <template v-slot:activator="{ props }">
              <v-btn
                v-bind="props"
                :title="$gettext('Open menu')"
                :icon="mdiDotsVertical"
                class="btn-overlay"
                variant="text"
              />
            </template>
            <v-list>
              <v-list-item v-if="user.can('file:view')">
                <v-btn @click="open(file)" :prepend-icon="mdiPencil" variant="text">
                  {{ $gettext('Edit') }}
                </v-btn>
              </v-list-item>
              <v-list-item>
                <v-btn @click="remove()" :prepend-icon="mdiTrashCan" variant="text">
                  {{ $gettext('Remove') }}
                </v-btn>
              </v-list-item>
            </v-list>
          </v-menu>
        </div>

        <div v-else-if="!readonly" class="file">
          <v-btn
            v-if="user.can('file:view')"
            @click="vfiles = true"
            :title="$gettext('Add file')"
            :icon="mdiButtonCursor"
            variant="text"
          />
          <v-btn
            @click="vurls = true"
            :title="$gettext('Add file from URL')"
            :icon="mdiLinkVariantPlus"
            variant="text"
          />
          <v-btn
            v-if="user.can('image:imagine')"
            @click="vcreate = true"
            :title="$gettext('Create file')"
            :icon="mdiCreation"
            variant="text"
          />
          <v-btn :title="$gettext('Upload file')" :icon="mdiUpload" variant="text">
            <v-file-input
              v-model="selected"
              @update:modelValue="add($event)"
              :accept="config.accept || 'image/*'"
              :hide-input="true"
              :prepend-icon="mdiUpload"
            />
          </v-btn>
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
        <v-col cols="12" md="3" class="name">{{ $gettext('mime') }}:</v-col>
        <v-col cols="12" md="9">{{ file.mime }}</v-col>
      </v-row>
      <v-row>
        <v-col cols="12" md="3" class="name">{{ $gettext('editor') }}:</v-col>
        <v-col cols="12" md="9">{{ file.editor }}</v-col>
      </v-row>
      <v-row>
        <v-col cols="12" md="3" class="name">{{ $gettext('updated') }}:</v-col>
        <v-col cols="12" md="9">{{ new Date(file.updated_at).toLocaleString() }}</v-col>
      </v-row>
    </v-col>
  </v-row>

  <Teleport to="body">
    <FileDialog v-model="vfiles" @add="addFromDialog" :filter="{ mime: ['image/gif', 'image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'] }" grid />
  </Teleport>

  <Teleport to="body">
    <FileAiDialog v-model="vcreate" @add="addFromAi" :context="context" />
  </Teleport>

  <Teleport to="body">
    <FileUrlDialog v-model="vurls" @add="addFromUrl" />
  </Teleport>
</template>

<style scoped>
.v-responsive.v-img,
img {
  background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQAQMAAAAlPW0iAAAAA3NCSVQICAjb4U/gAAAABlBMVEXMzMz////TjRV2AAAACXBIWXMAAArrAAAK6wGCiw1aAAAAHHRFWHRTb2Z0d2FyZQBBZG9iZSBGaXJld29ya3MgQ1M26LyyjAAAABFJREFUCJlj+M/AgBVhF/0PAH6/D/HkDxOGAAAAAElFTkSuQmCC);
  background-repeat: repeat;
  max-width: 100%;
  height: 180px;
  width: 270px;
}

.v-file-input {
  width: 48px;
  height: 48px;
}
</style>
