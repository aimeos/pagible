/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import AsideMeta from '../components/AsideMeta.vue'
import AsideCount from '../components/AsideCount.vue'
import DetailAppBar from '../components/DetailAppBar.vue'
import PageDetailContent from '../components/PageDetailContent.vue'

const PageDetailItem = defineAsyncComponent(() => import('../components/PageDetailItem.vue'))
const PageDetailEditor = defineAsyncComponent(() => import('../components/PageDetailEditor.vue'))
import { applyResult, hasUnresolved } from '../merge'
import { publishDate, publishItem } from '../publish'
import { defineAsyncComponent, markRaw } from 'vue'
import { frozenParse, hasTrue, txlocales } from '../utils'
import { setupEcho, cleanEcho } from '../echo'
import {
  useAppStore,
  useDirtyStore,
  useSideStore,
  useUserStore,
  useMessageStore,
  useSchemaStore,
  useViewStack
} from '../stores'
import {
  mdiTranslate,
  mdiArrowRightThin
} from '@mdi/js'


const ChangesDialog = defineAsyncComponent(() => import('../components/ChangesDialog.vue'))
const HistoryDialog = defineAsyncComponent(() => import('../components/HistoryDialog.vue'))
const PageDetailMetrics = defineAsyncComponent(() => import('../components/PageDetailMetrics.vue'))

const PAGE_DETAIL_FIELDS = `
  id
  aux
  data
  published
  publish_at
  created_at
  editor
  files {
    id
    lang
    mime
    name
    path
    previews
    description
    transcription
    updated_at
    editor
  }
  elements {
    id
    type
    name
    data
    editor
    updated_at
    files {
      id
      lang
      mime
      name
      path
      previews
      description
      transcription
      updated_at
      editor
    }
  }
`

const FETCH_PAGE = gql`query($id: ID!) {
  page(id: $id) {
    id
    latest {
      ${PAGE_DETAIL_FIELDS}
    }
  }
}`

const FETCH_PAGE_VERSIONS = gql`query($id: ID!) {
  page(id: $id) {
    id
    versions {
      ${PAGE_DETAIL_FIELDS}
    }
  }
}`

const SAVE_PAGE = gql`
  mutation ($id: ID!, $input: PageInput!, $elements: [ID!], $files: [ID!], $latestId: ID) {
    savePage(id: $id, input: $input, elements: $elements, files: $files, latestId: $latestId) {
      id
      latest { id }
      changed
    }
  }
`

