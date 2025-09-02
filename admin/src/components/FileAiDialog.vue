<script>
  import gql from 'graphql-tag'
  import FileListItems from './FileListItems.vue'
  import { useAppStore, useMessageStore } from '../stores'

  export default {
    components: {
      FileListItems
    },

    props: {
      'modelValue': {type: Boolean, required: true},
      'context': {type: [Object, null], default: null},
    },

    emits: ['update:modelValue', 'add'],

    inject: ['slugify', 'url'],

    setup() {
      const messages = useMessageStore()
      const app = useAppStore()

      return { app, messages }
    },

    data() {
      return {
        input: '',
        items: [],
        errors: [],
        similar: [],
        loading: false,
      }
    },

    beforeUpdate() {
      this.input = [this.context?.title, this.context?.text, this.context?.description].filter(Boolean).join("\n")
    },

    unmounted() {
      this.items.forEach(item => {
        if(item.path.startsWith('blob:')) {
          URL.revokeObjectURL(item.path)
        }
      })

      this.similar = []
      this.items = []
    },


    methods: {
      add(item) {
        if(!item.path.startsWith('blob:')) {
          this.$emit('add', [item])
          return
        }

        this.loading = true

        fetch(item.path).then(response => {
          return response.blob()
        }).then(blob => {
          const name = item.name.slice(0, item.name.length > 50 ? item.name.lastIndexOf(' ', 50) : 50) || 'ai-image'
          const filename = this.slugify(name) + '_' + (new Date()).toISOString().replace(/[^0-9]/g, '') + '.png'

          return this.$apollo.mutate({
            mutation: gql`mutation($input: FileInput, $file: Upload) {
              addFile(input: $input, file: $file) {
                id
                mime
                name
                path
                previews
                updated_at
                editor
              }
            }`,
            variables: {
              input: {
                name: item.name,
              },
              file: new File([blob], filename, { type: item.mime })
            },
            context: {
              hasUpload: true
            }
          })
        }).then(response => {
          if(response.errors) {
            throw response.errors
          }

          Object.assign(item, response.data.addFile, {previews: JSON.parse(response.data.addFile.previews || '{}')})
          // this.$refs.filelist.invalidate()
          this.$emit('add', [item])
        }).catch(error => {
          this.messages.add(this.$gettext(`Error adding file %{path}`, {path: item?.path}) + ":\n" + error, 'error')
          this.$log(`FileAiDialog::add(): Error adding file`, error)
        }).finally(() => {
          this.loading = false
        })
      },


      base64ToBlob(base64, mimeType = 'image/png') {
        if(!base64) {
          return null
        }

        const binary = atob(base64);
        const byteArray = new Uint8Array(binary.length);

        for(let i = 0; i < binary.length; i++) {
          byteArray[i] = binary.charCodeAt(i);
        }

        return new Blob([byteArray], { type: mimeType });
      },


      create() {
        if(!this.input || this.loading) {
          return
        }

        this.loading = true
        this.original = this.input

        this.$apollo.mutate({
          mutation: gql`mutation($prompt: String!, $context: String, $files: [String!]) {
            imagine(prompt: $prompt, context: $context, files: $files)
          }`,
          variables: {
            prompt: "Create as suitable image for:\n" + this.input,
            context: this.context ? "Context in JSON format:\n" + JSON.stringify(this.context) : '',
            files: this.similar.map(item => item.path),
          }
        }).then(response => {
          if(response.errors) {
            throw response.errors
          }

          const name = this.input
          const list = response.data.imagine
          this.input = list.shift() || this.input

          list.forEach(base64 => {
              this.items.unshift({
                path: URL.createObjectURL(this.base64ToBlob(base64)),
                name: name.slice(0, name.length > 100 ? name.lastIndexOf(' ', 100) : 100),
                mime: 'image/png'
              })
          })
        }).catch(error => {
          this.messages.add(this.$gettext('Error creating file') + ":\n" + error, 'error')
          this.$log(`FileAiDialog::create(): Error creating file`, error)
        }).finally(() => {
          this.loading = false
        })
      },


      remove(idx) {
        this.items.splice(idx, 1)
      },


      removeSimilar(idx) {
        this.similar.splice(idx, 1)
      },


      use(item) {
        if(!this.similar.find(entry => entry.path === item.path)) {
          this.similar.push(item)
        }
      }
    }
  }
</script>

<template>
  <v-dialog :modelValue="modelValue" @afterLeave="$emit('update:modelValue', false)" max-width="1200" scrollable>
    <v-card :loading="loading ? 'primary' : false">
      <template v-slot:append>
        <v-btn
          @click="$emit('update:modelValue', false)"
          :title="$gettext('Close')"
          icon="mdi-close"
          variant="text"
          elevation="0"
        />
      </template>
      <template v-slot:title>
        {{ $gettext('Create image') }}
      </template>

      <v-card-text>
        <v-textarea
          v-model="input"
          :label="$gettext('Describe the image')"
          variant="underlined"
          autofocus
          clearable
        ></v-textarea>

        <v-btn
          :loading="loading ? 'primary' : false"
          :disabled="!input || loading"
          @click="create()"
          variant="outlined"
          class="create">
          {{ $gettext('Create image') }}
        </v-btn>

        <div v-if="items.length">
          <v-tabs>
            <v-tab>{{ $gettext('Generated images') }}</v-tab>
          </v-tabs>
          <v-list class="items grid">
            <v-list-item v-for="(item, idx) in items" :key="idx">
              <v-btn
                @click="remove(idx)"
                :title="$gettext('Remove')"
                class="btn-overlay"
                icon="mdi-delete"
              />

              <div class="item-preview" @click="add(item)">
                <img :src="url(item.path)">
              </div>
            </v-list-item>
          </v-list>
        </div>

        <!-- Not supported by Prism API yet -->
        <!--div v-if="similar.length">
          <v-tabs>
            <v-tab>{{ $gettext('Use images of this style') }}</v-tab>
          </v-tabs>
          <v-list class="items grid">
            <v-list-item v-for="(item, idx) in similar" :key="idx">
              <v-btn icon="mdi-delete" @click="removeSimilar(idx)" class="btn-overlay" :title="$gettext('Remove')"></v-btn>

              <div class="item-preview">
                <img :src="url(item.path)">
              </div>
            </v-list-item>
          </v-list>
        </div>

        <v-tabs>
          <v-tab>{{ $gettext('Select similar images') }}</v-tab>
        </v-tabs>
        <FileListItems ref="filelist" :filter="{mime: 'image/'}" @select="use($event)" /-->
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped>
  .v-tabs {
    margin-top: 40px;
  }

  .v-btn.v-tab {
    background-color: rgb(var(--v-theme-background));
    width: 100%;
  }

  .v-btn.create {
    display: block;
    margin: auto;
  }

  .items.grid {
    grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
    display: grid;
    gap: 16px;
  }

  .items.grid .v-list-item {
    grid-template-rows: max-content;
    border: 1px solid rgb(var(--v-theme-primary));
  }

  .items.grid .item-preview {
    justify-content: center;
    display: flex;
    height: 180px;
  }

  .items.grid .item-preview img {
    display: block;
  }
</style>
