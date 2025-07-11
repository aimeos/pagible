<script>
  import gql from 'graphql-tag'
  import { useAppStore, useMessageStore } from '../stores'

  export default {
    props: {
      'modelValue': {type: Boolean, required: true},
      'multiple':  {type: Boolean, required: false},
      'mime': {type: String, default: ''}
    },

    emits: ['update:modelValue', 'add'],

    setup() {
      const messages = useMessageStore()
      const app = useAppStore()
      return { app, messages }
    },

    data() {
      return {
        errors: [],
        input: '',
        items: {},
        loading: false,
      }
    },

    methods: {
      add() {
        const promises = []
        const items = Object.values(this.items)

        if(!items.length) {
          return
        }

        this.loading = true

        items.forEach(item => {
          promises.push(this.$apollo.mutate({
            mutation: gql`mutation($input: FileInput) {
              addFile(input: $input) {
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
                path: item.path,
                name: item.name,
              }
            }
          }).then(response => {
            if(response.errors) {
              throw response.errors
            }

            Object.assign(item, response.data.addFile, {previews: JSON.parse(response.data.addFile.previews || '{}')})
          }).catch(error => {
            this.$log('Error adding file:', item, error)
          }).finally(() => {
            this.loading = false
          }))
        })

        Promise.all(promises).then(() => {
          this.$emit('update:modelValue', false)
          this.$emit('add', items)
          this.input = ''
          this.items = {}
        })
      },


      remove(url) {
        delete this.items[url]
      },


      size(val) {
        if(!val) {
          return ''
        }

        if(val < 1024) {
          return `${val} B`
        } else if(val < 1024 * 1024) {
          return `${(val / 1024).toFixed(2)} KB`
        } else if(val < 1024 * 1024 * 1024) {
          return `${(val / (1024 * 1024)).toFixed(2)} MB`
        } else {
          return `${(val / (1024 * 1024 * 1024)).toFixed(2)} GB`
        }
      },


      update() {
        const urls = this.input.split('\n').map(url => url.trim()).filter(url => url && url.startsWith('http'))

        for(const url of Object.keys(this.items)) {
          if(!urls.includes(url)) {
            delete this.items[url]
          }
        }

        urls.forEach(url => {
          if(this.items[url]) {
            return
          }

          fetch(this.app.urlproxy.replace(':url', encodeURIComponent(url)), {
              credentials: 'include',
              method: 'HEAD'
          }).then(response => {
            if(!response.ok) {
              throw new Error(`Failed to fetch ${url}`, response)
            }

            if(response.headers?.get('Content-Type')?.startsWith(this.mime)) {
              this.items[url] = {
                path: url,
                mime: response.headers?.get('Content-Type'),
                size: parseInt(response.headers?.get('Content-Length')),
                name: response.headers?.get('Content-Disposition')?.split('filename=')[1] || url.split('/')?.pop(),
              }
            } else {
              this.errors = this.multiple
                ? [`At least one file is not of type "${this.mime}*"`]
                : [`The file is not of type "${this.mime}*"`]
            }
          }).catch(error => {
            this.$log(`Error fetching ${url}`, error)
          })
        })
      }
    }
  }
</script>

<template>
  <v-dialog :modelValue="modelValue" max-width="1200" scrollable>
    <v-card>
      <template v-slot:append>
        <v-btn v-if="Object.keys(items).length" variant="outlined" @click="add()">
          {{ multiple ? 'Add files' : 'Add file' }}
        </v-btn>
        <v-btn icon="mdi-close" variant="flat" @click="$emit('update:modelValue', false)"></v-btn>
      </template>
      <template v-slot:title>
        Add files by URL
      </template>

      <v-progress-linear v-if="loading"
        color="primary"
        height="2"
        indeterminate
        rounded
      ></v-progress-linear>
      <v-divider v-else></v-divider>

      <v-card-text>
        <v-textarea v-if="multiple" ref="input"
          v-model="input"
          @keyup.enter="update()"
          @click:appendInner="update()"
          @click:clear="errors = []"
          :error-messages="errors"
          :append-inner-icon="input ? 'mdi-check' : ''"
          placeholder="Enter one URL per line"
          variant="underlined"
          autofocus
          clearable
          auto-grow
          rows="3"
        ></v-textarea>
        <v-text-field v-else ref="input"
          v-model="input"
          @keyup.enter="update()"
          @click:appendInner="update()"
          @click:clear="errors = []"
          :error-messages="errors"
          :append-inner-icon="input ? 'mdi-check' : ''"
          placeholder="Enter URL"
          variant="underlined"
          autofocus
          clearable
        ></v-text-field>

        <v-list class="items grid">
          <v-list-item v-for="(item, url) in items" :key="url">
            <v-btn icon="mdi-delete" @click="remove(url)" class="btn-overlay" title="Remove file"></v-btn>

            <div class="item-preview" @click="$emit('select', item)">
              <img v-if="item.mime?.startsWith('image/')" :src="item.path">
              <video v-else-if="item.mime?.startsWith('video/')" preload="metadata" controls :src="item.path"></video>
              <audio v-else-if="item.mime?.startsWith('audio/')" preload="metadata" controls :src="item.path"></audio>
              <a v-else :href="item.path" target="_blank">{{ item.path }}</a>
            </div>

            <div class="item-content" @click="$emit('select', item)">
              <div class="item-text">
                <span class="item-title">{{ item.name }}</span>
                <div class="item-mime item-subtitle">{{ item.mime }}</div>
              </div>

              <div class="item-aux">
                <div class="item-size">Size: {{ size(item.size) }}</div>
              </div>
            </div>
          </v-list-item>
        </v-list>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped>
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
    display: flex;
    height: 180px;
    z-index: 1;
  }

  .items.grid .item-preview img {
    display: block;
  }
</style>
