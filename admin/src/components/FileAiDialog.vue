/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

<script>
  import gql from 'graphql-tag'
  import FileListItems from './FileListItems.vue'
  import { useAppStore, useAuthStore, useMessageStore } from '../stores'
  import { recording } from '../audio'

  export default {
    components: {
      FileListItems
    },

    props: {
      'modelValue': {type: Boolean, required: true},
      'context': {type: [Object, null], default: null},
      'files': {type: Array, default: () => []},
    },

    emits: ['update:modelValue', 'add'],

    inject: ['base64ToBlob', 'transcribe', 'url'],

    setup() {
      const messages = useMessageStore()
      const auth = useAuthStore()
      const app = useAppStore()

      return { app, auth, messages }
    },

    data() {
      return {
        audio: null,
        chat: '',
        items: [],
        errors: [],
        used: [],
        loading: false,
        dictating: false,
      }
    },

    beforeUpdate() {
      this.chat = [this.context?.title, this.context?.text, this.context?.description].filter(Boolean).join("\n")
      this.used = this.files || []
    },

    unmounted() {
      this.items.forEach(item => {
        if(item.path.startsWith('blob:')) {
          URL.revokeObjectURL(item.path)
        }
      })

      this.used = []
      this.items = []
    },


    methods: {
      add(item) {
        if(!item.path.startsWith('blob:')) {
          this.$emit('add', [item])
          return
        }

        this.loading = true

        fetch(this.url(item.path, true)).then(response => {
          return response.blob()
        }).then(blob => {
          const filename = 'ai-image_' + (new Date()).toISOString().replace(/[^0-9]/g, '') + '.png'

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
          this.$refs.filelist.invalidate()
          this.$emit('add', [item])
        }).catch(error => {
          this.messages.add(this.$gettext(`Error adding file %{path}`, {path: item?.path}) + ":\n" + error, 'error')
          this.$log(`FileAiDialog::add(): Error adding file`, error)
        }).finally(() => {
          this.loading = false
        })
      },


      create() {
        if(!this.auth.can('image:imagine')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        if(!this.chat?.trim() || this.loading) {
          return
        }

        this.loading = true
        this.original = this.chat

        this.$apollo.mutate({
          mutation: gql`mutation($prompt: String!, $context: String, $files: [String!]) {
            imagine(prompt: $prompt, context: $context, files: $files)
          }`,
          variables: {
            prompt: this.chat,
            context: this.context ? "Context in JSON format:\n" + JSON.stringify(this.context) : '',
            files: this.used.map(item => item.id),
          }
        }).then(response => {
          if(response.errors) {
            throw response.errors
          }

          if(response.data.imagine) {
              this.items.unshift({
                path: URL.createObjectURL(this.base64ToBlob(response.data.imagine)),
                name: this.chat.slice(0, this.chat.length > 250 ? this.chat.lastIndexOf(' ', 250) : 250),
                mime: 'image/png'
              })
          }
        }).catch(error => {
          this.messages.add(this.$gettext('Error creating file') + ":\n" + error, 'error')
          this.$log(`FileAiDialog::create(): Error creating file`, error)
        }).finally(() => {
          this.loading = false
        })
      },


      record() {
        if(!this.audio) {
          return this.audio = recording().start()
        }

        this.audio.then(rec => {
          this.dictating = true
          this.audio = null

          rec.stop().then(buffer => {
            this.transcribe(buffer).then(transcription => {
              this.chat = transcription.asText()
            }).finally(() => {
              this.dictating = false
            })
          })
        })
      },


      remove(idx) {
        this.items.splice(idx, 1)
      },


      removeUsed(idx) {
        this.used.splice(idx, 1)
      },


      use(item) {
        if(!this.used.find(entry => entry.path === item.path)) {
          this.used.push(item)
        }
      }
    }
  }
</script>

<template>
  <v-dialog :modelValue="modelValue" @afterLeave="$emit('update:modelValue', false)" max-width="1200" scrollable>
    <v-card :loading="loading ? 'primary' : false">
      <template v-slot:append>
        <v-btn v-if="auth.can('audio:transcribe')"
          @click="record()"
          :class="{dictating: audio}"
          :icon="audio ? 'mdi-microphone-outline' : 'mdi-microphone'"
          :title="$gettext('Dictate')"
          :loading="dictating"
          variant="text"
        />
        <v-btn
          @click="$emit('update:modelValue', false)"
          :title="$gettext('Close')"
          icon="mdi-close"
          variant="text"
        />
      </template>
      <template v-slot:title>
        {{ $gettext('Create image') }}
      </template>

      <v-card-text>
        <v-textarea
          v-model="chat"
          :label="$gettext('Describe the image content')"
          variant="underlined"
          autofocus
          clearable
        ></v-textarea>

        <v-btn
          :loading="loading"
          :disabled="!chat"
          @click="create()"
          variant="outlined"
          class="create">
          {{ $gettext('New image') }}
        </v-btn>

        <div v-if="items.length">
          <v-tabs>
            <v-tab>{{ $gettext('Current images') }}</v-tab>
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

        <div v-if="used.length">
          <v-tabs>
            <v-tab>{{ $gettext('Images used') }}</v-tab>
          </v-tabs>
          <v-list class="items grid">
            <v-list-item v-for="(item, idx) in used" :key="idx">
              <v-btn icon="mdi-delete" @click="removeUsed(idx)" class="btn-overlay" :title="$gettext('Remove')"></v-btn>

              <div class="item-preview">
                <img :src="url(item.path)">
              </div>
            </v-list-item>
          </v-list>
        </div>

        <v-tabs>
          <v-tab>{{ $gettext('Select images') }}</v-tab>
        </v-tabs>
        <FileListItems ref="filelist" :filter="{mime: 'image/'}" @select="use($event)" />
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
