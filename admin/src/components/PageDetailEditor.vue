<script>
  import FieldsDialog from './FieldsDialog.vue'
  import SchemaDialog from './SchemaDialog.vue'
  import { useAppStore, useAuthStore, useMessageStore } from '../stores'
  import { uid } from '../utils'

  export default {
    components: {
      FieldsDialog,
      SchemaDialog
    },

    props: {
      'save': {type: Object, required: true},
      'item': {type: Object, required: true},
      'elements': {type: Object, required: true},
      'assets': {type: Object, default: () => ({})}
    },

    emits: ['change'],

    setup() {
      const messages = useMessageStore()
      const auth = useAuthStore()
      const app = useAppStore()

      return { app, auth, messages }
    },

    data() {
      return {
        pos: null,
        index: null,
        element: null,
        section: 'main',
        loading: true,
        expanded: false,
        vcontent: false,
        vschemas: false,
        vpreview: false,
        vedit: false,
      }
    },

    mounted() {
      window.addEventListener('message', this.message)

      this.$refs.iframe?.addEventListener('load', () => {
        this.$refs.iframe?.contentWindow?.postMessage('init', '*')
      })

      this.messages.add(this.$gettext('Double-click to edit'), 'info')
    },

    beforeUnmount() {
      window.removeEventListener('message', this.message);
    },

    computed: {
      url() {
        return this.app.urlpage
          .replace(/_domain_/, this.item.domain || '')
          .replace(/_path_/, this.item.path || '')
          .replace(/\/+$/, '')
      }
    },

    methods: {
      add(item) {
        const group = this.section || 'main'
        this.vschemas = false

        if(item.id) {
          this.elements[item.id] = item
          this.element = {id: uid(), group: group, type: 'reference', refid: item.id}
          this.update()
        } else {
          this.element = {id: uid(), group: group, type: item.type, data: {}}
          this.vedit = true
        }
      },


      edit() {
        this.element = this.item.content[this.index] || null
        this.vedit = this.element ? true : false
      },


      fullscreen() {
        const preview = this.$refs.preview

        if(!this.expanded) {
          if(preview?.requestFullscreen) {
            preview.requestFullscreen()
          } else if(preview?.webkitRequestFullscreen) {
            preview.webkitRequestFullscreen() // Safari
          }
        } else {
          if(document.exitFullscreen) {
            document.exitFullscreen()
          } else if(document.webkitExitFullscreen) {
            document.webkitExitFullscreen() // Safari
          }
        }

        this.expanded = !this.expanded
      },


      message(msg) {
        switch(msg.data) {
          // unselect element
          case 0:
            this.index = null
            break
          // not allowed
          case -1:
            this.vpreview = true
            setTimeout(() => { this.vpreview = false }, 3000)
            break
          // not cms content
          case -2:
            this.vcontent = true
            setTimeout(() => { this.vcontent = false }, 3000)
            break
          default:
            this.index = typeof msg.data === 'object' && msg.data.id ? this.item.content.findIndex(c => c.id === msg.data.id) : null
            this.section = msg.data.section || 'main'
        }
      },


      remove() {
        if(this.index === null) return

        this.item.content.splice(this.index, 1)
        this.$emit('change', 'content')
        this.index = null

        this.save.fcn(true).then(() => {
          this.$refs.iframe.contentWindow.postMessage('reload', this.url)
        })
      },


      update() {
        if(this.pos !== null) {
          this.item.content.splice(this.index + this.pos, 0, this.element)
        }

        this.vedit = false
        this.index = null
        this.pos = null

        this.$emit('change', 'content')
        this.save.fcn(true).then(() => {
          this.$refs.iframe.contentWindow.postMessage('reload', this.url)
        })
      }
    },

    watch: {
      'save.count': function() {
        if(this.save.count > 0) {
          this.$refs.iframe.contentWindow.postMessage('reload', this.url)
        }
      }
    }
  }
</script>

<template>
  <div class="page-preview" ref="preview">
    <div v-if="loading" class="controls">
      <svg class="spinner" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
        <circle class="spin1" cx="4" cy="12" r="3"/>
        <circle class="spin1 spin2" cx="12" cy="12" r="3"/>
        <circle class="spin1 spin3" cx="20" cy="12" r="3"/>
      </svg>
    </div>

    <div v-if="index !== null" class="controls">
      <v-btn v-if="index !== -1"
        @click="edit()"
        :title="$gettext('Edit element')"
        icon="mdi-pencil"
        variant="flat"
      />
      <v-btn v-if="index !== -1"
        @click="vschemas = true; pos = 0"
        :title="$gettext('Add element before')"
        icon="mdi-table-row-plus-before"
        variant="text"
      />
      <v-btn
        @click="vschemas = true; pos = 1"
        :title="$gettext('Add element after')"
        icon="mdi-table-row-plus-after"
        variant="text"
      />
      <v-btn v-if="index !== -1"
        @click="remove()"
        :title="$gettext('Remove element')"
        icon="mdi-trash-can-outline"
        variant="text"
      />
    </div>

    <div v-if="vpreview" class="preview-hint">
      {{ $gettext('Preview mode') }}
    </div>
    <div v-if="vcontent" class="preview-hint">
      {{ $gettext('Not CMS content') }}
    </div>

    <iframe ref="iframe" :src="url" @load="loading = false"></iframe>

    <v-btn v-if="!expanded"
      @click="fullscreen()"
      :title="$gettext('Fullscreen mode')"
      icon="mdi-fullscreen"
      class="fullscreen"
      variant="text"
    />
    <v-btn v-if="expanded"
      @click="fullscreen()"
      :title="$gettext('Exit fullscreen mode')"
      icon="mdi-fullscreen-exit"
      class="fullscreen"
      variant="text"
    />

    <FieldsDialog v-if="element"
      v-model="vedit"
      :assets="assets"
      :element="element.type === 'reference' ? elements[element.refid] : element"
      :readonly="!auth.can('page:save') || !!element.refid"
      :attach="$refs.preview"
      @update:element="update()"
    />

    <SchemaDialog
      v-model="vschemas"
      :attach="$refs.preview"
      @add="add($event)"
    />
  </div>
</template>

<style>
  .page-preview {
    overflow: hidden;
    position: relative;
    height: calc(100vh - 96px);
    width: 100%;
  }

  .page-preview iframe {
    width: 100%;
    height: 100%;
  }

  .page-preview .v-btn.fullscreen {
    background: rgb(var(--v-theme-surface-variant));
    color: rgb(var(--v-theme-surface));
    border-radius: 50%;
    position: absolute;
    bottom: 10px;
    right: 20px;
    z-index: 1000;
    opacity: 0.85;
  }

  .page-preview .controls {
    position: absolute;
    top: 50%;
    left: 50%;
    z-index: 999;
    transform: translate(-50%, -50%);
    grid-template-columns: repeat(auto-fit, 1fr);
    grid-auto-flow: column;
    display: grid;
    gap: 10px;
  }

  .page-preview .controls .spinner {
    width: 72px;
  }

  .page-preview .controls .v-btn {
    background: rgb(var(--v-theme-surface-variant));
    color: rgb(var(--v-theme-surface));
    border-radius: 50%;
    opacity: 0.85;
  }

  .page-preview .preview-hint {
    top: 50%;
    left: 50%;
    z-index: 999;
    position: absolute;
    transform: translate(-50%, -50%);
    background: rgb(var(--v-theme-surface-variant));
    color: rgb(var(--v-theme-surface));
    border-radius: 10px;
    font-weight: bold;
    opacity: 0.85;
    padding: 20px;
  }
</style>
