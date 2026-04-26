/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import AsideMeta from '../components/AsideMeta.vue'
import AsideCount from '../components/AsideCount.vue'
import ChangesDialog from '../components/ChangesDialog.vue'
import DetailAppBar from '../components/DetailAppBar.vue'
import HistoryDialog from '../components/HistoryDialog.vue'
import PageDetailItem from '../components/PageDetailItem.vue'
import PageDetailEditor from '../components/PageDetailEditor.vue'
import PageDetailContent from '../components/PageDetailContent.vue'
import { applyResult, hasUnresolved } from '../merge'
import { publishItem } from '../publish'
import { defineAsyncComponent } from 'vue'
import { write, translate } from '../ai'
import { txlocales } from '../utils'
import { subscribe } from '../echo'
import {
  useAppStore,
  useDirtyStore,
  useUserStore,
  useMessageStore,
  useSchemaStore,
  useViewStack
} from '../stores'
import {
  mdiTranslate,
  mdiArrowRightThin
} from '@mdi/js'


const PageDetailMetrics = defineAsyncComponent(() => import('../components/PageDetailMetrics.vue'))


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
    const user = useUserStore()
    const app = useAppStore()

    return {
      app,
      dirtyStore,
      user,
      viewStack,
      messages,
      schemas,
      mdiTranslate,
      mdiArrowRightThin,
      txlocales,
      translate
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
      echoCleanup: null,
      saving: false,
      savecnt: 0
    }
  },

  computed: {
    currentAssets() {
      const fileIds = this.fileIds()

      return Object.fromEntries(
        Object.entries(this.assets || {}).filter(([key, value]) => fileIds.includes(key))
      )
    },

    hasChanged() {
      return Object.values(this.dirty).some((entry) => entry)
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
      return Object.values(this.errors).some((entry) => entry)
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
        query: gql`query($id: ID!) {
          page(id: $id) {
            id
            latest {
              ${this.fields()}
            }
          }
        }`,
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

        this.assets = this.files(this.latest?.files || [])
        this.elements = this.elems(this.latest?.elements || [])
        this.item.content = this.obsolete(this.item.content)

        subscribe('page', this.item.id, (event) => {
          if (!this.hasChanged && this.user.can('page:view') && event.editor !== this.user.me?.email) {
            this.latest = { ...this.latest, id: event.versionId }
            Object.assign(this.item, event.data)

            this.item.content = event.aux?.content ?? this.item.content
            this.item.config = event.aux?.config ?? this.item.config
            this.item.meta = event.aux?.meta ?? this.item.meta
          }
        }).then((cleanup) => {
          this.echoCleanup = cleanup
        })
      })
      .catch((error) => {
        this.messages.add(this.$gettext('Error fetching page') + ':\n' + error, 'error')
        this.$log(`PageDetail::watch(item): Error fetching page`, error)
      })
  },

  beforeUnmount() {
    this.dirtyStore.unregister()
    this.echoCleanup?.()
  },

  methods: {
    apply(changes) {
      Object.assign(this.item, changes)
      this.dirty.page = true
      if(changes.content) this.dirty.content = true
      this.vhistory = false
    },

    clean(data, type) {
      if (data && type) {
        data = JSON.parse(JSON.stringify(data)) // deep copy

        for (const key in data) {
          const el = data[key]

          for (const k in el) {
            if (k.startsWith('_')) {
              delete el[k]
            }
          }

          for (const name in el.data || {}) {
            if (!this.schemas[type]?.[el.type]?.fields?.[name]) {
              delete el.data[name]
            }
          }
        }
      }

      return data
    },

    elems(entries) {
      const map = {}

      for (const entry of entries) {
        map[entry.id] = {
          ...entry,
          data: JSON.parse(entry.data || '{}'),
          files: Object.values(this.files(entry.files || []))
        }
      }

      return map
    },

    fields() {
      return `id
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
              }`
    },

    fileIds() {
      const files = []

      for (const entry of this.item.content || []) {
        files.push(...(entry.files || []))
      }

      for (const key in this.item.meta || {}) {
        files.push(...(this.item.meta[key].files || []))
      }

      for (const key in this.item.config || {}) {
        files.push(...(this.item.config[key].files || []))
      }

      return files.filter((id, idx, self) => {
        return self.indexOf(id) === idx
      })
    },

    files(entries) {
      const map = {}

      for (const entry of entries) {
        map[entry.id] = {
          ...entry,
          previews: JSON.parse(entry.previews || '{}'),
          description: JSON.parse(entry.description || '{}'),
          transcription: JSON.parse(entry.transcription || '{}')
        }
      }

      return map
    },

    invalidate() {
      const cache = this.$apollo.provider.defaultClient.cache
      cache.evict({ id: 'Page:' + this.item.id })
      cache.gc()
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
      const at = new Date(this.publishAt)

      if (this.publishTime) {
        const [hours, minutes] = this.publishTime.split(':').map(Number)
        at.setHours(hours, minutes, 0, 0)
      }

      this.publish(at)
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
          mutation: gql`
            mutation ($id: ID!, $input: PageInput!, $elements: [ID!], $files: [ID!], $latestId: ID) {
              savePage(id: $id, input: $input, elements: $elements, files: $files, latestId: $latestId) {
                id
                latest { id }
                changed
              }
            }
          `,
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
          const changed = page?.changed ? JSON.parse(page.changed) : null

          if (changed?.latest?.id || page?.latest?.id) {
            this.latest = { ...this.latest, id: changed?.latest?.id ?? page.latest.id }
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

    translatePage(lang) {
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

      this.translate(
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
      return this.translate(texts, to, from || this.item.lang)
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
          query: gql`query($id: ID!) {
            page(id: $id) {
              id
              versions {
                ${this.fields()}
              }
            }
          }`,
          variables: {
            id: id
          }
        })
        .then((result) => {
          if (result.errors || !result.data.page) {
            throw result
          }

          return (result.data.page.versions || []).map((v) => {
            const item = {
              ...v,
              data: Object.assign(JSON.parse(v.data || '{}'), JSON.parse(v.aux || '{}'))
            }
            item.files = this.files(v.files || [])
            delete item.aux
            return item
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

      return write(prompt, context, files)
    }
  },

  watch: {
    asidePage(newAside) {
      this.aside = newAside
    },

    hasChanged(value) {
      this.dirtyStore.set(value)
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
            :save="{ fcn: save, count: savecnt }"
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
      :current="{
        data: {
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
          meta: clean(item.meta, 'meta'),
          config: clean(item.config, 'config'),
          content: clean(item.content, 'content')
        },
        elements: latest?.elements || [],
        files: currentAssets
      }"
      :load="() => versions(item.id)"
      @revert="revertVersion"
      @apply="apply"
      @use="use($event)"
    />
    <ChangesDialog v-model="vchanged" :changed="changed"
      :targets="{ data: item, meta: item.meta, config: item.config, content: item.content }"
      @resolve="dirty.page = true"
    />
  </Teleport>
</template>

<style scoped>
.v-tab.conflict {
  color: rgb(var(--v-theme-error));
}
</style>
