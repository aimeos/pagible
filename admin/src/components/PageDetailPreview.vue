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
        expanded: false,
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
          .replace(/:domain/, this.item.domain || '')
          .replace(/:path/, this.item.path || '')
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
          case null:
            this.index = null
            break
          case false:
            this.vpreview = true
            setTimeout(() => { this.vpreview = false }, 3000)
            break
          default:
            this.index = msg.data.id ? this.item.content.findIndex(c => c.id === msg.data.id) : null
            this.section = msg.section || 'main'
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
    <div v-if="index !== null" class="actions">
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
    <div v-if="vpreview" class="preview-mode">
      {{ $gettext('Preview mode') }}
    </div>

    <iframe ref="iframe" :src="url"></iframe>

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
  </div>

  <Teleport to="body">
    <FieldsDialog v-if="element"
      v-model="vedit"
      :element="element.type === 'reference' ? elements[element.refid] : element"
      :readonly="!auth.can('page:save') || !!element.refid"
      @update:element="update()"
    />
  </Teleport>

  <Teleport to="body">
    <SchemaDialog
      v-model="vschemas"
      @add="add($event)"
    />
  </Teleport>
</template>

<style>
  .page-preview {
    overflow: hidden;
    position: relative;
    height: calc(100vh - 96px);
    width: 100%;
  }

  iframe {
    width: 100%;
    height: 100%;
  }

  .v-btn.fullscreen {
    background: rgb(var(--v-theme-surface-variant));
    color: rgb(var(--v-theme-surface));
    border-radius: 50%;
    position: absolute;
    bottom: 10px;
    right: 20px;
    z-index: 1000;
    opacity: 0.85;
  }

  .page-preview .actions {
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

  .page-preview .actions .v-btn {
    background: rgb(var(--v-theme-surface-variant));
    color: rgb(var(--v-theme-surface));
    border-radius: 50%;
    opacity: 0.85;
  }

  .preview-mode {
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
