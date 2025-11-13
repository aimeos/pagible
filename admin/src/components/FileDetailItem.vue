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

    inject: ['base64ToBlob', 'compose', 'locales', 'transcribe', 'translate', 'txlocales', 'url'],

    data() {
      return {
        vedit: false,
        selected: false,
        loading: {},
        tabtrans: null,
        tabdesc: null,
        edittext: null,
        cropLabel: null,
        cropper: null,
        audio: null,
        images: [],
        menu: {},
        width: 0,
        height: 0,
        extend: {
          top: 0,
          right: 0,
          bottom: 0,
          left: 0
        },
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

          self.loading.cover = true

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
            self.loading.cover = false
          })
        }, 'image/png', 1)
      },


      aspect(ratio) {
        this.cropper.setAspectRatio(ratio)
        this.cropper.setDragMode('crop')

        this.$nextTick(() => {
          const cropBox = this.cropper.cropper.querySelector(".cropper-crop-box");

          if(cropBox && !this.cropLabel) {
            const label = document.createElement("div");
            label.className = "crop-label";
            cropBox.appendChild(label);
            this.cropLabel = label;
          }
        });
      },


      clear() {
        this.cropper.setDragMode('none')
        this.cropper.clear()
        this.selected = false
        this.cropLabel = null
      },


      composeText() {
        const lang = this.desclangs.shift() || this.item.lang || 'en'
        const prompt = `Summarize the content of the file in a few words in plain text format for a title tag in the language with the ISO code "${lang}":`

        this.loading.compose = true

        this.compose(prompt, null, [this.item.id]).then(result => {
          this.update('description', Object.assign(this.item.description || {}, {[lang]: result}))
        }).finally(() => {
          this.loading.compose = false
        })
      },


      crop() {
        this.updateFile()
        this.clear()
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


      erase() {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        const self = this

        this.image().then(blob => {
          self.mask().toBlob(function(mask) {
            self.loading.erase = true

            self.$apollo.mutate({
              mutation: gql`mutation($file: Upload!, $mask: Upload!) {
                erase(file: $file, mask: $mask)
              }`,
              variables: {
                file: new File([blob], 'image', {type: self.item.mime}),
                mask: new File([mask], 'mask', {type: 'image/png'}),
              },
              context: {
                hasUpload: true
              }
            }).then(response => {
              if(response.errors) {
                throw response.errors
              }

              self.replace(self.base64ToBlob(response.data?.erase))
            }).catch(error => {
              self.messages.add(self.$gettext('Error erasing image part') + ":\n" + error, 'error')
              self.$log('FileDetailItem::erase(): Error erasing image part', error)
            }).finally(() => {
              self.loading.erase = false
              self.clear()
            })
          })
        })
      },


      flipX() {
        this.cropper.scaleX(-1)
        this.updateFile()
      },


      flipY() {
        this.cropper.scaleY(-1)
        this.updateFile()
      },


      image() {
        if(this.images[0]?.blob) {
          return Promise.resolve(this.images[0]?.blob)
        }

        return fetch(this.url(this.item.path, true)).then(response => {
          if(!response.ok) {
            throw new Error('Network error: ' + response.statusText)
          }
          return response.blob()
        })
      },


      init() {
        if(this.readonly || !this.item.mime?.startsWith('image/')) {
          return null
        }

        if(this.cropper) {
          this.cropper.destroy()
        }

        const self = this

        return new Cropper(this.$refs.image, {
          aspectRatio: NaN,
          background: true,
          dragMode: 'none',
          movable: false,
          autoCrop: false,
          zoomable: false,
          responsive: false,
          zoomOnWheel: false,
          zoomOnTouch: false,
          touchDragZoom: false,
          checkCrossOrigin: false,
          checkOrientation: false,
          viewMode: 1,
          crop(event) {
            if (!self.cropLabel) return

            const { width, height } = event.detail
            self.cropLabel.textContent = `${Math.round(width)} × ${Math.round(height)}`
            self.selected = true
          },
          ready() {
            const imageData = this.cropper.getImageData()
            self.height = imageData.naturalHeight
            self.width = imageData.naturalWidth
          }
        })
      },


      inpaint() {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        if(!this.edittext?.trim()) {
          return
        }

        const self = this

        this.image().then(blob => {
          self.mask().toBlob(function(mask) {
            self.loading.paint = true

            self.$apollo.mutate({
              mutation: gql`mutation($file: Upload!, $mask: Upload!, $prompt: String!) {
                inpaint(file: $file, mask: $mask, prompt: $prompt)
              }`,
              variables: {
                file: new File([blob], 'image', {type: self.item.mime}),
                mask: new File([mask], 'mask', {type: 'image/png'}),
                prompt: self.edittext
              },
              context: {
                hasUpload: true
              }
            }).then(response => {
              if(response.errors) {
                throw response.errors
              }

              self.replace(self.base64ToBlob(response.data?.inpaint))
            }).catch(error => {
              self.messages.add(self.$gettext('Error editing image part') + ":\n" + error, 'error')
              self.$log('FileDetailItem::inpaint(): Error editing image part', error)
            }).finally(() => {
              self.loading.paint = false
              self.clear()
            })
          })
        })
      },


      isolate() {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        const self = this

        this.cropper.getCroppedCanvas().toBlob(function(blob) {
          self.loading.isolate = true

          self.$apollo.mutate({
            mutation: gql`mutation($file: Upload!) {
              isolate(file: $file)
            }`,
            variables: {
              file: new File([blob], 'image.png', {type: 'image/png'}),
            },
            context: {
              hasUpload: true
            }
          }).then(response => {
            if(response.errors) {
              throw response.errors
            }

            self.replace(self.base64ToBlob(response.data?.isolate))
          }).catch(error => {
            self.messages.add(self.$gettext('Error removing background') + ":\n" + error, 'error')
            self.$log('FileDetailItem::isolate(): Error removing background', error)
          }).finally(() => {
            self.loading.isolate = false
          })
        })
      },


      mask() {
        const canvas = document.createElement('canvas')
        const context = canvas.getContext('2d')

        const data = this.cropper.getImageData()
        const crop = this.cropper.getData()

        canvas.width = data.naturalWidth
        canvas.height = data.naturalHeight

        context.fillStyle = 'black';
        context.fillRect(0, 0, canvas.width, canvas.height);

        context.fillStyle = 'white';
        context.fillRect(crop.x, crop.y, crop.width, crop.height);

        return canvas
      },


      record() {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        if(!this.audio) {
          return this.audio = recording().start()
        }

        this.audio.then(rec => {
          this.loading.dictate = true
          this.audio = null

          rec.stop().then(buffer => {
            this.transcribe(buffer).then(transcription => {
              const lang = this.desclangs[0] || this.item.lang || 'en'
              this.update('description', Object.assign(this.item.description || {}, {[lang]: transcription.asText()}))
            }).finally(() => {
              this.loading.dictate = false
            })
          })
        })
      },


      removeCover() {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        this.loading.cover = true
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
          this.loading.cover = false
        })
      },


      repaint() {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        if(!this.edittext?.trim()) {
          return
        }

        const self = this

        this.image().then(blob => {
          self.loading.paint = true

          self.$apollo.mutate({
            mutation: gql`mutation($file: Upload!, $prompt: String!) {
              repaint(file: $file, prompt: $prompt)
            }`,
            variables: {
              file: new File([blob], 'image', {type: self.item.mime}),
              prompt: self.edittext
            },
            context: {
              hasUpload: true
            }
          }).then(response => {
            if(response.errors) {
              throw response.errors
            }

            self.replace(self.base64ToBlob(response.data?.repaint))
          }).catch(error => {
            self.messages.add(self.$gettext('Error editing image') + ":\n" + error, 'error')
            self.$log('FileDetailItem::repaint(): Error editing image', error)
          }).finally(() => {
            self.loading.paint = false
            self.clear()
          })
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
        this.selected = false
        this.cropper.reset()
        this.cropper.clear()
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

        this.loading.transcribe = true

        this.transcribe(this.item.path).then(transcription => {
          const lang = this.desclangs[0] || this.item.lang || 'en'
          this.update('transcription', Object.assign(this.item.transcription || {}, {[lang]: transcription.asText()}))
        }).finally(() => {
          this.loading.transcribe = false
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

        this.loading.translate = true

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
          this.loading.translate = false
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


      uncrop() {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        if(!this.extend.top && !this.extend.right && !this.extend.bottom && !this.extend.left) {
          return
        }

        const self = this

        this.cropper.getCroppedCanvas().toBlob(function(blob) {
          self.loading.uncrop = true

          self.$apollo.mutate({
            mutation: gql`mutation($file: Upload!, $top: Int!, $right: Int!, $bottom: Int, $left: Int) {
              uncrop(file: $file, top: $top, right: $right, bottom: $bottom, left: $left)
            }`,
            variables: {
              file: new File([blob], 'image.png', {type: 'image/png'}),
              top: self.extend.top ?? 0,
              right: self.extend.right ?? 0,
              bottom: self.extend.bottom ?? 0,
              left: self.extend.left ?? 0
            },
            context: {
              hasUpload: true
            }
          }).then(response => {
            if(response.errors) {
              throw response.errors
            }

            self.replace(self.base64ToBlob(response.data?.uncrop))
          }).catch(error => {
            self.messages.add(self.$gettext('Error uncropping image') + ":\n" + error, 'error')
            self.$log('FileDetailItem::uncrop(): Error uncropping image', error)
          }).finally(() => {
            self.loading.uncrop = false
          })
        })
      },


      update(what, value) {
        this.item[what] = value
        this.$emit('update:item', this.item)
      },


      updateFile() {
        if(!this.readonly) {
          this.cropper.getCroppedCanvas().toBlob(blob => {
            const url = URL.createObjectURL(blob)

            this.images.unshift({blob: blob, url: url})
            this.images.splice(10).forEach(img => {
              URL.revokeObjectURL(img.url)
            })

            this.cropper.replace(url)
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

        this.loading.cover = true

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
          this.loading.cover = false
        })
      },


      upscale(factor) {
        if(this.readonly) {
          return this.messages.add(this.$gettext('Permission denied'), 'error')
        }

        const self = this

        this.cropper.getCroppedCanvas().toBlob(function(blob) {
          self.loading.upscale = true

          self.$apollo.mutate({
            mutation: gql`mutation($file: Upload!, $factor: Int!) {
              upscale(file: $file, factor: $factor)
            }`,
            variables: {
              file: new File([blob], 'image.png', {type: 'image/png'}),
              factor: factor
            },
            context: {
              hasUpload: true
            }
          }).then(response => {
            if(response.errors) {
              throw response.errors
            }

            self.replace(self.base64ToBlob(response.data?.upscale))
          }).catch(error => {
            self.messages.add(self.$gettext('Error upscaling image') + ":\n" + error, 'error')
            self.$log('FileDetailItem::upscale(): Error upscaling image', error)
          }).finally(() => {
            self.loading.upscale = false
          })
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
              <v-btn v-if="selected"
                @click="clear()"
                :title="$gettext('Cancel')"
                icon="mdi-close"
                class="no-rtl"
              />
              <component v-else :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
                v-model="menu['select']"
                transition="scale-transition"
                location="end center"
                max-width="300">

                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    :title="$gettext('Select area')"
                    icon="mdi-crop-free"
                    class="no-rtl"
                  />
                </template>

                <v-card>
                  <v-toolbar density="compact">
                    <v-toolbar-title>{{ $gettext('Select area') }}</v-toolbar-title>
                    <v-btn icon="mdi-close" @click="menu['select'] = false" />
                  </v-toolbar>

                  <v-list @click="menu['select'] = false">
                    <v-list-item>
                      <v-btn prepend-icon="mdi-crop-free" class="no-rtl" variant="text" @click="aspect(ratio)">{{ $gettext('Original ratio') }}</v-btn>
                    </v-list-item>
                    <v-list-item>
                      <v-btn prepend-icon="mdi-crop-free" class="no-rtl" variant="text" @click="aspect(NaN)">{{ $gettext('No ratio') }}</v-btn>
                    </v-list-item>
                    <v-list-item>
                      <v-btn prepend-icon="mdi-crop-free" class="no-rtl" variant="text" @click="aspect(1)">{{ $gettext('Square') }}</v-btn>
                    </v-list-item>
                    <v-list-item>
                      <v-btn prepend-icon="mdi-crop-free" class="no-rtl" variant="text" @click="aspect(3/2)">3:2</v-btn>
                    </v-list-item>
                    <v-list-item>
                      <v-btn prepend-icon="mdi-crop-free" class="no-rtl" variant="text" @click="aspect(4/3)">4:3</v-btn>
                    </v-list-item>
                    <v-list-item>
                      <v-btn prepend-icon="mdi-crop-free" class="no-rtl" variant="text" @click="aspect(5/3)">5:3</v-btn>
                    </v-list-item>
                    <v-list-item>
                      <v-btn prepend-icon="mdi-crop-free" class="no-rtl" variant="text" @click="aspect(16/9)">16:9</v-btn>
                    </v-list-item>
                  </v-list>
                </v-card>
              </component>

              <v-btn
                @click="crop()"
                :disabled="!selected"
                :title="$gettext('Crop selected area')"
                icon="mdi-crop"
                class="no-rtl"
              />

              <v-btn
                @click="erase()"
                :disabled="!selected"
                :loading="loading.erase"
                :title="$gettext('Erase selected area')"
                icon="mdi-eraser"
                class="no-rtl"
              />

              <v-dialog
                v-model="menu['paint']"
                transition="scale-transition"
                max-width="600">

                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    :loading="loading.paint"
                    :title="$gettext('Edit image')"
                    icon="mdi-image-edit"
                    class="no-rtl"
                  />
                </template>

                <v-card>
                  <v-toolbar density="compact">
                    <v-toolbar-title>{{ $gettext('Edit image') }}</v-toolbar-title>
                    <v-btn icon="mdi-close" @click="menu['paint'] = false" />
                  </v-toolbar>

                  <v-card-text>
                    <v-textarea
                      v-model="edittext"
                      :label="$gettext('Describe the changes')"
                      variant="underlined"
                      autofocus
                      clearable
                      auto-grow
                    ></v-textarea>
                  </v-card-text>

                  <v-card-actions>
                    <v-btn
                      variant="outlined"
                      :disabled="!edittext"
                      @click="selected ? inpaint() : repaint(); menu['paint'] = false"
                    >{{ $gettext('Edit image') }}</v-btn>
                  </v-card-actions>
                </v-card>
              </v-dialog>

              <v-btn
                @click="isolate()"
                :title="$gettext('Remove background')"
                :loading="loading.isolate"
                icon="mdi-image-filter-black-white"
                class="no-rtl"
              />

              <v-dialog
                v-model="menu['uncrop']"
                transition="scale-transition"
                max-width="300">

                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    :loading="loading.uncrop"
                    :title="$gettext('Expand image')"
                    icon="mdi-arrow-expand-all"
                    class="no-rtl"
                  />
                </template>

                <v-card class="uncrop">
                  <v-toolbar density="compact">
                    <v-toolbar-title>{{ $gettext('Expand image') }}</v-toolbar-title>
                    <v-btn icon="mdi-close" @click="menu['uncrop'] = false" />
                  </v-toolbar>

                  <v-card-text>
                    <v-row class="single">
                      <v-col cols="6">
                        <v-number-input
                          v-model="extend.top"
                          variant="outlined"
                          controlVariant="hidden"
                          :label="$gettext('Top')"
                          :max="2000"
                          :min="0"
                        />
                      </v-col>
                    </v-row>
                    <v-row>
                      <v-col cols="6">
                        <v-number-input
                          v-model="extend.left"
                          variant="outlined"
                          controlVariant="hidden"
                          :label="$gettext('Left')"
                          :max="2000"
                          :min="0"
                        />
                      </v-col>
                      <v-col cols="6">
                        <v-number-input
                          v-model="extend.right"
                          variant="outlined"
                          controlVariant="hidden"
                          :label="$gettext('Right')"
                          :max="2000"
                          :min="0"
                        />
                      </v-col>
                    </v-row>
                    <v-row class="single">
                      <v-col cols="6">
                        <v-number-input
                          v-model="extend.bottom"
                          variant="outlined"
                          controlVariant="hidden"
                          :label="$gettext('Bottom')"
                          :max="2000"
                          :min="0"
                        />
                      </v-col>
                    </v-row>
                  </v-card-text>

                  <v-card-actions>
                    <v-btn
                      variant="outlined"
                      @click="uncrop(extend.top, extend.right, extend.bottom, extend.left); menu['uncrop'] = false"
                    >{{ $gettext('Expand image') }}</v-btn>
                  </v-card-actions>
                </v-card>
              </v-dialog>

              <component :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
                v-model="menu['upscale']"
                transition="scale-transition"
                location="end center"
                max-width="300">

                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    :loading="loading.upscale"
                    :disabled="width >= 4096 && height >= 4096"
                    :title="$gettext('Upscale image')"
                    icon="mdi-magnify-expand"
                    class="no-rtl"
                  />
                </template>

                <v-card>
                  <v-toolbar density="compact">
                    <v-toolbar-title>{{ $gettext('Upscale image') }}</v-toolbar-title>
                    <v-btn icon="mdi-close" @click="menu['upscale'] = false" />
                  </v-toolbar>

                  <v-list @click="menu['upscale'] = false">
                    <v-list-item v-if="width * 16 <= 4096 && height * 16 <= 4096">
                      <v-btn prepend-icon="mdi-magnify-expand" class="no-rtl" variant="text" @click="upscale(16)">
                        {{ $gettext('Scale %{factor}', { factor: '16x' }) }}
                      </v-btn>
                    </v-list-item>
                    <v-list-item v-if="width * 8 <= 4096 && height * 8 <= 4096">
                      <v-btn prepend-icon="mdi-magnify-expand" class="no-rtl" variant="text" @click="upscale(8)">
                        {{ $gettext('Scale %{factor}', { factor: '8x' }) }}
                      </v-btn>
                    </v-list-item>
                    <v-list-item v-if="width * 4 <= 4096 && height * 4 <= 4096">
                      <v-btn prepend-icon="mdi-magnify-expand" class="no-rtl" variant="text" @click="upscale(4)">
                        {{ $gettext('Scale %{factor}', { factor: '4x' }) }}
                      </v-btn>
                    </v-list-item>
                    <v-list-item v-if="width * 2 <= 4096 && height * 2 <= 4096">
                      <v-btn prepend-icon="mdi-magnify-expand" class="no-rtl" variant="text" @click="upscale(2)">
                        {{ $gettext('Scale %{factor}', { factor: '2x' }) }}
                      </v-btn>
                    </v-list-item>
                  </v-list>
                </v-card>
              </component>

              <v-btn icon="mdi-rotate-left" class="no-rtl" @click="rotate(-90)" :title="$gettext('Rotate counter-clockwise')" />
              <v-btn icon="mdi-rotate-right" class="no-rtl" @click="rotate(90)" :title="$gettext('Rotate clockwise')" />

              <v-btn icon="mdi-flip-horizontal" class="no-rtl" @click="flipX" :title="$gettext('Flip horizontally')" />
              <v-btn icon="mdi-flip-vertical" class="no-rtl" @click="flipY" :title="$gettext('Flip vertically')" />

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
          <div v-else-if="item.mime?.startsWith('video/')" class="editor-container">
            <video ref="video"
              :src="url(item.path)"
              crossorigin="anonymous"
              class="element"
              controls
            ></video>

            <div v-if="!readonly" class="toolbar">
              <img v-if="Object.values(item.previews).length" class="video-preview"
                :src="url(Object.values(item.previews).shift())"
                @click="removeCover()"
              />
              <div v-else>
                <v-btn
                  icon="mdi-tooltip-image"
                  :loading="loading.cover"
                  :title="$gettext('Use as cover image')"
                  @click="addCover()"
                />
                <v-btn icon
                  :loading="loading.cover"
                  :title="$gettext('Upload cover image')"
                  @click="$refs.coverInput.click()">
                  <v-icon>mdi-image-plus</v-icon>
                  <input ref="coverInput" type="file" class="cover-input" @change="uploadCover($event)" />
                </v-btn>
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
                :loading="loading.translate"
                icon="mdi-translate"
                variant="text"
              />
              <v-btn
                @click="composeText()"
                :title="$gettext('Generate description')"
                :loading="loading.compose"
                icon="mdi-creation"
                variant="text"
              />
              <v-btn
                @click="record()"
                :class="{dictating: audio}"
                :icon="audio ? 'mdi-microphone-outline' : 'mdi-microphone'"
                :title="$gettext('Dictate')"
                :loading="loading.dictate"
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
                :loading="loading.translate"
                icon="mdi-translate"
                variant="text"
              />
              <v-btn
                @click="transcribeFile()"
                :title="$gettext('Transcribe file content')"
                :loading="loading.transcribe"
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

  .v-dialog .v-btn {
    display: block;
    margin: auto;
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
    flex-wrap: wrap;
    justify-content: center;
    background-color: rgb(var(--v-theme-background));
  }

  @media (max-width: 768px) {
    .toolbar {
      width: auto;
    }
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

  .uncrop .single,
  .v-card.uncrop .v-card-actions {
    justify-content: center;
  }

  .uncrop .v-number-input :deep(.v-field__input) {
    text-align: center;
  }
</style>