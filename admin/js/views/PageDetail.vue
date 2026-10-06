/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import AsideCount from '../components/AsideCount.vue'
import ActionMenu from '../components/ActionMenu.vue'
import ChatDialog from '../components/ChatDialog.vue'
import PageDetailContent from '../components/PageDetailContent.vue'

const FieldsAside = defineAsyncComponent(() => import('../components/FieldsAside.vue'))
const PageDetailItem = defineAsyncComponent(() => import('../components/PageDetailItem.vue'))
const PageDetailEditor = defineAsyncComponent(() => import('../components/PageDetailEditor.vue'))
import { applyResult, hasUnresolved } from '../merge'
import { detailBase, useDetail } from '../detail'
import { FILE_FIELDS, fileMap } from '../files'
import { defineAsyncComponent, markRaw } from 'vue'
import { focusInvalid, frozenParse, hasTrue, safeParse, txlocales } from '../utils'
import { useAppStore, useDrawerStore, useSchemaStore } from '../stores'
import {
  mdiCreation,
  mdiTranslate,
  mdiArrowRightThin
} from '@mdi/js'


const PageDetailMetrics = defineAsyncComponent(() => import('../components/PageDetailMetrics.vue'))

// copy of the element without the internal "_" properties
function strip(el) {
  const out = {}

  for (const k in el) {
    if (!k.startsWith('_')) out[k] = el[k]
  }

  return out
}

const PAGE_DETAIL_FIELDS = `
  id
  aux
  data
  published
  publish_at
  created_at
  editor
  files {
    ...CmsFileFields
  }
  elements {
    id
    type
    name
    data
    editor
    updated_at
    files {
      ...CmsFileFields
    }
  }
`

const FETCH_PAGE = gql`
  ${FILE_FIELDS}
  query($id: ID!, $access: Boolean!) {
    page(id: $id) {
      id
      access @include(if: $access)
      has
      restricted
      latest {
        ${PAGE_DETAIL_FIELDS}
      }
    }
  }
`

const FETCH_PAGE_VERSIONS = gql`
  ${FILE_FIELDS}
  query($id: ID!) {
    page(id: $id) {
      id
      versions {
        ${PAGE_DETAIL_FIELDS}
      }
    }
  }
`

const SAVE_PAGE = gql`
  mutation ($id: ID!, $input: PageInput!, $latestId: ID) {
    savePage(id: $id, input: $input, latestId: $latestId) {
      id
      latest { id published publish_at editor created_at }
      changed
    }
  }
`

