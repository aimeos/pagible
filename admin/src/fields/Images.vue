<script>
  import gql from 'graphql-tag'
  import { VueDraggable } from 'vue-draggable-plus'
  import { useAppStore, useAuthStore } from '../stores'
  import FileAiDialog from '../components/FileAiDialog.vue'
  import FileListItems from '../components/FileListItems.vue'
  import FileUrlDialog from '../components/FileUrlDialog.vue'
  import FileDialog from '../components/FileDialog.vue'
  import FileDetail from '../views/FileDetail.vue'

  export default {
    components: {
      FileDetail,
      FileDialog,
      FileAiDialog,
      FileUrlDialog,
      FileListItems,
      VueDraggable
    },

    inject: ['openView', 'url', 'srcset'],

    props: {
      'modelValue': {type: Array, default: () => []},
      'config': {type: Object, default: () => {}},
      'assets': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    emits: ['update:modelValue', 'error', 'addFile', 'removeFile'],

    setup() {
      const auth = useAuthStore()
      const app = useAppStore()

      return { app, auth }
    },

    data() {
      return {
        images: [],
        index: Math.floor(Math.random() * 100000),
        selected: null,
        vcreate: false,
        vfiles: false,
        vurls: false,
      }
    },

    unmounted() {
      this.images.forEach(item => {
        if(item.path?.startsWith('blob:')) {
          URL.revokeObjectURL(item.path)
        }
      })
    },

    methods: {
      add(files) {
        if(!this.auth.can('file:add')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const promises = []

        if(!files?.length) {
          return
        }

        Array.from(files).forEach(file => {
          const path = URL.createObjectURL(file)
          const idx = this.images.length

          this.images[idx] = {path: path, uploading: true}

          const promise = this.$apollo.mutate({
            mutation: gql`mutation($file: Upload!) {
              addFile(file: $file) {
                id
                mime
                name
                path
                previews
              }
            }`,
            variables: {
              file: file
            },
            context: {
              hasUpload: true
            }
          }).then(response => {
            if(response.errors) {
              throw response.errors
            }

            const data = response.data?.addFile || {}
            data.previews = JSON.parse(data.previews) || {}
            delete data.__typename

            return new Promise((resolve, reject) => {
              const image = new Image()
              image.onload = resolve
              image.onerror = reject
              image.src = this.url(Object.values(data.previews)[0])
            }).then(() => {
              this.images[idx] = data
              this.$emit('addFile', data)
              URL.revokeObjectURL(path)
            })
          }).catch(error => {
            this.messages.add(this.$gettext(`Error adding file %{path}`, {path: file.name}), 'error')
            this.$log(`Images::addFile(): Error adding file`, ev, error)
          })

          promises.push(promise)
        })

        Promise.all(promises).then(() => {
          this.$emit('update:modelValue', this.images.map(item => ({id: item.id, type: 'file'})))
        })

        this.selected = null
      },


      change() {
        this.$emit('update:modelValue', this.images.map(item => ({id: item.id, type: 'file'})))
      },


      open(item) {
        this.openView(FileDetail, {item: item})
      },


      remove(idx) {
        if(this.images[idx]?.id) {
          this.$emit('removeFile', this.images[idx].id)
        }

        this.images.splice(idx, 1)
        this.$emit('update:modelValue', this.images.map(item => ({id: item.id, type: 'file'})))
        this.validate()
      },


      select(items) {
        if(!Array.isArray(items)) {
          items = [items]
        }

        items.forEach(item => {
          this.images.push(item)
          this.$emit('addFile', item)
        })

        this.$emit('update:modelValue', this.images.map(item => ({id: item.id, type: 'file'})))
        this.vfiles = false
        this.vurls = false
        this.validate()
      },


      async validate() {
        const result = this.images.length >= (this.config.min ?? 0) && this.images.length <= (this.config.max ?? 1000)

        this.$emit('error', !result)
        return await result
      }
    },

    watch: {
      modelValue: {
        immediate: true,
        handler(list) {
          if(!this.images.length) {
            for(const entry of (list || [])) {
              if(this.assets[entry.id]) {
                this.images.push(this.assets[entry.id])
              }
            }
          }
        }
      }
    }
  }
</script>

<template>
  <VueDraggable v-model="images" :disabled="readonly" @update="change()" draggable=".image" group="images" class="images" animation="500">

    <div v-for="(item, idx) in images" :key="idx" :class="{readonly: readonly}" class="image" @click="open(item)">
      <v-progress-linear v-if="item.uploading"
        color="primary"
        height="5"
        indeterminate
        rounded
      />
      <v-img v-if="item.path"
        :srcset="srcset(item.previews)"
        :src="url(item.path)"
        draggable="false"
      />
      <v-btn v-if="!readonly && item.id"
        @click.stop="remove(idx)"
        :title="$gettext('Remove file')"
        icon="mdi-trash-can"
        class="btn-overlay"
          variant="flat"
      />
    </div>
    <div v-if="!readonly" class="add">
      <div class="icon-group">
        <v-btn v-if="auth.can('file:view')"
          @click="vfiles = true"
          :title="$gettext('Add files')"
          icon="mdi-button-cursor"
          variant="flat"
        />
        <v-btn
          @click="vurls = true"
          :title="$gettext('Add files from URLs')"
          icon="mdi-link-variant-plus"
          variant="flat"
        />
      </div>
      <div class="icon-group">
        <v-btn
          @click="vcreate = true"
          :title="$gettext('Create file')"
          icon="mdi-creation"
          variant="flat"
        />
        <v-btn
          :title="$gettext('Add files')"
          icon="mdi-upload"
          variant="flat"
          ><v-file-input
            v-model="selected"
            @update:modelValue="add($event)"
            :accept="config.accept || 'image/*'"
            :hide-input="true"
            prepend-icon="mdi-upload"
            multiple
          />
        </v-btn>
      </div>
    </div>
  </VueDraggable>

  <Teleport to="body">
    <FileDialog v-model="vfiles" @add="select($event)" :filter="{mime: 'image/'}" grid />
  </Teleport>

  <Teleport to="body">
    <FileAiDialog v-model="vcreate" @add="select($event); vcreate = false" :context="context" />
  </Teleport>

  <Teleport to="body">
    <FileUrlDialog v-model="vurls" @add="select($event)" mime="image/" multiple />
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
    border: 1px solid #808080;
    border-radius: 4px;
    position: relative;
    height: 180px;
    width: 180px;
    margin: 1px;
  }

  .images .add {
    border: 1px dashed #808080;
    flex-flow: column;
    flex-wrap: wrap;
  }

  .v-progress-linear {
    position: absolute;
    z-index: 1;
  }
</style>