export default {
  components: {
    AsideMeta,
    AsideCount,
    ChangesDialog,
    DetailAppBar,
    HistoryDialog,
    PageDetailItem,
    PageDetailEditor,
    PageDetailContent,
    PageDetailMetrics
  },

  props: {
    item: { type: Object, required: true }
  },

  provide() {
    return {
      // re-provide custom methods
      write: this.writeText,
      translate: this.translateText
    }
  },

  setup() {
    const dirtyStore = useDirtyStore()
    const viewStack = useViewStack()
    const messages = useMessageStore()
    const schemas = useSchemaStore()
    const side = useSideStore()
    const user = useUserStore()
    const app = useAppStore()

    return {
      app,
      dirtyStore,
      side,
      user,
      viewStack,
      messages,
      schemas,
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
      dirty: {},
      errors: {},
      assets: {},
      elements: {},
      latest: null,
      publishAt: null,
      publishTime: null,
      publishing: false,
      translating: false,
      vhistory: false,
      changed: null,
      vchanged: false,
      destroyed: false,
      echoCleanup: null,
      echoPromise: null,
      saving: false,
      savecnt: 0,
      historyData: null
    }
  },

  computed: {
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
    this.dirtyStore.register(() => this.save(true))
    this.schemas.load()

    if (!this.item?.id || !this.user.can('page:view')) {
      return
    }

    this.$apollo
      .query({
        query: FETCH_PAGE,
        fetchPolicy: 'no-cache',
        variables: {
          id: this.item.id
        }
      })
      .then((result) => {
        if (result.errors || !result.data.page) {
          throw result
        }

        this.reset()
        this.latest = result?.data?.page?.latest

        Object.assign(this.item, JSON.parse(this.latest?.data || '{}'))

        const aux = JSON.parse(this.latest?.aux || '{}')
        this.item.content = aux.content ?? []
        this.item.config = aux.config ?? {}
        this.item.meta = aux.meta ?? {}

        this.assets = markRaw(this.files(this.latest?.files || []))
        this.elements = markRaw(this.elems(this.latest?.elements || []))
        this.item.content = this.obsolete(this.item.content)
        this.latest = { id: this.latest?.id }

        setupEcho(this, 'page', this.item.id, (event) => {
          if (!this.hasChanged && this.user.can('page:view') && event.editor !== this.user.me?.email) {
            this.latest = { id: event.versionId }
            Object.assign(this.item, event.data)

            this.item.content = event.aux?.content ?? this.item.content
            this.item.config = event.aux?.config ?? this.item.config
            this.item.meta = event.aux?.meta ?? this.item.meta
          }
        })
      })
      .catch((error) => {
        this.messages.add(this.$gettext('Error fetching page') + ':\n' + error, 'error')
        this.$log(`PageDetail::watch(item): Error fetching page`, error)
      })
  },

  beforeUnmount() {
    this.side.$reset()
    this.dirtyStore.unregister()

    this.assets = markRaw({})
    this.elements = markRaw({})
    this.destroyed = true
    this.changed = null
    this.latest = null
    this.dirty = null
    this.errors = null

    cleanEcho(this)
  },

  methods: {
    apply(changes) {
      Object.assign(this.item, changes)
      this.dirty.page = true
      if(changes.content) this.dirty.content = true
      this.vhistory = false
    },

    clean(data, type) {
      if (!data || !type) return data

      const isArray = Array.isArray(data)
      const result = isArray ? [] : {}

      for (const key in data) {
        const el = data[key]
        const cleaned = {}

        for (const k in el) {
          if (!k.startsWith('_')) {
            cleaned[k] = el[k]
          }
        }

        if (cleaned.data) {
          const fields = this.schemas[type]?.[el.type]?.fields

          if (fields) {
            const cleanedData = {}

            for (const name in cleaned.data) {
              if (fields[name]) {
                cleanedData[name] = cleaned.data[name]
              }
            }

            cleaned.data = cleanedData
          }
        }

        if (isArray) {
          result.push(cleaned)
        } else {
          result[key] = cleaned
        }
      }

      return result
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

    fileIds() {
      const files = new Set()

      for (const entry of this.item.content || []) {
        for (const id of entry.files || []) files.add(id)
      }

      for (const key in this.item.meta || {}) {
        for (const id of this.item.meta[key].files || []) files.add(id)
      }

      for (const key in this.item.config || {}) {
        for (const id of this.item.config[key].files || []) files.add(id)
      }

      return [...files]
    },

    files(entries) {
      const map = {}

      for (const entry of entries) {
        map[entry.id] = Object.freeze({
          ...entry,
          previews: frozenParse(entry.previews),
          description: frozenParse(entry.description),
          transcription: frozenParse(entry.transcription)
        })
      }

      return map
    },

    historyCurrent() {
      const item = this.item
      const fileIds = new Set(this.fileIds())
      const files = {}

      for (const key in this.assets) {
        if (fileIds.has(key)) files[key] = this.assets[key]
      }

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
        elements: this.latest?.elements || [],
        files: markRaw(files)
      })
    },

    invalidate() {
      const cache = this.$apollo.provider.defaultClient.cache
      cache.evict({ id: 'Page:' + this.item.id })
      cache.gc()
    },

    loadVersions() {
      return this.versions(this.item.id)
    },

    obsolete(content) {
      for (const entry of content) {
        if (entry.files && Array.isArray(entry.files)) {
          entry.files = entry.files.filter((id) => {
            return typeof this.assets[id] !== 'undefined'
          })
        }
      }

      return content
    },

    pageUpdated(event) {
      Object.assign(this.item, event)
      this.dirty.page = true
    },

    publish(at = null) {
      publishItem(this, 'page', {
        success: this.$gettext('Page published successfully'),
        scheduled: (d) => this.$gettext('Page scheduled for publishing at %{date}', { date: d.toLocaleDateString() }),
        error: this.$gettext('Error publishing page')
      }, at)
    },

    published() {
      this.publish(publishDate(this.publishAt, this.publishTime))
    },

    reset() {
      this.$refs.page?.reset()
      this.$refs.content?.reset()

      this.dirty = {}
      this.changed = null
      this.errors = {}
    },

    revertVersion(event) {
      this.use(event)
      this.reset()
    },

    save(quiet = false) {
      this.$refs.content?.flush()

      if (!this.user.can('page:save')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return Promise.resolve(false)
      }

      if (this.hasError) {
        this.messages.add(
          this.$gettext('There are invalid fields, please resolve the errors first'),
          'error'
        )
        return Promise.resolve(false)
      }

      if (!this.hasChanged) {
        return Promise.resolve(true)
      }

      const meta = {}
      for (const key in this.item.meta || {}) {
        meta[key] = {
          type: this.item.meta[key].type || '',
          data: this.item.meta[key].data || {},
          files: this.item.meta[key].files || []
        }
      }

      const config = {}
      for (const key in this.item.config || {}) {
        config[key] = {
          type: this.item.config[key].type || '',
          data: this.item.config[key].data || {},
          files: this.item.config[key].files || []
        }
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
              meta: JSON.stringify(this.clean(meta, 'meta')),
              config: JSON.stringify(this.clean(config, 'config')),
              content: JSON.stringify(this.clean(this.item.content, 'content'))
            },
            elements: Object.keys(this.elements),
            files: this.fileIds(),
            latestId: this.latest?.id
          }
        })
        .then((response) => {
          if (response.errors) {
            throw response.errors
          }

          const page = response.data?.savePage
          const changed = page?.changed ? markRaw(JSON.parse(page.changed)) : null

          if (changed?.latest?.id || page?.latest?.id) {
            this.latest = { id: changed?.latest?.id ?? page.latest.id }
          }

          this.$refs.history?.reset()
          applyResult(this, changed, this.$gettext('Page saved successfully'), quiet)

          if (changed) {
            const aux = changed.latest?.aux
            this.item.content = aux?.content ?? this.item.content
            this.item.config = aux?.config ?? this.item.config
            this.item.meta = aux?.meta ?? this.item.meta
          }

          this.invalidate()
          this.savecnt++

          return true
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error saving page') + ':\n' + error, 'error')
          this.$log(`PageDetail::save(): Error saving page`, error)
        })
        .finally(() => {
          this.saving = false
        })
    },

    async translatePage(lang) {
      if (!this.user.can('text:translate')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return
      }

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

      const { translate } = await import('../ai')
      translate(
        list.map((entry) => entry.text),
        lang,
        this.item.lang
      )
        .then((result) => {
          result.forEach((text, index) => {
            if (list[index]) {
              list[index].item[list[index].key] = text
            }
          })

          this.dirty['content'] = true
          this.dirty['page'] = true

          this.item.lang = lang
        })
        .finally(() => {
          this.translating = false
        })
    },

    translateText(texts, to, from = null) {
      return import('../ai').then(({ translate }) => translate(texts, to, from || this.item.lang))
    },

    update(what, value) {
      if (what === 'page') {
        Object.assign(this.item, value)
      } else {
        this[what] = value
      }

      this.dirty[what] = true
    },

    use(version) {
      Object.assign(this.item, version.data)

      this.assets = version.files
      this.elements = this.elems(version.elements || [])
      this.item.content = this.obsolete(this.item.content)

      this.dirty['content'] = true
      this.dirty['page'] = true

      this.vhistory = false
    },

    validate() {
      return Promise.all(
        [this.$refs.page?.validate(), this.$refs.content?.validate()].filter((v) => v)
      ).then((results) => {
        return results.every((result) => result)
      })
    },

    versions(id) {
      if (!this.user.can('page:view')) {
        this.messages.add(this.$gettext('Permission denied'), 'error')
        return Promise.resolve([])
      }

      if (!id) {
        return Promise.resolve([])
      }

      return this.$apollo
        .query({
          query: FETCH_PAGE_VERSIONS,
          variables: {
            id: id
          },
          fetchPolicy: 'no-cache'
        })
        .then((result) => {
          if (result.errors || !result.data.page) {
            throw result
          }

          return (result.data.page.versions || []).map((v) => {
            const item = {
              ...v,
              data: Object.freeze(Object.assign(JSON.parse(v.data || '{}'), JSON.parse(v.aux || '{}')))
            }
            item.files = Object.freeze(this.files(v.files || []))
            delete item.aux
            return Object.freeze(item)
          })
        })
        .catch((error) => {
          this.messages.add(this.$gettext('Error fetching page versions') + ':\n' + error, 'error')
          this.$log(`PageDetail::versions(): Error fetching page versions`, id, error)
        })
    },

    writeText(prompt, context = [], files = []) {
      if (!Array.isArray(context)) {
        context = [context]
      }

      context.push('page content as JSON: ' + JSON.stringify(this.item.content))
      context.push('required output language: ' + (this.item.lang || 'en'))

      return import('../ai').then(({ write }) => write(prompt, context, files))
    }
  },

  watch: {
    asidePage(newAside) {
      this.aside = newAside
    },

    hasChanged(value, old) {
      if (value !== old) this.dirtyStore.set(value)
    },

    vhistory(val) {
      if (val) this.historyData = this.historyCurrent()
    }
  }
}
</script>

