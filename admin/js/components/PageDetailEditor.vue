/** @license MIT, https://opensource.org/license/mit */

<script>
import { defineAsyncComponent } from 'vue'
import { useAppStore, useUserStore, useMessageStore } from '../stores'
import { uid } from '../utils'

const SchemaDialog = defineAsyncComponent(() => import('./SchemaDialog.vue'))

export default {
  components: {
    SchemaDialog
  },

  props: {
    save: { type: Object, required: true },
    item: { type: Object, required: true },
    elements: { type: Object, required: true },
    asideVisible: { type: Boolean, default: false },
    assets: { type: Object, default: () => ({}) },
    previewSize: {
      type: String,
      default: 'computer',
      validator: (value) => ['mobile', 'tablet', 'computer'].includes(value)
    }
  },

  emits: ['change', 'edit'],

  provide() {
    return {
      // let descendant file fields refresh the preview after editing a file
      update: this.reload
    }
  },

  setup() {
    const messages = useMessageStore()
    const user = useUserStore()
    const app = useAppStore()

    return {
      app,
      user,
      messages
    }
  },

  data() {
    return {
      pos: null,
      index: null,
      element: null,
      section: 'main',
      loading: true,
      vcontent: false,
      vschemas: false,
      vpreview: false,
      visible: false,
      timers: []
    }
  },

  mounted() {
    window.addEventListener('message', this.message)

    this.iframeLoad = () => {
      if (this.origin) {
        this.$refs.iframe?.contentWindow?.postMessage('init', this.origin)
      }
    }
    this.$refs.iframe?.addEventListener('load', this.iframeLoad)

    if (this.user.can('page:save')) {
      this.messages.add(this.$gettext('Double-click to edit'), 'info')
    }
  },

  beforeUnmount() {
    window.removeEventListener('message', this.message)
    this.$refs.iframe?.removeEventListener('load', this.iframeLoad)

    if (this.$refs.iframe) {
      this.$refs.iframe.src = 'about:blank'
    }

    this.timers.forEach((id) => clearTimeout(id))
  },

  computed: {
    url() {
      if (!this.visible) {
        return null
      }

      const domain = this.item.domain || ''
      let url = this.app.urlpage

      if (!domain) {
        url = url
          .replace(/^[a-z][a-z\d+.-]*:\/\/_domain_/i, '')
          .replace(/\/_domain_(?=\/|$)/, '')
      }

      return url
        .replace(/_domain_/, domain)
        .replace(/_path_/, this.item.path || '')
        .replace(/([^:/])\/+$/, '$1')
    },

    origin() {
      if (!this.url) {
        return null
      }

      try {
        return new URL(this.url, window.location.origin).origin
      } catch {
        return null
      }
    }
  },

  methods: {
    add(item) {
      const group = this.section || 'main'
      this.vschemas = false

      if (item.id) {
        this.elements[item.id] = item
        this.element = { id: uid(), group: group, type: 'reference', refid: item.id }
      } else {
        this.element = { id: uid(), group: group, type: item.type, data: {} }
      }

      this.insert()
    },

    addAfter() {
      this.vschemas = true
      this.pos = 1
    },

    addBefore() {
      this.vschemas = true
      this.pos = 0
    },

    edit() {
      this.element = this.item.content[this.index] || null
      this.$emit('edit', this.element, !!this.element)
    },

    insert() {
      if (!this.element || this.pos === null) return

      const index = Math.max(0, (this.index ?? -1) + this.pos)
      this.item.content.splice(index, 0, this.element)
      this.index = index
      this.pos = null

      this.$emit('change', 'content')
      this.$emit('edit', this.element, true)
    },

    load(isVisible) {
      this.visible = !!isVisible
    },

    message(msg) {
      // only accept messages coming from our own preview iframe and only from
      // the exact origin we navigated it to, not from arbitrary windows/frames
      // that may hold a reference to this window
      const expected = this.origin

      if (!expected) {
        return
      }

      if (msg.source !== this.$refs.iframe?.contentWindow || msg.origin !== expected) {
        return
      }

      switch (msg.data) {
        // unselect element
        case 0:
          this.index = null
          this.element = null
          this.$emit('edit', null)
          break
        // not allowed
        case -1:
          this.vpreview = true
          this.timers.push(setTimeout(() => {
            this.vpreview = false
          }, 3000))
          break
        // not cms content
        case -2:
          this.vcontent = true
          this.timers.push(setTimeout(() => {
            this.vcontent = false
          }, 3000))
          break
        default:
          this.index =
            typeof msg.data === 'object' && msg.data.id
              ? this.item.content.findIndex((c) => c.id === msg.data.id)
              : null
          this.section = msg.data.section || 'main'

          if (this.index === -1) {
            this.$emit('edit', null)
            this.addAfter()
          } else if (this.index !== null) {
            this.edit()
          }
      }
    },

    reload() {
      if (this.origin) {
        this.$refs.iframe?.contentWindow?.postMessage('reload', this.origin)
      }
    },

    remove() {
      if (this.index === null || this.index < 0) return

      this.item.content.splice(this.index, 1)
      this.element = null
      this.index = null

      this.$emit('edit', null)
      this.$emit('change', 'content')
      this.save.fcn(true).then(() => this.reload())
    }
  },

  watch: {
    'save.count': function () {
      if (this.save.count > 0) {
        this.reload()
      }
    }
  }
}
</script>

<template>
  <div :class="['page-preview-stage', { 'aside-visible': asideVisible }]" v-visible="load">
    <div :class="['page-preview', `preview-${previewSize}`]" ref="preview">
      <div v-if="loading" class="loading">
        <svg
          class="spinner"
          viewBox="0 0 24 24"
          fill="currentColor"
          xmlns="http://www.w3.org/2000/svg"
        >
          <circle class="spin1" cx="4" cy="12" r="3" />
          <circle class="spin1 spin2" cx="12" cy="12" r="3" />
          <circle class="spin1 spin3" cx="20" cy="12" r="3" />
        </svg>
      </div>

      <div v-if="vpreview" class="preview-hint">
        {{ $gettext('Preview mode') }}
      </div>
      <div v-if="vcontent" class="preview-hint">
        {{ $gettext('Not CMS content') }}
      </div>

      <!--
        No sandbox attribute: the preview loads our own same-origin page which needs
        both scripts and same-origin access (editor session, client-side requests and
        the element-selection bridge). A sandbox with allow-scripts + allow-same-origin
        is escapable and gives no real isolation, so it would only add a misleading
        warning. The actual trust boundary is the strict source/origin check in message().
      -->
      <iframe ref="iframe" :src="url" @load="loading = false"></iframe>

      <SchemaDialog v-model="vschemas" :attach="$refs.preview" @add="add($event)" />
    </div>
  </div>
</template>

<style>
.page-preview-stage {
  box-sizing: border-box;
  height: calc(100vh - 96px);
  width: 100%;
}

@media (min-width: 960px) {
  .page-preview-stage.aside-visible {
    margin-inline-end: 8px;
    width: calc(100% - 8px);
  }
}

.page-preview {
  overflow: hidden;
  position: relative;
  height: 100%;
  margin-inline: auto;
  max-width: 100%;
  width: 100%;
  transition: width 0.2s ease;
}

.page-preview.preview-mobile {
  width: 384px;
}

.page-preview.preview-tablet {
  width: 768px;
}

.page-preview.preview-computer {
  width: 100%;
}

.page-preview iframe {
  width: 100%;
  height: 100%;
}

.page-preview .loading {
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

.page-preview .loading .spinner {
  width: 72px;
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
