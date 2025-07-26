<script>
  import gql from 'graphql-tag'
  import FileListItems from './FileListItems.vue'
  import { useAppStore } from '../stores'

  export default {
    components: {
      FileListItems
    },

    props: {
      'modelValue': {type: Boolean, required: true},
      'context': {type: [Object, null], default: null},
    },

    emits: ['update:modelValue', 'add'],

    setup() {
      const app = useAppStore()
      return { app }
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

    methods: {
      add(item) {
        this.loading = true

        fetch(this.app.urlproxy.replace(':url', encodeURIComponent(item.path)), {
            credentials: 'include',
            method: 'GET'
        }).then(response => {
          if(!response.ok) {
            throw new Error(`Failed to fetch ${item.path}`, response)
          }

          return response.blob()
        }).then(blob => {
          const filename = item.name.replaceAll(' ', '-') + '_' + (new Date()).toISOString().replace(/[^0-9]/g, '') + '.png'

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
          this.$emit('add', [item])
        }).catch(error => {
          this.$log(`FileAiDialog::add(): Error adding file for ${item.path}`, error)
        }).finally(() => {
          this.loading = false
        })
      },


      create() {
        if(!this.input || this.loading) {
          return
        }

        this.loading = true
        this.original = this.input

        this.$apollo.mutate({
          mutation: gql`mutation($prompt: String!, $context: String, $images: [String!]) {
            imagine(prompt: $prompt, context: $context, images: $images)
          }`,
          variables: {
            prompt: this.input,
            context: this.context ? $gettext('Context in JSON format') + ":\n" + JSON.stringify(this.context) : '',
            images: this.similar.map(item => item.path),
          }
        }).then(response => {
          if(response.errors) {
            throw response.errors
          }

          const name = this.input
          const list = response.data.imagine
          this.input = list.shift() || this.input

          list.forEach(url => {
            fetch(this.app.urlproxy.replace(':url', encodeURIComponent(url)), {
                credentials: 'include',
                method: 'HEAD'
            }).then(response => {
              if(!response.ok) {
                throw new Error(`Failed to fetch ${url}`, response)
              }

              this.items.unshift({
                path: url,
                mime: response.headers?.get('Content-Type'),
                name: name.slice(0, name.lastIndexOf(' ', 100))
              })
            }).catch(error => {
              this.$log(`FileAiDialog::create(): Error fetching ${url}`, error)
            })
          })
        }).catch(error => {
          this.$log(`FileAiDialog::create(): Error creating files`, error)
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


      url(path) {
        if(path.startsWith('http') || path.startsWith('blob:')) {
          return path
        }
        return this.app.urlfile.replace(/\/+$/g, '') + '/' + path
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
  <v-dialog :modelValue="modelValue" max-width="1200" scrollable>
    <v-card :loading="loading ? 'primary' : false">
      <template v-slot:append>
        <v-btn
          @click="$emit('update:modelValue', false)"
          :title="$gettext('Close')"
          icon="mdi-close"
          variant="flat"
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
          color="primary"
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

        <div v-if="similar.length">
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
        <FileListItems :filter="{mime: 'image/'}" @select="use($event)" />
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