<template>
  <DetailAppBar
    type="page"
    :label="$gettext('Page')"
    :name="item.name"
    :dirty="hasChanged"
    :error="hasError"
    :conflict="hasConflict"
    :changed="changed"
    :published="item.published"
    :has-latest="!!latest"
    :saving="saving"
    :publishing="publishing"
    v-model:publish-at="publishAt"
    v-model:publish-time="publishTime"
    @save="save()"
    @publish="publish()"
    @schedule="published"
    @history="vhistory = true"
    @changes="vchanged = true"
  >
    <template #actions>
      <v-menu v-if="user.can('text:translate')">
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            :title="$gettext('Translate page')"
            :loading="translating"
            :icon="mdiTranslate"
            class="btn-translate-page"
          />
        </template>
        <v-list>
          <v-list-item v-for="lang in txlocales(item.lang)" :key="lang.code">
            <v-btn
              @click="translatePage(lang.code)"
              :prepend-icon="mdiArrowRightThin"
              variant="text"
            >
              {{ lang.name }}
            </v-btn>
          </v-list-item>
        </v-list>
      </v-menu>
    </template>
  </DetailAppBar>

  <v-main class="page-details" :aria-label="$gettext('Page')">
    <v-form @submit.prevent>
      <v-tabs fixed-tabs v-model="tab">
        <v-tab v-if="app.urlpage" value="editor" @click="aside = ''">
          {{ $gettext('Editor') }}
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
      </v-tabs>

      <v-window v-model="tab" :touch="false">
        <v-window-item v-if="app.urlpage" value="editor">
          <PageDetailEditor
            :save="saveConfig"
            :item="item"
            :assets="assets"
            :elements="elements"
            @change="dirty.content = true"
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
      </v-window>
    </v-form>
  </v-main>

  <AsideMeta v-if="aside === 'meta'" :item="item" />
  <AsideCount v-if="aside === 'count'" />

  <Teleport to="body">
    <HistoryDialog
      ref="history"
      v-model="vhistory"
      :readonly="!user.can('page:save')"
      :current="historyData"
      :load="loadVersions"
      @revert="revertVersion"
      @apply="apply"
      @use="use($event)"
    />
    <ChangesDialog v-model="vchanged" :changed="changed"
      :targets="changeTargets"
      @resolve="dirty.page = true"
    />
  </Teleport>
</template>

<style scoped>
.v-tab.conflict {
  color: rgb(var(--v-theme-error));
}
</style>
