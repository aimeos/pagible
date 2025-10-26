/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

<script>
  import gql from 'graphql-tag'
  import Cropper from 'cropperjs'
  import 'cropperjs/dist/cropper.css'
  import FileAiDialog from './FileAiDialog.vue'
  import { useAppStore, useAuthStore, useLanguageStore, useMessageStore, useSideStore } from '../stores'
  import { recording } from '../audio'


  export default {
    components: {
      FileAiDialog
    },

    props: {
      'item': {type: Object, required: true},
    },

    emits: ['update:item', 'update:file', 'error'],

    inject: ['compose', 'locales', 'transcribe', 'translate', 'txlocales', 'url'],

    data() {
      return {
        vedit: false,
        transcribing: false,
        translating: false,
        dictating: false,
        composing: false,
        cropping: false,
        covering: false,
        tabtrans: null,
        tabdesc: null,
        cropLabel: null,
        cropper: null,
        audio: null,
        images: [],
        scaleX: 1,
        scaleY: 1,
        menu: {},
      }
    },

    setup() {
      const languages = useLanguageStore()
      const messages = useMessageStore()
      const side = useSideStore()
      const auth = useAuthStore()
      const app = useAppStore()

      return { app, auth, languages, messages, side }
    },

    mounted() {
      this.cropper = this.init()
    },

    beforeUnmount() {
      if(this.cropper) {
        this.cropper.destroy()
      }

      this.images.forEach(img => {
        URL.revokeObjectURL(img.url)
      })
    },

    computed: {
      desclangs() {
        return this.languages.available.concat(Object.keys(this.item.description || {})).filter((v, idx, self) => {
          return self.indexOf(v) === idx
        })
      },


      ratio() {
        if(!this.cropper) {
          return NaN
        }

        const imageData = this.cropper.getImageData()
        return imageData.naturalWidth / imageData.naturalHeight
      },


      readonly() {
        return !this.auth.can('file:save')
      }
    },

    methods: {
      addCover() {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        const self = this
        const video = this.$refs.video

        if(!video) {
          return this.messages.add(this.$gettext('No video element found'), 'error')
        }

        const filename = this.item.path.replace(/\.[A-Za-z0-9]+$/, '.png').split('/').pop()
        const canvas = document.createElement('canvas')
        const context = canvas.getContext('2d')

        canvas.width = video.videoWidth
        canvas.height = video.videoHeight
        context.drawImage(video, 0, 0, video.videoWidth, video.videoHeight)

        canvas.toBlob(function(blob) {
          const file = new File([blob], filename, {type: 'image/png'})

          self.covering = true

          self.$apollo.mutate({
            mutation: gql`mutation($id: ID!, $preview: Upload) {
              saveFile(id: $id, input: {}, preview: $preview) {
                id
                latest {
                  data
                  created_at
                }
              }
            }`,
            variables: {
              id: self.item.id,
              preview: file
            },
            context: {
              hasUpload: true
            }
          }).then(response => {
            if(response.errors) {
              throw response.errors
            }

            const latest = response.data?.saveFile?.latest

            if(latest) {
              self.item.previews = JSON.parse(latest.data || '{}')?.previews || {}
              self.item.updated_at = latest.created_at
            }
          }).catch(error => {
            self.messages.add(self.$gettext('Error saving video cover') + ":\n" + error, 'error')
            self.$log(`FileDetailItem::addCover(): Error saving video cover`, error)
          }).finally(() => {
            self.covering = false
          })
        }, 'image/png', 1)
      },


      aspect(ratio) {
        this.cropper.setAspectRatio(ratio)
        this.cropper.setDragMode('crop')
        this.cropping = true

        this.$nextTick(() => {
          const cropBox = this.cropper.cropper.querySelector(".cropper-crop-box");

          if (cropBox && !this.cropLabel) {
            const label = document.createElement("div");
            label.className = "crop-label";
            cropBox.appendChild(label);
            this.cropLabel = label;
          }
        });
      },


      composeText() {
        const lang = this.desclangs.shift() || this.item.lang || 'en'
        const prompt = `Summarize the content of the file in a few words in plain text format for a title tag in the language with the ISO code "${lang}":`

        this.composing = true

        this.compose(prompt, null, [this.item.id]).then(result => {
          this.update('description', Object.assign(this.item.description || {}, {[lang]: result}))
        }).finally(() => {
          this.composing = false
        })
      },


      crop() {
        this.cropping = false
        this.cropper.setDragMode('none')
        this.cropper.getCroppedCanvas().toBlob(blob => {
          this.$emit('update:file', blob)
        })
      },


      download() {
        this.cropper.getCroppedCanvas().toBlob(blob => {
          const url = URL.createObjectURL(blob)
          const link = document.createElement('a')

          link.href = url
          link.download = this.item.name || 'download'
          link.click()

          URL.revokeObjectURL(url)
        })
      },


      flipX() {
        this.scaleX = -this.scaleX
        this.cropper.scaleX(this.scaleX)
        this.updateFile()
      },


      flipY() {
        this.scaleY = -this.scaleY
        this.cropper.scaleY(this.scaleY)
        this.updateFile()
      },


      init() {
        if(!this.readonly && this.item.mime?.startsWith('image/')) {
          if(this.cropper) {
            this.cropper.destroy()
            this.cropper = null
          }

          const self = this

          this.cropper = new Cropper(this.$refs.image, {
            aspectRatio: NaN,
            background: true,
            responsive: true,
            dragMode: 'none',
            movable: false,
            autoCrop: false,
            zoomable: false,
            zoomOnWheel: false,
            zoomOnTouch: false,
            touchDragZoom: false,
            checkCrossOrigin: false,
            checkOrientation: false,
            viewMode: 1,
            crop(event) {
              if (!self.cropLabel) return;
              const { width, height } = event.detail;
              self.cropLabel.textContent = `${Math.round(width)} × ${Math.round(height)}`;
            },
          })
        }
      },


      record() {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        if(!this.audio) {
          return this.audio = recording().start()
        }

        this.audio.then(rec => {
          this.dictating = true
          this.audio = null

          rec.stop().then(buffer => {
            this.transcribe(buffer).then(transcription => {
              const lang = this.desclangs[0] || this.item.lang || 'en'
              this.update('description', Object.assign(this.item.description || {}, {[lang]: transcription.asText()}))
            }).finally(() => {
              this.dictating = false
            })
          })
        })
      },


      removeCover() {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        this.covering = true
        this.item.previews = {}

        this.$apollo.mutate({
          mutation: gql`mutation($id: ID!, $preview: Boolean) {
            saveFile(id: $id, input: {}, preview: $preview) {
              id
              latest {
                data
                created_at
              }
            }
          }`,
          variables: {
            id: this.item.id,
            preview: false
          }
        }).then(response => {
          if(response.errors) {
            throw response.errors
          }

          const latest = response.data?.saveFile?.latest

          if(latest) {
            this.item.previews = JSON.parse(latest.data || '{}')?.previews || {}
            this.item.updated_at = latest.created_at
          }
        }).catch(error => {
          this.messages.add(this.$gettext('Error removing video cover') + ":\n" + error, 'error')
          this.$log(`FileDetailItem::removeCover(): Error removing video cover`, error)
        }).finally(() => {
          this.covering = false
        })
      },


      replace(blob, idx = null) {
        let file = null

        if(blob) {
          const image = URL.createObjectURL(blob)

          this.cropper.replace(image)

          if(idx !== null) {
            this.images.unshift(...this.images.splice(idx, 1))
          } else {
            this.images.unshift({blob: blob, url: image})
          }

          this.images.splice(10).forEach(img => {
            URL.revokeObjectURL(img.url)
          })

          file = new File([blob], this.item.path.split('/').pop(), {type: 'image/png'})
        }

        this.$emit('update:file', file)
        this.reset()
      },


      reset() {
        this.cropping = false
        this.cropper.reset()
        this.cropper.clear()
        this.scaleX = 1
        this.scaleY = 1
      },


      rotate(deg) {
        this.cropper.rotate(deg)
        this.updateFile()

        this.$nextTick(() => {
          const container = this.cropper.getContainerData()
          const image = this.cropper.getImageData()
          let scaleX, scaleY

          if(Math.abs(Math.abs(image.rotate) - 180) === 90) {
            scaleX = container.width / image.naturalHeight
            scaleY = container.height / image.naturalWidth
          } else {
            scaleX = container.width / image.naturalWidth
            scaleY = container.height / image.naturalHeight
          }

          this.cropper.zoomTo(Math.min(scaleX, scaleY))
        });
      },


      transcribeFile() {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        if(!this.item.mime?.startsWith('audio/') && !this.item.mime?.startsWith('video/')) {
          return this.messages.add(this.$gettext('Transcription is only available for audio and video files'), 'error')
        }

        this.transcribing = true

        this.transcribe(this.item.path).then(transcription => {
          const lang = this.desclangs[0] || this.item.lang || 'en'
          this.update('transcription', Object.assign(this.item.transcription || {}, {[lang]: transcription.asText()}))
        }).finally(() => {
          this.transcribing = false
        })
      },


      translateText(map) {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        if(!map || typeof map !== 'object') {
          this.$log(`FileDetailItem::translateText(): Invalid map object`, map)
          return
        }

        const promises = []
        const [lang, text] = Object.entries(map || {}).find(([lang, text]) => {
          return text ? true : false
        })

        this.translating = true

        this.txlocales(lang).map(lang => lang.code).forEach(lang => {
          promises.push(this.translate(text, lang).then(result => {
            if(result[0]) {
              map[lang] = result[0]
            }
          }).catch(error => {
            this.$log(`FileDetailItem::translateText(): Error translating text`, error)
          }))
        })

        return Promise.all(promises).then(() => {
          this.$emit('update:item', this.item)
          this.translating = false
          return map
        })
      },


      translateVTT(map) {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        if(!map || typeof map !== 'object') {
          this.$log(`FileDetailItem::translateVTT(): Invalid map object`, map)
          return
        }

        const regex = /^\d{2}:\d{2}:\d{2}\.\d{3} --\> \d{2}:\d{2}:\d{2}\.\d{3}(?: .*)?$/;
        const texts = {...map}

        for(const [lang, text] of Object.entries(texts)) {
          if(text) {
            texts[lang] = text.split('\n')
              .map(line => (line.startsWith('WEBVTT') || regex.test(line)) ? `<x>${line}</x>` : line)
              .filter(line => line.trim() !== '')
              .join('');
          }
        }

        this.translateText(texts).then(texts => {
          for(const [lang, text] of Object.entries(texts)) {
            if(texts[lang]) {
              map[lang] = texts[lang].replaceAll(/\<x\>/g, '\n\n').replaceAll(/\<\/x\>/g, '\n').trim()
            }
          }

          this.$emit('update:item', this.item)
        }).catch(error => {
          this.$log(`FileDetailItem::translateVTT(): Error translating VTT`, error)
        })
      },


      update(what, value) {
        this.item[what] = value
        this.$emit('update:item', this.item)
      },


      updateFile() {
        if(!this.readonly) {
          this.cropper.getCroppedCanvas().toBlob(blob => {
            this.images.unshift({blob: blob, url: URL.createObjectURL(blob)})
            this.images.splice(10).forEach(img => {
              URL.revokeObjectURL(img.url)
            })

            this.$emit('update:file', new File([blob], this.item.path.split('/').pop(), {type: 'image/png'}))
          })
        }
      },


      uploadCover(ev) {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        const file = ev.target.files[0];

        if(!file) {
          return this.messages.add(this.$gettext('No file selected'), 'error')
        }

        this.covering = true

        this.$apollo.mutate({
          mutation: gql`mutation($id: ID!, $preview: Upload) {
            saveFile(id: $id, input: {}, preview: $preview) {
              id
              latest {
                data
                created_at
              }
            }
          }`,
          variables: {
            id: this.item.id,
            preview: file
          },
          context: {
            hasUpload: true
          }
        }).then(response => {
          if(response.errors) {
            throw response.errors
          }

          const latest = response.data?.saveFile?.latest

          if(latest) {
            this.item.previews = JSON.parse(latest.data || '{}')?.previews || {}
            this.item.updated_at = latest.created_at
          }
        }).catch(error => {
          this.messages.add(this.$gettext('Error uploading video cover') + ":\n" + error, 'error')
          this.$log(`FileDetailItem::uploadCover(): Error uploading video cover`, error)
        }).finally(() => {
          this.covering = false
        })
      },


      use(items) {
        if(!items?.length) {
          return
        }

        this.vedit = false
        this.item.path = items[0].path
        this.item.mime = items[0].mime

        this.cropper.replace(this.url(this.item.path, true))
        this.$emit('update:file', null)
        this.reset()
      },
    },

    watch: {
      item: function(item, old) {
        if(item.path !== old.path) {
          this.$nextTick(() => {
            this.init()
          })
        }
      }
    }
  }
