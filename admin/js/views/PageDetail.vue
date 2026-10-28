/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import AsideCount from '../components/AsideCount.vue'
import ActionItem from '../components/ActionItem.vue'
import ActionMenu from '../components/ActionMenu.vue'
import ChatDialog from '../components/ChatDialog.vue'
import PageDetailContent from '../components/PageDetailContent.vue'
import { applyResult, assignMerged, hasUnresolved } from '../merge'
import { detailVariants } from '../variants'
import { detailBase, focusInvalid, useDetail } from '../detail'
import { FILE_FIELDS, fileMap } from '../files'
import { defineAsyncComponent, markRaw } from 'vue'
import { frozenParse, safeParse } from '../json'
import { hasTrue } from '../utils'
import { useAppStore, useDrawerStore, useSchemaStore } from '../stores'
import {
  mdiCreation,
  mdiCheck,
  mdiCheckAll,
  mdiDelete,
  mdiPlus,
  mdiSync,
  mdiTranslate
} from '@mdi/js'

const FieldsAside = defineAsyncComponent(() => import('../components/FieldsAside.vue'))
const PageDetailItem = defineAsyncComponent(() => import('../components/PageDetailItem.vue'))
const PageDetailEditor = defineAsyncComponent(() => import('../components/PageDetailEditor.vue'))

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
  query($id: ID!, $lang: String, $access: Boolean!) {
    page(id: $id, lang: $lang) {
      id
      lang
      source
      stale
      access @include(if: $access)
      has
      restricted
      variants {
        id
        lang
        source
        state
      }
      latest {
        ${PAGE_DETAIL_FIELDS}
      }
    }
  }
`

const FETCH_PAGE_VERSIONS = gql`
  ${FILE_FIELDS}
  query($id: ID!, $lang: String) {
    page(id: $id, lang: $lang) {
      id
      versions {
        ${PAGE_DETAIL_FIELDS}
      }
    }
  }
`

const SAVE_PAGE = gql`
  mutation ($id: ID!, $input: PageInput!, $latestId: ID, $lang: String, $restore: Boolean) {
    savePage(id: $id, input: $input, latestId: $latestId, lang: $lang, restore: $restore) {
      id
      stale
      latest { id published publish_at editor created_at }
      changed
    }
  }