export default {
  extends: detailBase,

  components: {
    ...detailBase.components,
    ActionMenu,
    AsideCount,
    ChatDialog,
    FieldsAside,
    PageDetailItem,
    PageDetailEditor,
    PageDetailContent,
    PageDetailMetrics
  },

  setup() {
    return {
      ...useDetail('page'),
      app: useAppStore(),
      drawer: useDrawerStore(),
      schemas: useSchemaStore(),
      mdiCreation,
      mdiTranslate,
      mdiArrowRightThin,
      txlocales
    }
  },

  data() {
    return {
      tab: this.app.urlpage ? 'editor' : 'content',
      aside: '',
      asidePage: 'meta',
      chatOpen: false,
      dirty: {},
      errors: {},
      assets: {},
      elements: {},
      editorActions: false,
      editorElement: null,
      previewSize: 'computer',
      latest: null,
      translating: false,
      savecnt: 0,
      historyData: null
    }
  },

  computed: {
    chatContext() {
      return this.$gettext(
        'The user is viewing the page with ID "%{id}". When they refer to "this page", use get-page with that ID.',
        { id: this.item.id }
      )
    },

    hasChanged() {
      return hasTrue(this.dirty)
    },

    hasConflict() {
      return hasUnresolved(this.changed, ['data', 'content', 'meta', 'config'])
    },

    hasContentConflict() {
      return hasUnresolved(this.changed, ['content', 'meta', 'config'])
    },

    hasPageConflict() {
      return hasUnresolved(this.changed)
    },

    hasError() {
      return hasTrue(this.errors)
    },

    changeTargets() {
      const item = this.item
      return markRaw({ data: item, meta: item.meta, config: item.config, content: item.content })
    },

    saveConfig() {
      return { fcn: this.save, count: this.savecnt }
    }
  },

  created() {
    this.schemas.load()
  },

  beforeUnmount() {
    this.assets = markRaw({})
    this.elements = markRaw({})
    this.editorActions = false
    this.editorElement = null
    this.latest = null
    this.dirty = null
    this.errors = null
  },

  methods: {
    // loads the latest version into the open editor; resolves true on success so the caller
    // can defer the websocket subscription until the initial load completed
    reload() {
      return this.reloadVersion(FETCH_PAGE, this.$gettext('Error fetching page'), (page) => {
        this.latest = page.latest

        Object.assign(this.item, safeParse(this.latest?.data))
        this.item.access = page.access
        this.item.has = page.has
        this.item.restricted = page.restricted
        this.item.published = this.latest?.published
        this.item.editor = this.latest?.editor
        this.item.updated_at = this.latest?.created_at

        const aux = safeParse(this.latest?.aux)
        this.item.content = aux.content ?? []
        this.item.config = aux.config ?? {}
        this.item.meta = aux.meta ?? {}

        this.assign(this.latest?.elements || [], this.latest?.files || [])
        this.latest = { id: this.latest?.id }
      }, () => !this.hasChanged, { access: this.user.can('page:access') })
    },

    apply(changes, version) {
      if (version) {
        this.elements = { ...this.elems(version.elements || []), ...this.elements }
        this.assets = { ...version.files, ...this.assets }
      }

      if (changes.content) {
        const prev = {}
        for (const el of this.item.content || []) {
          prev[el.id || el.refid] = JSON.stringify(strip(el))
        }

        changes.content = changes.content.map((el) => {
          const copy = { ...el }
          const old = prev[el.id || el.refid]

          if (old === undefined || old !== JSON.stringify(strip(el))) {
            copy._changed = true
          }

          return copy
        })
      }

      Object.assign(this.item, changes)
      this.dirty.page = true
      if (changes.content) this.dirty.content = true
      this.vhistory = false
    },

    // applies the elements and files of a version and drops references to missing files
    assign(elements, files) {
      const map = this.elems(elements)

      this.assets = markRaw(this.files(files, map))
      this.elements = markRaw(map)
      this.item.content = this.obsolete(this.item.content)
    },

    // removes internal properties and data not defined by the schema of the elements
    clean(data, type) {
      if (!data || !type) return data

      const map = (el) => {
        const cleaned = strip(el)
        const fields = this.schemas[type]?.[el.type]?.fields

        if (cleaned.data && fields) {
          cleaned.data = Object.fromEntries(Object.entries(cleaned.data).filter(([name]) => {
            return fields[name] || (name.endsWith('-rel') && fields[name.slice(0, -4)]?.rel)
          }))
        }

        return cleaned
      }

      return Array.isArray(data)
        ? data.map(map)
        : Object.fromEntries(Object.entries(data).map(([key, el]) => [key, map(el)]))
    },

    elems(entries) {
      const map = {}

      for (const entry of entries) {
        map[entry.id] = {
          ...entry,
          data: frozenParse(entry.data),
          files: Object.freeze(Object.values(this.files(entry.files || [])))
        }
      }

      return map
    },

    editorAction(action) {
      const editor = this.$refs.editor

      if (typeof editor?.[action] === 'function') {
        editor[action]()
      }
    },

    editElement(element, actions = false) {
      this.editorActions = !!element && actions
      this.editorElement = element
      this.aside = element ? 'editor' : ''
      this.drawer.aside = !!element
    },

    fileIds() {
      const files = new Set()

      for (const entry of this.item.content || []) {
        for (const id of entry.files || []) files.add(id)
        for (const file of this.elements[entry.refid]?.files || []) files.add(file.id)
      }

      for (const key in this.item.meta || {}) {
        for (const id of this.item.meta[key].files || []) files.add(id)
      }

      for (const key in this.item.config || {}) {
        for (const id of this.item.config[key].files || []) files.add(id)
      }

      return [...files]
    },

    files(entries, elements = {}) {
      const map = fileMap(entries)

      for (const element of Object.values(elements)) {
        for (const file of element.files || []) map[file.id] = file
      }

      return map
    },

    historyCurrent() {
      const item = this.item
      const fileIds = new Set(this.fileIds())
      const files = Object.fromEntries(Object.entries(this.assets).filter(([id]) => fileIds.has(id)))

      return markRaw({
        data: Object.freeze({
          related_id: item.related_id || null,
          scheduled: item.publish_at ? 1 : 0,
          cache: item.cache,
          domain: item.domain,
          lang: item.lang,
          name: item.name,
          path: item.path,
          status: item.status,
          title: item.title,
          tag: item.tag,
          to: item.to,
          type: item.type,
          theme: item.theme,
          meta: this.clean(item.meta, 'meta'),
          config: this.clean(item.config, 'config'),
          content: this.clean(item.content, 'content')
        }),
        elements: Object.values(this.elements).filter(element => item.content.some(block => block.refid === element.id)),
        files: markRaw(files)
      })
    },

    obsolete(content) {
      for (const entry of content) {
        if (Array.isArray(entry.files)) entry.files = entry.files.filter((id) => this.assets[id] !== undefined)
      }

      return content
    },

    pageUpdated(event) {
      Object.assign(this.item, event)
      this.dirty.page = true
    },

    reset() {
      this.$refs.page?.reset()
      this.$refs.content?.reset()

      this.dirty = {}
      this.changed = null
      this.errors = {}
    },

    async showError() {
      const tab = this.errors.content ? 'content' : this.errors.page ? 'page' : null

      if (!tab) {
        return
      }

      this.tab = tab
      this.aside = tab === 'content' ? 'count' : this.asidePage

      await this.$nextTick()
      await this.$refs[tab]?.showError()
      await this.$nextTick()

      focusInvalid(this.$refs.form)
    },

    // a chat turn may have changed this page via tool calls; reload it unless the user has unsaved edits
    chatDone() {
      if (!this.hasChanged && this.user.can('page:view')) {
        this.refresh()
      }
    },

    // reloads the page data and, on success, the preview iframe which renders the saved version
    refresh() {
      return this.reload().then((ok) => {
        if (ok) this.$refs.editor?.reload()
        return ok
      })
    },

    review() {
      this.chatOpen = true
      this.$nextTick(() => this.$refs.chat?.send(this.$gettext('Rate this page and suggest improvements')))
    },

    async save(quiet = false) {
      await this.$nextTick()
      this.$refs.content?.flush()

      if (!this.saveable()) {
        return false
      }

      if (!this.hasChanged) {
        return true
      }

      this.saving = true

      return this.$apollo
        .mutate({
          mutation: SAVE_PAGE,
          variables: {
            id: this.item.id,
            input: {
              cache: this.item.cache || 0,
              domain: this.item.domain || '',
              lang: this.item.lang || '',
              name: this.item.name || '',
              path: this.item.path || '',
              status: this.item.status || 0,
              title: this.item.title || '',
              tag: this.item.tag || '',
              to: this.item.to || '',
              type: this.item.type || '',
              theme: this.item.theme || '',
              related_id: this.item.related_id || null,
              meta: JSON.stringify(this.clean(this.item.meta || {}, 'meta')),
              config: JSON.stringify(this.clean(this.item.config || {}, 'config')),
              content: JSON.stringify(this.clean(this.item.content, 'content'))
            },
            latestId: this.latest?.id
          }
        })
        .then((response) => {
          const page = response.data?.savePage
          const changed = page?.changed ? markRaw(safeParse(page.changed)) : null

          if (changed?.latest?.id || page?.latest?.id) {
            this.latest = { id: changed?.latest?.id ?? page.latest.id }
          }

          applyResult(this, changed, this.$gettext('Page saved successfully'), quiet)

          if (changed) {
            const aux = changed.latest?.aux
            this.item.content = aux?.content ?? this.item.content
            this.item.config = aux?.config ?? this.item.config
            this.item.meta = aux?.meta ?? this.item.meta
          }

          this.savecnt++
          this.saved(page?.latest)

          return true
        })
        .catch((error) => {
          this.messages.error(this.$gettext('Error saving page'), error)
        })
        .finally(() => {
          this.saving = false
        })
    },

    async translatePage(lang) {
      if (!this.user.can('text:translate')) return this.messages.denied()

      if (!this.schemas.content) {
        this.messages.add(this.$gettext('No page schema for "content" found'), 'error')
        return
      }

      const allowed = ['text', 'markdown', 'plaintext', 'string']
      const list = [
        { item: this.item, key: 'title', text: this.item.title },
        { item: this.item, key: 'name', text: this.item.name },
        { item: this.item, key: 'path', text: this.item.path }
      ]

      for (const el of Object.values(this.item.meta)) {
        for (const name in el.data) {
          const fieldtype = this.schemas.meta[el.type]?.fields?.[name]?.type

          if (el.data[name] && allowed.includes(fieldtype)) {
            list.push({ item: el.data, key: name, text: el.data[name] })
          }
        }
      }

      this.item.content.forEach((el) => {
        for (const name in el.data) {
          const fields = this.schemas.content[el.type]?.fields
          const fieldtype = fields?.[name]?.type

          if (fieldtype === 'items') {
            for (const idx in el.data[name]) {
              const item = el.data[name][idx]

              for (const key in item) {
                if (allowed.includes(fields[name]?.item?.[key]?.type)) {
                  list.push({ item: item, key: key, text: item[key] })
                }
              }
            }
          } else if (el.type !== 'code' && el.data[name] && allowed.includes(fieldtype)) {
            list.push({ item: el.data, key: name, text: el.data[name] })
          }
        }
      })

      this.translating = true

      try {
        const { translate } = await import('../ai')
        const texts = list.map((entry) => entry.text)
        const result = []

        // The translate mutation accepts at most 50 texts per request
        for (let i = 0; i < texts.length; i += 50) {
          const chunk = await translate(texts.slice(i, i + 50), lang, this.item.lang)

          if (!Array.isArray(chunk)) {
            return // error message is already shown by translate()
          }

          result.push(...chunk)
        }

        result.forEach((text, index) => {
          if (list[index]) {
            list[index].item[list[index].key] = text
          }
        })

        Object.assign(this.dirty, { content: true, page: true })
        this.item.lang = lang
      } finally {
        this.translating = false
      }
    },

    use(version, clean = false) {
      Object.assign(this.item, version.data)
      this.assign(version.elements || [], Object.values(version.files || {}))

      Object.assign(this.dirty, { content: true, page: true })
      this.vhistory = false
      if (clean) this.reset()
    },

    versions(id) {
      return this.loadVersions(FETCH_PAGE_VERSIONS, id, v => {
        const elements = this.elems(v.elements || [])
        const item = {
          ...v,
          data: Object.freeze(Object.assign(safeParse(v.data), safeParse(v.aux)))
        }
        item.files = Object.freeze(this.files(v.files || [], elements))
        delete item.aux
        return Object.freeze(item)
      })
    },

    writeContext() {
      return 'page content as JSON: ' + JSON.stringify(this.item.content)
    }
  },

  watch: {
    asidePage(newAside) {
      this.aside = newAside
    },

    vhistory(val) {
      if (val) this.historyData = this.historyCurrent()
    }
  }
}
</script>