</script>

<template>
  <v-container>
    <v-sheet class="scroll">
      <v-row>
        <v-col cols="12" md="6">
          <v-text-field ref="name"
            :readonly="readonly"
            :modelValue="item.name"
            @update:modelValue="update('name', $event)"
            variant="underlined"
            :label="$gettext('Name')"
            counter="255"
            maxlength="255"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="6">
          <v-select ref="lang"
            :items="locales(true)"
            :readonly="readonly"
            :modelValue="item.lang"
            @update:modelValue="update('lang', $event)"
            variant="underlined"
            :label="$gettext('Language')"
          ></v-select>
        </v-col>
      </v-row>
      <v-row>
        <v-col v-if="item" cols="12" class="preview">
          <div v-if="item.mime?.startsWith('image/')" ref="editorContainer" class="editor-container">
            <img ref="image" :src="url(item.path, true)" class="element" crossorigin="anonymous" />

            <div v-if="!readonly" class="toolbar">
              <div class="toolbar-group">
                <v-btn v-if="cropping"
                  @click="crop()"
                  :title="$gettext('Use cropped image')"
                  icon="mdi-image-check"
                  class="no-rtl"
                />
                <component v-else :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
                  v-model="menu['crop']"
                  transition="scale-transition"
                  location="end center"
                  max-width="300">

                  <template #activator="{ props }">
                    <v-btn
                      v-bind="props"
                      :title="$gettext('Crop image')"
                      icon="mdi-crop"
                      class="no-rtl"
                    />
                  </template>

                  <v-card>
                    <v-toolbar density="compact">
                      <v-toolbar-title>{{ $gettext('Crop image') }}</v-toolbar-title>
                      <v-btn icon="mdi-close" @click="menu['crop'] = false" />
                    </v-toolbar>

                    <v-list @click="menu['crop'] = false">
                      <v-list-item>
                        <v-btn prepend-icon="mdi-crop" class="no-rtl" variant="text" @click="aspect(ratio)">{{ $gettext('Original ratio') }}</v-btn>
                      </v-list-item>
                      <v-list-item>
                        <v-btn prepend-icon="mdi-crop" class="no-rtl" variant="text" @click="aspect(NaN)">{{ $gettext('No ratio') }}</v-btn>
                      </v-list-item>
                      <v-list-item>
                        <v-btn prepend-icon="mdi-crop" class="no-rtl" variant="text" @click="aspect(1)">{{ $gettext('Square') }}</v-btn>
                      </v-list-item>
                      <v-list-item>
                        <v-btn prepend-icon="mdi-crop" class="no-rtl" variant="text" @click="aspect(3/2)">3:2</v-btn>
                      </v-list-item>
                      <v-list-item>
                        <v-btn prepend-icon="mdi-crop" class="no-rtl" variant="text" @click="aspect(4/3)">4:3</v-btn>
                      </v-list-item>
                      <v-list-item>
                        <v-btn prepend-icon="mdi-crop" class="no-rtl" variant="text" @click="aspect(5/3)">5:3</v-btn>
                      </v-list-item>
                      <v-list-item>
                        <v-btn prepend-icon="mdi-crop" class="no-rtl" variant="text" @click="aspect(16/9)">16:9</v-btn>
                      </v-list-item>
                    </v-list>
                  </v-card>
                </component>

                <v-btn icon="mdi-image-edit" class="no-rtl" @click="vedit = true" :title="$gettext('Edit image')" />
              </div>
              <div class="toolbar-group">
                <v-btn icon="mdi-rotate-left" class="no-rtl" @click="rotate(-90)" :title="$gettext('Rotate counter-clockwise')" />
                <v-btn icon="mdi-rotate-right" class="no-rtl" @click="rotate(90)" :title="$gettext('Rotate clockwise')" />
              </div>
              <div class="toolbar-group">
                <v-btn icon="mdi-flip-horizontal" class="no-rtl" @click="flipX" :title="$gettext('Flip horizontally')" />
                <v-btn icon="mdi-flip-vertical" class="no-rtl" @click="flipY" :title="$gettext('Flip vertically')" />
              </div>
              <div class="toolbar-group">
                <v-btn icon="mdi-download" class="no-rtl" @click="download()" :title="$gettext('Download')" />

                <component :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
                  v-model="menu['undo']"
                  transition="scale-transition"
                  location="end center"
                  max-width="300">

                  <template #activator="{ props }">
                    <v-btn
                      v-bind="props"
                      :disabled="!images.length"
                      :title="$gettext('Undo')"
                      icon="mdi-history"
                      class="no-rtl"
                    />
                  </template>

                  <v-card>
                    <v-toolbar density="compact">
                      <v-toolbar-title>{{ $gettext('Undo') }}</v-toolbar-title>
                      <v-btn icon="mdi-close" @click="menu['undo'] = false" />
                    </v-toolbar>

                    <v-list @click="menu['undo'] = false">
                      <v-list-item v-for="(img, idx) in images.slice(1)" :key="idx">
                        <v-img :src="img.url" @click="replace(img.blob, idx+1)" />
                      </v-list-item>
                      <v-list-item>
                        <v-img :src="url(item.path)" @click="use([item])" />
                      </v-list-item>
                    </v-list>
                  </v-card>
                </component>
              </div>
            </div>
          </div>
          <div v-else-if="item.mime?.startsWith('video/')" class="editor-container">
            <video ref="video"
              :src="url(item.path)"
              crossorigin="anonymous"
              class="element"
              controls
            ></video>

            <div v-if="!readonly" class="toolbar">
              <div class="toolbar-group">
                <img v-if="Object.values(item.previews).length" class="video-preview"
                  :src="url(Object.values(item.previews).shift())"
                  @click="removeCover()"
                />
                <div v-else class="toolbar-group">
                  <v-btn icon="mdi-tooltip-image" :loading="covering" :title="$gettext('Use as cover image')" @click="addCover()" />
                  <v-btn icon :loading="covering" :title="$gettext('Upload cover image')" @click="$refs.coverInput.click()">
                    <v-icon>mdi-image-plus</v-icon>
                    <input ref="coverInput" type="file" class="cover-input" @change="uploadCover($event)" />
                  </v-btn>
                </div>
              </div>
            </div>
          </div>
          <audio v-else-if="item.mime?.startsWith('audio/')"
            :src="url(item.path)"
            crossorigin="anonymous"
            class="element"
            controls
          ></audio>
          <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 16 16" fill="currentColor">
            <path d="M7.05 11.885c0 1.415-.548 2.206-1.524 2.206C4.548 14.09 4 13.3 4 11.885c0-1.412.548-2.203 1.526-2.203.976 0 1.524.79 1.524 2.203m-1.524-1.612c-.542 0-.832.563-.832 1.612q0 .133.006.252l1.559-1.143c-.126-.474-.375-.72-.733-.72zm-.732 2.508c.126.472.372.718.732.718.54 0 .83-.563.83-1.614q0-.129-.006-.25zm6.061.624V14h-3v-.595h1.181V10.5h-.05l-1.136.747v-.688l1.19-.786h.69v3.633z"/>
            <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2M9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/>
          </svg>
        </v-col>
      </v-row>
      <v-row>
        <v-col cols="12" class="description">
          <div class="label">
            {{ $gettext('Descriptions') }}
            <div v-if="!readonly" class="actions">
              <v-btn v-if="Object.values(item.description || {}).find(v => !!v)"
                @click="translateText(item.description)"
                :title="$gettext('Translate text')"
                :loading="translating"
                icon="mdi-translate"
                variant="text"
              />
              <v-btn
                @click="composeText()"
                :title="$gettext('Generate description')"
                :loading="composing"
                icon="mdi-creation"
                variant="text"
              />
              <v-btn
                @click="record()"
                :class="{dictating: audio}"
                :icon="audio ? 'mdi-microphone-outline' : 'mdi-microphone'"
                :title="$gettext('Dictate')"
                :loading="dictating"
                variant="text"
              />
            </div>
          </div>

          <v-tabs v-model="tabdesc">
            <v-tab v-for="entry in locales()" :value="entry.value">{{ entry.title }}</v-tab>
          </v-tabs>
          <v-window v-model="tabdesc" :touch="false">
            <v-window-item v-for="entry in locales()" :value="entry.value">
              <v-textarea ref="description"
                @update:modelValue="item.description[entry.value] = $event; $emit('update:item', item)"
                :label="$gettext('Description (%{lang})', {lang: entry.value})"
                :modelValue="item.description?.[entry.value] || ''"
                :readonly="readonly"
                variant="underlined"
                counter="500"
                rows="2"
                auto-grow
                clearable
              ></v-textarea>
            </v-window-item>
          </v-window>
        </v-col>
      </v-row>
      <v-row v-if="item.mime?.startsWith('audio/') || item.mime?.startsWith('video/')">
        <v-col cols="12" class="transcription">
          <div class="label">
            {{ $gettext('Transcriptions') }}
            <div v-if="!readonly" class="actions">
              <v-btn v-if="Object.values(item.transcription || {}).find(v => !!v)"
                @click="translateVTT(item.transcription)"
                :title="$gettext('Translate text')"
                :loading="translating"
                icon="mdi-translate"
                variant="text"
              />
              <v-btn
                @click="transcribeFile()"
                :title="$gettext('Transcribe file content')"
                :loading="transcribing"
                icon="mdi-creation"
                variant="text"
              />
            </div>
          </div>

          <v-tabs v-model="tabtrans">
            <v-tab v-for="entry in locales()" :value="entry.value">{{ entry.title }}</v-tab>
          </v-tabs>
          <v-window v-model="tabtrans" :touch="false">
            <v-window-item v-for="entry in locales()" :value="entry.value">
              <v-textarea ref="transcription"
                @update:modelValue="item.transcription[entry.value] = $event; $emit('update:item', item)"
                :label="$gettext('Transcription (%{lang})', {lang: entry.value})"
                :modelValue="item.transcription?.[entry.value] || ''"
                :readonly="readonly"
                variant="underlined"
                rows="10"
                auto-grow
                clearable
              ></v-textarea>
            </v-window-item>
          </v-window>
        </v-col>
      </v-row>
    </v-sheet>
  </v-container>


  <Teleport to="body">
    <FileAiDialog v-model="vedit" :files="[item]" @add="use($event)" />
  </Teleport>

