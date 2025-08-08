<script>
  import gql from 'graphql-tag'
  import { default as FileComp } from './File.vue'

  export default {
    extends: FileComp,

    setup() {
      return { ...FileComp.setup() }
    }
  }
</script>

<template>
  <v-row>
    <v-col cols="12" md="6">
      <div class="files" :class="{readonly: readonly}">
        <div v-if="file.id" class="file" @click="open(file)" :title="$gettext('Edit file')">
          <v-progress-linear v-if="file.uploading"
            color="primary"
            height="5"
            indeterminate
            rounded
          />
          <video v-if="file.path"
            :src="url(file.path)"
            :draggable="false"
            controls
          />

          <v-btn v-if="!readonly"
            @click.stop="menu = !menu"
            :title="$gettext('Open menu')"
            icon="mdi-dots-vertical"
            class="btn-overlay"
            variant="flat"
          />
          <v-menu v-if="menu">
            <template v-slot:activator="{ props }">
              <div class="menu-overlay">
                <v-btn
                  @click.stop="open(file)"
                  :title="$gettext('Edit file')"
                  icon="mdi-pencil"
                  variant="flat"
                />
                <v-btn
                  @click.stop="remove()"
                  :title="$gettext('Remove file')"
                  icon="mdi-trash-can"
                  variant="flat"
                />
              </div>
            </template>
          </v-menu>
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
            :title="$gettext('Upload file')"
            icon="mdi-upload"
            variant="flat">
            <v-file-input
              v-model="selected"
              @update:modelValue="add($event)"
              :accept="config.accept || 'video/*'"
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
        <v-col cols="12" md="9">{{ (new Date(file.updated_at)).toLocaleString() }}</v-col>
      </v-row>
    </v-col>
  </v-row>

  <Teleport to="body">
    <FileDialog v-model="vfiles" @add="handle($event); vfiles = false" :filter="{mime: 'video/'}" />
  </Teleport>

  <Teleport to="body">
    <FileUrlDialog v-model="vurls" @add="select($event); vurls = false" mime="video/" />
  </Teleport>
</template>

<style scoped>
  .files video {
    max-width: 100%;
    max-height: 200px;
  }
</style>
