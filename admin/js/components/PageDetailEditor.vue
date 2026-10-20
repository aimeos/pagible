/** @license MIT, https://opensource.org/license/mit */

<script>
import { defineAsyncComponent } from 'vue'
import LoadingSpinner from './LoadingSpinner.vue'
import { useUserStore, useMessageStore } from '../stores'
import { pageUrl } from '../urls'
import { trigger } from '../shortcuts'
import { uid } from '../utils'

const SchemaDialog = defineAsyncComponent(() => import('./SchemaDialog.vue'))
const HINT_DURATION = 3000 // ms the preview hints are shown

// status codes sent by the preview page
const PREVIEW = Object.freeze({
  UNSELECT: 0, // element unselected
  DENIED: -1, // editing not allowed
  FOREIGN: -2 // no CMS content
})

export default {
  components: {
    LoadingSpinner,
    SchemaDialog
  },

  props: {
    save: { type: Object, required: true },
    item: { type: Object, required: true },
    elements: { type: Object, required: true },
    asideVisible: { type: Boolean, default: false },
    previewSize: {
      type: String,
      default: 'computer',
      validator: (value) => ['mobile', 'tablet', 'computer'].includes(value)
    }
  },

  emits: ['change', 'edit', 'add-elements', 'update:content'],

  provide() {
    return {
      // let descendant file fields refresh the preview after editing a file
      update: this.reload
    }
  },

  setup() {
    const messages = useMessageStore()
    const user = useUserStore()
    return {
      user,
      messages
    }
  },

  data() {
    return {
      pos: null,
      index: null,
      // the preview shows the saved page, unsaved domain/path changes (e.g. by translating) would 404
      saved: { domain: this.item.domain, path: this.item.path },
      element: null,
      section: 'main',
      loading: true,
      vcontent: false,
      vschemas: false,
      vpreview: false,
      visible: false,
      timers: {}
    }
  },

  mounted() {
    window.addEventListener('message', this.message)

    if (this.user.can('page:save')) {
      this.messages.add(this.$gettext('Double-click to edit'), 'info')
    }
  },

  beforeUnmount() {
    window.removeEventListener('message', this.message)

    if (this.$refs.iframe) {
      this.$refs.iframe.src = 'about:blank'
    }

    Object.values(this.timers).forEach((id) => clearTimeout(id))
  },

  computed: {
    url() {
      if (!this.visible) {
        return null
      }

      return pageUrl(this.saved)
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
        this.$emit('add-elements', { [item.id]: item })
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
      this.element = (this.item.content || [])[this.index] || null
      this.$emit('edit', this.element, !!this.element)
    },

    // shows the preview hint of the given data key for a few seconds
    hint(key) {
      clearTimeout(this.timers[key])

      this[key] = true
      this.timers[key] = setTimeout(() => (this[key] = false), HINT_DURATION)
    },

    insert() {
      if (!this.element || this.pos === null) return

      const content = [...(this.item.content || [])]
      let index = Math.max(0, (this.index ?? -1) + this.pos)

      // elements unknown to the page (e.g. of empty or inherited sections) are added at the end of the section
      if (this.index === -1) {
        const last = content.findLastIndex((entry) => (entry.group || 'main') === this.element.group)
        index = last === -1 ? content.length : last + 1
      }

      content.splice(index, 0, this.element)
      this.$emit('update:content', content)
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
        // save/publish shortcut pressed inside the preview
        case 'save':
        case 'publish':
          trigger(msg.data)
          break
        case PREVIEW.UNSELECT:
          this.index = null
          this.element = null
          this.$emit('edit', null)
          break
        case PREVIEW.DENIED:
          this.hint('vpreview')
          break
        case PREVIEW.FOREIGN:
          this.hint('vcontent')
          break
        default: {
          const data = msg.data && typeof msg.data === 'object' ? msg.data : {}

          this.index = data.id ? (this.item.content || []).findIndex((c) => c.id === data.id) : null
          this.section = data.section || 'main'

          if (this.index === -1) {
            this.$emit('edit', null)
            this.addAfter()
          } else if (this.index !== null) {
            this.edit()
          }
        }
      }
    },

    loaded() {
      this.loading = false

      if (this.origin) {
        this.$refs.iframe?.contentWindow?.postMessage('init', this.origin)
      }
    },

    reload() {
      if (this.origin) {
        this.$refs.iframe?.contentWindow?.postMessage('reload', this.origin)
      }
    },

    remove() {
      if (this.index === null || this.index < 0) return

      this.$emit('update:content', (this.item.content || []).filter((entry, idx) => idx !== this.index))
      this.element = null
      this.index = null

      this.$emit('edit', null)
      this.$emit('change', 'content')
      // saving increments save.count which reloads the preview
      this.save.fcn(true)
    }
  },

  watch: {
    url(url) {
      if (url) {
        this.loading = true
      }
    },

    'save.count': function () {
      if (this.save.count > 0) {
        this.saved = { domain: this.item.domain, path: this.item.path }
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
        <LoadingSpinner />
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
      <iframe ref="iframe" :src="url" @load="loaded()"></iframe>

      <SchemaDialog v-model="vschemas" :attach="$refs.preview" :lang="item.lang" @add="add($event)" />
    </div>
  </div>
</template>

<style>
.page-preview-stage {
  box-sizing: border-box;
  height: var(--cms-detail-height);
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
  background: rgba(var(--v-theme-surface-variant), 0.85);
  color: rgb(var(--v-theme-on-surface-variant));
  border-radius: 10px;
  font-weight: bold;
  padding: 20px;
}
</style>