</template>

<style scoped>
  .v-sheet.scroll {
    max-height: calc(100vh - 96px);
  }

  :deep(.cropper-bg) {
    background-repeat: repeat;
  }

  .preview {
    display: flex;
    justify-content: center;
  }

  .preview .element {
    max-width: 100%;
    max-height: 100%;
  }

  .preview svg {
    width: 5rem;
    height: 5rem;
  }

  .editor-container {
    width: 100%;
  }

  :deep(.crop-label) {
    position: absolute;
    top: calc(50% + 16px);
    left: 50%;
    color: #fff;
    font-size: 12px;
    padding: 12px 6px;
    border-radius: 4px;
    white-space: nowrap;
    pointer-events: none;
    transform: translate(-50%, -50%);
    background: rgba(0, 0, 0, 0.6);
  }

  .toolbar {
    gap: 8px;
    width: 100%;
    display: flex;
    padding: 10px;
    flex-wrap: nowrap;
    justify-content: center;
    background-color: rgb(var(--v-theme-background));
  }

  .toolbar-group {
    display: flex;
    gap: 8px;
  }

  img.video-preview {
    width: 100px;
    cursor: pointer;
    border-radius: 8px;
  }

  .cover-input {
    display: none;
  }

  .description .label,
  .transcription .label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-transform: capitalize;
    font-weight: bold;
    margin-bottom: 4px;
    margin-top: 40px;
  }

  .description .v-textarea,
  .transcription .v-textarea {
    margin-top: 16px;
  }

  @media (max-width: 480px) {
    .toolbar {
      width: auto;
    }

    .toolbar-group {
      flex-direction: column;
      justify-content: center;
      gap: 4px;
    }
  }
</style>