`

export default {
  extends: detailBase,

  mixins: [detailVariants],

  components: {
    ...detailBase.components,
    ActionItem,
    ActionMenu,
    AsideCount,
    ChatDialog,
    FieldsAside,
    PageDetailItem,
    PageDetailEditor,
    PageDetailContent
  },

  provide() {
    return {
      // lets page link fields list the pages in the language of the edited variant
      pageLang: () => this.variantLang || this.item?.lang
    }
  },

  setup() {
    return {
      ...useDetail('page'),
      app: useAppStore(),
      drawer: useDrawerStore(),
      schemas: useSchemaStore(),
      mdiCreation,
      mdiCheck,
      mdiCheckAll,
      mdiDelete,
      mdiPlus,
      mdiSync,
      mdiTranslate
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
      // changes restored from an old version, which marks translations as outdated
      restored: false,
      savecnt: 0
    }
  },

  computed: {
    // instructions for the AI model, not translated like those of the element and file lists
    // the chat works on the language variant shown in the editor, the page tools use the source variant otherwise
    chatContext() {
      const lang = this.variantLang || this.item.lang

      if (!lang) {
        return `The user is viewing the page with ID "${this.item.id}". When they refer to "this page", use get-page with that ID.`
      }

      return (
        `The user is viewing the "${lang}" language variant of the page with ID "${this.item.id}". ` +
        `When they refer to "this page", use get-page with that ID and lang "${lang}". ` +
        `Rate, suggest and change only this language variant: pass lang "${lang}" to all tools working on the page, ` +
        `e.g. save-page, get-page-history and publish-page, and use the URL of this variant for tools expecting a page URL.`
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

  methods: {
    // loads the latest version into the open editor; resolves true on success so the caller
    // can defer the websocket subscription until the initial load completed
    reload() {
      return this.reloadVersion(FETCH_PAGE, this.$gettext('Error fetching page'), (page) => {
        this.latest = page.latest
        this.variantLang = page.lang || this.variantLang

        Object.assign(this.item, safeParse(this.latest?.data))
        this.item.access = page.access
        this.item.source = page.source
        this.item.stale = page.stale
        this.item.variants = page.variants || []
        this.item.has = page.has
        this.item.restricted = page.restricted

        const aux = safeParse(this.latest?.aux)
        this.item.content = aux.content ?? []
        this.item.config = aux.config ?? {}
        this.item.meta = aux.meta ?? {}

        this.assign(this.latest?.elements || [], this.latest?.files || [])
        this.latest = { id: this.latest?.id }
      }, () => !this.hasChanged, { lang: this.variantLang, access: this.user.can('page:access') })
    },

    apply(changes, version) {
      if (version) {
        this.elements = markRaw({ ...this.elems(version.elements || []), ...this.elements })
        this.assets = markRaw({ ...version.files, ...this.assets })
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
      this.markChanged('page')
      if (changes.content) this.markChanged('content')
      this.restored = true
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
        elements: Object.values(this.elements).filter(element => (item.content || []).some(block => block.refid === element.id)),
        files: markRaw(files)
      })
    },

    obsolete(content) {
      for (const entry of content) {
        if (Array.isArray(entry.files)) entry.files = entry.files.filter((id) => this.assets[id] !== undefined)
      }

      return content
    },

    accessUpdated(access) {
      this.item.access = access
      this.item.restricted = access !== null
    },

    // the maps are raw for performance, so they are replaced to update the views
    addAssets(map) {
      if (Object.keys(map).length) this.assets = markRaw({ ...this.assets, ...map })
    },

    addElements(map) {
      if (Object.keys(map).length) this.elements = markRaw({ ...this.elements, ...map })
    },

    contentUpdated(list) {
      this.item.content = list
      this.markChanged('content')
    },

    pageUpdated(event) {
      Object.assign(this.item, event)
      this.markChanged('page')
    },

    // marks the content or page tab as changed and counts the edit, so edits made while saving stay unsaved
    markChanged(key) {
      this.edits++
      this.dirty[key] = true
    },

    // content, meta and config are edited in the content tab, all other sections in the page tab
    resolved(event) {
      this.resolveChange(event)
      this.markChanged(['content', 'meta', 'config'].includes(event.section) ? 'content' : 'page')
    },

    // child components send the changes of content elements, the page owns them
    patchEntry(entry, values) {
      Object.assign(entry, values)
    },

    reset() {
      const entries = [
        ...(this.item.content || []),
        ...Object.values(this.item.meta || {}),
        ...Object.values(this.item.config || {})
      ]

      for (const entry of entries) {
        if (entry?._changed || entry?._error) {
          delete entry._changed
          delete entry._error
        }
      }

      this.$refs.page?.reset()
      this.$refs.content?.reset()

      this.dirty = {}
      this.changed = null
      this.errors = {}
      this.restored = false
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
      this.$nextTick(() => this.$refs.chat?.send(this.$gettext('Score this page 1–10 per area (content quality, readability, SEO, UX, engagement) and suggest improvements')))
    },

    save(quiet = false) {
      // the editor still holds the content of the previous language until the switch is done
      // and all saves are started by the user, also the quiet ones from publishing or leaving the page
      if (this.switching) {
        this.messages.add(this.$gettext('Please wait until the language is switched'), 'info')
        return Promise.resolve(false)
      }

      return this.saveOnce((quiet) => this.saveItem(quiet), quiet)
    },

    saveItem(quiet) {
      return this.saveWith(() => ({
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
            meta: JSON.stringify(this.clean(this.item.meta || {}, 'meta')),
            config: JSON.stringify(this.clean(this.item.config || {}, 'config')),
            content: JSON.stringify(this.clean(this.item.content || [], 'content'))
          },
          latestId: this.latest?.id,
          lang: this.variantLang,
          restore: this.restored
        }
      }), (response, edited, sent) => {
        const page = response.data?.savePage

        // a version restored while saving is sent by the next save
        if (!edited) {
          this.restored = false
        }

        if (this.isTranslation && page?.stale && !this.item.stale) {
          this.setVariantState(true)
        }
        const changed = page?.changed ? markRaw(safeParse(page.changed)) : null

        if (changed?.latest?.id || page?.latest?.id) {
          this.latest = { id: changed?.latest?.id ?? page.latest.id }
        }

        applyResult(this, changed, this.$gettext('Page saved successfully'), quiet, edited, sent)

        if (changed) {
          const aux = changed.latest?.aux || {}
          const merged = Object.fromEntries(['content', 'config', 'meta'].filter((key) => aux[key]).map((key) => [key, aux[key]]))
          assignMerged(this.item, merged, edited ? sent : null)
        }

        this.savecnt++
        this.saved(page?.latest)
      }, this.$gettext('Error saving page'))
    },

    use(version, clean = false) {
      Object.assign(this.item, version.data)
      this.assign(version.elements || [], Object.values(version.files || {}))

      this.markChanged('content')
      this.markChanged('page')
      this.vhistory = false
      // discarding unsaved changes returns to the latest version instead of an old one
      if (clean) this.reset()
      else this.restored = true
    },

    versions(id) {
      return this.loadVersions(FETCH_PAGE_VERSIONS, id, (v) => {
        const elements = this.elems(v.elements || [])
        const item = {
          ...v,
          data: Object.freeze(Object.assign(safeParse(v.data), safeParse(v.aux)))
        }
        item.files = Object.freeze(this.files(v.files || [], elements))
        delete item.aux
        return Object.freeze(item)
      }, { lang: this.variantLang })
    },

    writeContext() {
      return 'page content as JSON: ' + JSON.stringify(this.item.content)
    }
  },

  watch: {
    asidePage(newAside) {
      this.aside = newAside
    }
  }
}
</script>

<template>
  <DetailAppBar v-bind="bar" :label="$gettext('Page')" :has-latest="!!latest">
    <template #actions>
      <span class="btn-lang-switch" v-if="langs.length > 1">
        <ActionMenu :title="$gettext('Language')">
          <template #activator="{ props, label }">
            <v-btn
              v-bind="props"
              :title="label"
              :loading="switching || isTranslating"
              :append-icon="isTranslation && item.stale ? mdiSync : undefined"
            >
              {{ variantLang || item.lang }}
            </v-btn>
          </template>
          <v-list-item v-for="entry in langs" :key="entry.code">
            <v-btn
              @click="switchLang(entry)"
              :active="entry.code === variantLang"
              :disabled="entry.state === 'trashed' || (entry.state === 'missing' && !user.canLang('variant:add', entry.code))"
              :prepend-icon="{ current: mdiCheck, stale: mdiSync, trashed: mdiDelete, missing: mdiPlus }[entry.state]"
              :class="'lang-' + entry.state"
              :data-lang="entry.code"
              variant="text"
            >
              {{ entry.name }}
              <span class="lang-state">{{
                entry.source
                  ? $gettext('Source')
                  : {
                      current: $gettext('Up to date'),
                      stale: $gettext('Needs update'),
                      trashed: $gettext('Trashed'),
                      missing: $gettext('Create')
                    }[entry.state]
              }}</span>
            </v-btn>
          </v-list-item>
          <template v-if="isTranslation && item.stale && canWrite('save')">
            <v-divider />
            <ActionItem
              v-if="user.can('text:translate')"
              @click="translate()"
              :prepend-icon="mdiTranslate"
              class="btn-translate-page"
            >
              {{ $gettext('Translate') }}
            </ActionItem>
            <ActionItem @click="ignoreChanges()" :prepend-icon="mdiCheckAll" class="btn-ignore-changes">
              {{ $gettext('Ignore changes') }}
            </ActionItem>
          </template>
        </ActionMenu>
      </span>
      <v-btn
        v-if="user.can('page:chat')"
        @click="review()"
        :title="$gettext('Score this page 1–10 per area (content quality, readability, SEO, UX, engagement) and suggest improvements')"
        :icon="mdiCreation"
        class="btn-review-page"
      />
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
            @change="markChanged('content')"
            @edit="editElement"
            @add-elements="addElements"
            @update:content="contentUpdated"
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
            @change="markChanged('content')"
            @patch-entry="patchEntry"
            @add-assets="addAssets"
            @add-elements="addElements"
            @update:content="contentUpdated"
          />
        </v-window-item>

        <v-window-item value="page">
          <PageDetailItem
            ref="page"
            :item="item"
            :assets="assets"
            @patch="pageUpdated"
            @add-assets="addAssets"
            @update:access="accessUpdated"
            @update:aside="asidePage = $event"
            @error="errors.page = $event"
          />
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
    :actions="editorActions && canWrite('save')"
    :assets="assets"
    :element="editorElement.type === 'reference' ? elements[editorElement.refid] : editorElement"
    :preview-size="previewSize"
    :readonly="!canWrite('save') || !!editorElement.refid"
    :save-count="savecnt"
    @add-after="editorAction('addAfter')"
    @add-before="editorAction('addBefore')"
    @change="markChanged('content')"
    @patch-entry="patchEntry"
    @add-assets="addAssets"
    @remove="editorAction('remove')"
    @update:preview-size="previewSize = $event"
  />

  <DetailDialogs v-bind="dialogs">
    <ChatDialog ref="chat" v-model="chatOpen" :context="chatContext" @done="chatDone" />
  </DetailDialogs>
</template>

<style scoped>
.btn-lang-switch .v-btn {
  text-transform: uppercase;
}

.lang-state {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  margin-inline-start: 0.5rem;
  text-transform: none;
}

.detail-tabs .v-tab.conflict {
  color: color-mix(in srgb, rgb(var(--v-theme-error)) 50%, rgb(var(--v-theme-on-background)));
}
</style>
