<script>
  import gql from 'graphql-tag'
  import File from './File.vue'

  export default {
    extends: File,

    setup() {
      return { ...File.setup() }
    }
  }
</script>

<template>
  <v-row>
    <v-col cols="12" md="6">
      <div class="files" :class="{readonly: readonly}">
        <div v-if="file.id" class="file" @click="open(file)" :title="$gettext('Edit')">
          <v-progress-linear v-if="file.uploading"
            color="primary"
            height="5"
            indeterminate
            rounded
          />
          <audio v-if="file.path"
            :src="url(file.path)"
            :draggable="false"
            controls
          />

          <v-menu v-if="file.id && !readonly">
            <template v-slot:activator="{ props }">
              <v-btn v-bind="props"
                :title="$gettext('Open menu')"
                icon="mdi-dots-vertical"
                class="btn-overlay"
                variant="text"
                elevation="0"
              />
            </template>
            <v-list>
              <v-list-item v-if="auth.can('file:view')">
                <v-btn
                  @click="open(file)"
                  prepend-icon="mdi-pencil"
                  variant="text"
                  elevation="0">
                  {{ $gettext('Edit') }}
                </v-btn>
              </v-list-item>
              <v-list-item>
                <v-btn
                  @click="remove()"
                  prepend-icon="mdi-trash-can"
                  variant="text"
                  elevation="0">
                  {{ $gettext('Remove') }}
                </v-btn>
              </v-list-item>
            </v-list>
          </v-menu>
        </div>

        <div v-else-if="!readonly" class="file">
          <v-btn v-if="auth.can('file:view')"
            @click="vfiles = true"
            :title="$gettext('Add file')"
            icon="mdi-button-cursor"
            variant="text"
            elevation="0"
          ></v-btn>
          <v-btn
            @click="vurls = true"
            :title="$gettext('Add file from URL')"
            icon="mdi-link-variant-plus"
            variant="text"
            elevation="0"
          ></v-btn>
          <v-btn
            :title="$gettext('Upload file')"
            icon="mdi-upload"
            variant="text"
            elevation="0">
            <v-file-input
              v-model="selected"
              @update:modelValue="add($event)"
              :accept="config.accept || 'audio/*'"
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
    <FileDialog v-model="vfiles" @add="handle($event); vfiles = false" :filter="{mime: 'audio/'}" />
  </Teleport>

  <Teleport to="body">
    <FileUrlDialog v-model="vurls" @add="select($event); vurls = false" mime="audio/" />
  </Teleport>
</template>

<style scoped>
</style>
