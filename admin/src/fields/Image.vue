<script>
  import gql from 'graphql-tag'
  import File from './File.vue'
  import FileAiDialog from '../components/FileAiDialog.vue'

  export default {
    extends: File,

    components: {
      FileAiDialog
    },

    setup() {
      return { ...File.setup() }
    },

    data() {
      return {
        vcreate: false,
      }
    },

    methods: {
      handle(data, path) {
        return new Promise((resolve, reject) => {
          const image = new Image()
          image.onload = resolve
          image.onerror = reject
          image.src = this.url(Object.values(data.previews).shift() || data.path)
        }).then(() => {
          return File.methods.handle.call(this, data, path)
        }).catch(error => {
          console.error(error)
          return false
        })
      }
    }
  }
</script>

<template>
  <v-row>
    <v-col cols="12" md="6">
      <div class="files" :class="{readonly: readonly}">
        <div v-if="file.path" class="file" @click="open(file)">
          <v-progress-linear v-if="file.uploading"
            color="primary"
            height="5"
            indeterminate
            rounded
          />
          <v-img
            :draggable="false"
            :src="url(file.path)"
            :srcset="srcset(file.previews)"
          />
          <v-btn v-if="!readonly && file.path"
            @click.stop="remove()"
            :title="$gettext('Remove file')"
            icon="mdi-trash-can"
            class="btn-overlay"
            variant="flat"
          />
        </div>
        <div v-else-if="!readonly" class="file">
          <v-btn v-if="auth.can('file:view')"
            @click="vfiles = true"
            :title="$gettext('Add file')"
            icon="mdi-button-cursor"
            variant="flat"
          />
          <v-btn
            @click="vurls = true"
            :title="$gettext('Add file from URL')"
            icon="mdi-link-variant-plus"
            variant="flat"
          />
          <v-btn
            @click="vcreate = true"
            :title="$gettext('Create file')"
            icon="mdi-creation"
            variant="flat"
          />
          <v-btn
            :title="$gettext('Upload file')"
            icon="mdi-upload"
            variant="flat">
            <v-file-input
              v-model="selected"
              @update:modelValue="add($event)"
              :accept="config.accept || 'image/*'"
              :hide-input="true"
              prepend-icon="mdi-upload"
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
        <v-col cols="12" md="3" class="name">{{ $gettext('mime') }}:</v-col>
        <v-col cols="12" md="9">{{ file.mime }}</v-col>
      </v-row>
      <v-row>
        <v-col cols="12" md="3" class="name">{{ $gettext('editor') }}:</v-col>
        <v-col cols="12" md="9">{{ file.editor }}</v-col>
      </v-row>
      <v-row>
        <v-col cols="12" md="3" class="name">{{ $gettext('updated') }}:</v-col>
        <v-col cols="12" md="9">{{ (new Date(file.updated_at)).toLocaleString() }}</v-col>
      </v-row>
    </v-col>
  </v-row>

  <Teleport to="body">
    <FileDialog v-model="vfiles" @add="handle($event); vfiles = false" :filter="{mime: 'image/'}" grid />
  </Teleport>

  <Teleport to="body">
    <FileAiDialog v-model="vcreate" @add="select($event); vcreate = false" :context="context" />
  </Teleport>

  <Teleport to="body">
    <FileUrlDialog v-model="vurls" @add="select($event); vurls = false" />
  </Teleport>
</template>

<style scoped>
  .v-responsive.v-img, img {
    max-width: 100%;
    height: 180px;
    width: 270px;
  }
</style>
