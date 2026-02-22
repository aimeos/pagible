/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

<script>
  import gql from 'graphql-tag'
  import { toMp3, transcription } from './audio'
  import { computed, markRaw, provide } from 'vue'
  import { useAppStore, useLanguageStore, useMessageStore } from './stores'

  export default {
    data() {
      return {
        viewStack: []
      }
    },

    provide() {
      return {
        base64ToBlob: this.base64ToBlob,
        debounce: this.debounce,
        openView: this.open,
        closeView: this.close,
        write: this.write,
        transcribe: this.transcribe,
        translate: this.translate,
        txlocales: this.txlocales,
        locales: this.locales,
        slugify: this.slugify,
        srcset: this.srcset,
        url: this.url,
      }
    },

    setup() {
      const languages = useLanguageStore()
      const messages = useMessageStore()
      const app = useAppStore()

      return { app, languages, messages }
    },

    methods: {
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


      debounce(func, delay) {
        let timer

        return function(...args) {
          return new Promise((resolve, reject) => {
            const context = this

            clearTimeout(timer)
            timer = setTimeout(() => {
              try {
                resolve(func.apply(context, args))
              } catch (error) {
                reject(error)
              }
            }, delay)
          })
        }
      },


      open(component, props = {}) {
        if(!component) {
          console.error('Component is not defined')
          return
        }

        this.viewStack.push({
          component: markRaw(component),
          props: props || {},
        })
      },


      close() {
        this.viewStack.pop()
      },


      write(prompt, context = [], files = []) {
        prompt = String(prompt).trim()

        if(!prompt) {
          this.messages.add(this.$gettext('Prompt is required for generating text'), 'error')
          return Promise.resolve('')
        }

        if(!Array.isArray(context)) {
          context = [context]
        }

        context.push('Only return the requested data without any additional information')

        return this.$apollo.mutate({
          mutation: gql`mutation($prompt: String!, $context: String, $files: [String!]) {
            write(prompt: $prompt, context: $context, files: $files)
          }`,
          variables: {
            prompt: prompt,
            context: context.filter(v => !!v).join("\n"),
            files: files.filter(v => !!v)
          }
        }).then(result => {
          if(result.errors) {
            throw result
          }

          return result.data?.write?.replace(/^"(.*)"$/, '$1') || ''
        }).catch(error => {
          this.messages.add(this.$gettext('Error generating text') + ":\n" + error, 'error')
          this.$log(`App::write(): Error generating text`, error)
        })
      },


      locales(none = false) {
        const list = []

        if(none) {
          list.push({value: null, title: this.$gettext('None')})
        }

        this.languages.available.forEach(code => {
          list.push({value: code, title: this.languages.translate(code) + ' (' + code.toUpperCase() + ')'})
        })

        return list
      },


      slugify(text) {
        if(!text) return ''
        return text
          .replace(/[?&=%#@!$^*()+=\[\]{}|\\"'<>;:.,_\s]/gu, '-')
          .replace(/-+/g, '-')
          .replace(/^-|-$/g, '')
          .toLowerCase()
      },


      srcset(map) {
        let list = []
        for(const key in (map || {})) {
          list.push(`${this.url(map[key])} ${key}w`)
        }
        return list.join(', ')
      },


      transcribe(input) {
        return toMp3(this.url(input, true)).then(blob => {
          return this.$apollo.mutate({
            mutation: gql`mutation($file: Upload!) {
              transcribe(file: $file)
            }`,
            variables: {
              file: new File([blob], 'audio.mp3', { type: 'audio/mpeg' })
            },
            context: {
              hasUpload: true,
            }
          })
        }).then(result => {
          if(result.errors) {
            throw result
          }

          return transcription(JSON.parse(result.data?.transcribe || '[]'))
        }).catch(error => {
          this.messages.add(this.$gettext('Error transcribing file') + ":\n" + error, 'error')
          this.$log(`App::transcribe(): Error transcribing from media URL`, error)
        })
      },


      translate(texts, to, from = null, context = null) {
        if(!Array.isArray(texts)) {
          texts = [texts].filter(v => !!v)
        }

        if(!texts.length) {
          return Promise.resolve([])
        }

        if(!to) {
          return Promise.reject(new Error('Target language is required'))
        }

        return this.$apollo.mutate({
          mutation: gql`mutation($texts: [String!]!, $to: String!, $from: String, $context: String) {
            translate(texts: $texts, to: $to, from: $from, context: $context)
          }`,
          variables: {
            texts: texts,
            to: to.toUpperCase(),
            from: from?.toUpperCase(),
            context: context
          }
        }).then(result => {
          if(result.errors) {
            throw result
          }

          return result.data?.translate || []
        }).catch(error => {
          this.messages.add(this.$gettext('Error translating texts') + ":\n" + error, 'error')
          this.$log(`App::translate(): Error translating texts`, error)
        })
      },


      txlocales(current = null) {
        const list = []
        const supported = [
          'ar', 'bg', 'cs', 'da', 'de', 'el', 'en', 'en-GB', 'en-US', 'es', 'et', 'fi', 'fr',
          'he', 'hu', 'id', 'it', 'ja', 'ko', 'lt', 'lv', 'nb', 'nl', 'pl', 'pt', 'pt-BR',
          'ro', 'ru', 'sk', 'sl', 'sv', 'th', 'tr', 'uk', 'vi', 'zh', 'zh-Hans', 'zh-Hant'
        ]

        this.languages.available.forEach(code => {
          if(supported.includes(code) && code !== current) {
            list.push({code: code, name: this.languages.translate(code) + ' (' + code.toUpperCase() + ')'})
          }
        })

        return list
      },


      url(path, proxy = false) {
        if(!path) return ''

        if(typeof path !== 'string') {
          return path
        }

        if(proxy && path.startsWith('http')) {
          return this.app.urlproxy.replace(/_url_/, encodeURIComponent(path))
        }

        if(path.startsWith('http') || path.startsWith('blob:')) {
          return path
        }

        return this.app.urlfile.replace(/\/+$/g, '') + '/' + path
      }
    }
  }
</script>

<template>
  <v-app>
    <transition-group name="slide-stack">
      <v-layout ref="baseview" key="list" class="view" style="z-index: 10">
        <router-view />
      </v-layout>

      <v-layout ref="view" v-for="(view, i) in viewStack" :key="i" class="view" :style="{ zIndex: 11 + i }">
        <component :is="view.component" v-bind="view.props" />
      </v-layout>
    </transition-group>

    <v-snackbar-queue v-model="messages.queue"></v-snackbar-queue>
  </v-app>
</template>

<style>
  html, body {
    position: absolute;
    overflow: hidden;
    height: 100%;
    width: 100%;
    left: 0;
    top: 0;
  }

  .view {
    background: rgb(var(--v-theme-background));
    position: absolute !important;
    min-height: 100%;
    width: 100%;
  }

  /* Slide animation */
  .slide-stack-enter-active,
  .slide-stack-leave-active {
    transition: transform 0.3s ease;
  }

  .slide-stack-enter-from {
    transform: translateX(100%);
  }

  .slide-stack-leave-to {
    transform: translateX(100%);
  }
</style>