<template>
  <DetailAppBar v-bind="bar" :label="$gettext('Page')" :has-latest="!!latest">
    <template #actions>
      <v-btn
        v-if="user.can('page:chat')"
        @click="review()"
        :title="$gettext('Rate this page and suggest improvements')"
        :icon="mdiCreation"
        class="btn-review-page"
      />
      <span class="btn-translate-page" v-if="user.can('text:translate')">
        <ActionMenu :title="$gettext('Translate page')">
          <template #activator="{ props, label }">
            <v-btn
              v-bind="props"
              :title="label"
              :loading="translating"
              :icon="mdiTranslate"
            />
          </template>
          <v-list-item v-for="lang in txlocales(item.lang)" :key="lang.code">
            <v-btn
              @click="translatePage(lang.code)"
              :prepend-icon="mdiArrowRightThin"
              variant="text"
            >
              {{ lang.name }}
            </v-btn>
          </v-list-item>
        </ActionMenu>
      </span>
    </template>
  </DetailAppBar>

  <v-main class="page-details" :aria-label="$gettext('Page')">
    <v-progress-linear v-if="loading" indeterminate color="primary" />
    <v-form v-else ref="form" @submit.prevent>
      <v-tabs class="detail-tabs" fixed-tabs hide-slider v-model="tab">
        <v-tab v-if="app.urlpage" value="editor" @click="aside = editorElement ? 'editor' : ''">
          {{ $pgettext('editing interface', 'Editor') }}
        </v-tab>
        <v-tab
          value="content"
          :class="{ changed: dirty.content, error: errors.content, conflict: hasContentConflict }"
          @click="aside = 'count'"
        >
          {{ $gettext('Content') }}
        </v-tab>
        <v-tab
          value="page"
          :class="{ changed: dirty.page, error: errors.page, conflict: hasPageConflict }"
          @click="aside = asidePage"
        >
          {{ $gettext('Page') }}
        </v-tab>
        <v-tab v-if="user.can('page:metrics')" value="metrics" @click="aside = ''">
          {{ $gettext('Metrics') }}
        </v-tab>
        <v-tab v-for="(sp, key) in subpanels" :key="key" :value="'ext-' + key" @click="aside = ''">
          {{ label(sp) }}
        </v-tab>
      </v-tabs>

      <v-window v-model="tab" :touch="false">
        <v-window-item v-if="app.urlpage" value="editor">
          <PageDetailEditor
            ref="editor"
            :aside-visible="aside === 'editor' && drawer.aside"
            :save="saveConfig"
            :item="item"
            :elements="elements"
            :preview-size="previewSize"
            @change="dirty.content = true"
            @edit="editElement"
          />
        </v-window-item>

        <v-window-item value="content">
          <PageDetailContent
            ref="content"
            :item="item"
            :assets="assets"
            :changed="changed?.content"
            :elements="elements"
            @error="errors.content = $event"
            @change="dirty.content = true"
          />
        </v-window-item>

        <v-window-item value="page">
          <PageDetailItem
            ref="page"
            :item="item"
            :assets="assets"
            @update:item="pageUpdated"
            @update:aside="asidePage = $event"
            @error="errors.page = $event"
          />
        </v-window-item>

        <v-window-item v-if="user.can('page:metrics')" value="metrics">
          <PageDetailMetrics ref="metrics" :item="item" />
        </v-window-item>

        <v-window-item v-for="(sp, key) in subpanels" :key="key" :value="'ext-' + key">
          <component :is="sp.component" :item="item" :assets="assets" />
        </v-window-item>
      </v-window>
    </v-form>
  </v-main>

  <AsideMeta v-if="aside === 'meta'" :item="item" />
  <AsideCount v-if="aside === 'count'" />
  <FieldsAside
    v-if="aside === 'editor' && editorElement"
    :actions="editorActions && user.can('page:save')"
    :assets="assets"
    :element="editorElement.type === 'reference' ? elements[editorElement.refid] : editorElement"
    :preview-size="previewSize"
    :readonly="!user.can('page:save') || !!editorElement.refid"
    :save-count="savecnt"
    @add-after="editorAction('addAfter')"
    @add-before="editorAction('addBefore')"
    @change="dirty.content = true"
    @remove="editorAction('remove')"
    @update:preview-size="previewSize = $event"
  />

  <Teleport to="body">
    <ChatDialog ref="chat" v-model="chatOpen" :context="chatContext" @done="chatDone" />
    <HistoryDialog
      v-if="vhistory"
      v-model="vhistory"
      :readonly="!user.can('page:save')"
      :current="historyData"
      :load="() => versions(item.id)"
      @apply="apply"
      @use="use"
    />
    <ChangesDialog v-model="vchanged" :changed="changed"
      :targets="changeTargets"
      @resolve="dirty.page = true"
    />
  </Teleport>
</template>

<style scoped>
.detail-tabs .v-tab.conflict {
  color: color-mix(in srgb, rgb(var(--v-theme-error)) 50%, rgb(var(--v-theme-on-background)));
}
</style>
