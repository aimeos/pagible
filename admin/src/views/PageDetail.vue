<script>
  import gql from 'graphql-tag'
  import AsideMeta from '../components/AsideMeta.vue'
  import AsideCount from '../components/AsideCount.vue'
  import HistoryDialog from '../components/HistoryDialog.vue'
  import PageDetailItem from '../components/PageDetailItem.vue'
  import PageDetailContent from '../components/PageDetailContent.vue'
  import PageDetailEditor from '../components/PageDetailEditor.vue'
  import { useAuthStore, useDrawerStore, useLanguageStore, useMessageStore, useSchemaStore } from '../stores'


  export default {
    components: {
      AsideMeta,
      AsideCount,
      HistoryDialog,
      PageDetailItem,
      PageDetailContent,
      PageDetailEditor
    },

    inject: ['closeView', 'compose', 'translate', 'txlocales'],

    props: {
      'item': {type: Object, required: true}
    },

    provide() {
      return { // re-provide custom methods
        compose: this.composeText,
        translate: this.translateText
      }
    },

    setup() {
      const languages = useLanguageStore()
      const messages = useMessageStore()
      const schemas = useSchemaStore()
      const drawer = useDrawerStore()
      const auth = useAuthStore()

      return { auth, drawer, languages, messages, schemas }
    },

    data: () => ({
      tab: 'editor',
      aside: '',
      asidePage: 'meta',
      changed: {},
      errors: {},
      assets: {},
      elements: {},
      latest: null,
      pubmenu: null,
      publishAt: null,
      translating: false,
      vhistory: false,
      savecnt: 0,
    }),

    computed: {
      currentAssets() {
        const fileIds = this.fileIds()

        return Object.fromEntries(
          Object.entries(this.assets || {}).filter(([key, value]) => fileIds.includes(key))
        )
      },


      hasChanged() {
        return Object.values(this.changed).some(entry => entry)
      },

      hasError() {
        return Object.values(this.errors).some(entry => entry)
      },

      langs() {
        const list = []
        const supported = [
          'ar', 'bg', 'cs', 'da', 'de', 'el', 'en', 'en-GB', 'en_US', 'es', 'et', 'fi', 'fr',
          'he', 'hu', 'id', 'it', 'ja', 'ko', 'lt', 'lv', 'nb', 'nl', 'pl', 'pt', 'pt-BR',
          'ro', 'ru', 'sk', 'sl', 'sv', 'th', 'tr', 'uk', 'vi', 'zh', 'zh-HANS', 'zh-HANT'
        ]

        Object.entries(this.languages.available).forEach(pair => {
          if(supported.includes(pair[0]) && pair[0] !== this.item.lang) {
            list.push({code: pair[0], name: pair[1]})
          }
        })

        return list
      },
    },

    created() {
      this.$options._compose = this.compose

      if(!this.item?.id || !this.auth.can('page:view')) {
        return
      }

      this.$apollo.query({
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
      }).then(result => {
        if(result.errors || !result.data.page) {
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
      }).catch(error => {
        this.messages.add(this.$gettext('Error fetching page'), 'error')
        this.$log(`PageDetail::watch(item): Error fetching page`, error)
      })
    },

    methods: {
      clean(data, type) {
        if(data && type) {
          data = JSON.parse(JSON.stringify(data)) // deep copy

          for(const key in data) {
            const el = data[key]

            for(const k in el) {
              if(k.startsWith('_')) {
                delete el[k]
              }
            }

            for(const name in el.data || {}) {
              if(!this.schemas[type]?.[el.type]?.fields?.[name]) {
                delete el.data[name]
              }
            }
          }
        }

        return data
      },


      composeText(prompt, context = [], files = []) {
        if(!this.$options._compose) {
          return Promise.reject(new Error('Compose method is not available in PageDetail component'))
        }

        if(!Array.isArray(context)) {
          context = [context]
        }

        context.push('page content as JSON: ' + JSON.stringify(this.item.content))
        context.push('required output language: ' + (this.item.lang || 'en'))

        return this.$options._compose(prompt, context, files)
      },


      elems(entries) {
        const map = {}

        for(const entry of entries) {
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

        for(const entry of (this.item.content || [])) {
          files.push(...(entry.files || []))
        }

        for(const key in (this.item.meta || {})) {
          files.push(...(this.item.meta[key].files || []))
        }

        for(const key in (this.item.config || {})) {
          files.push(...(this.item.config[key].files || []))
        }

        return files.filter((id, idx, self) => {
          return self.indexOf(id) === idx
        })
      },


      files(entries) {
        const map = {}

        for(const entry of entries) {
          map[entry.id] = {
            ...entry,
            previews: JSON.parse(entry.previews || '{}'),
            description: JSON.parse(entry.description || '{}'),
            transcription: JSON.parse(entry.transcription || '{}'),
          }
        }

        return map
      },


      invalidate() {
        const cache = this.$apollo.provider.defaultClient.cache
        cache.evict({id: 'Page:' + this.item.id})
        cache.gc()
      },


      obsolete(content) {
        for(const entry of content) {
          if(entry.files && Array.isArray(entry.files)) {
            entry.files = entry.files.filter(id => {
              return typeof this.assets[id] !== 'undefined'
            })
          }
        }

        return content
      },


      publish(at = null) {
        if(!this.auth.can('page:publish')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        this.save(true).then(valid => {
          if(!valid) {
            return
          }

          this.$apollo.mutate({
            mutation: gql`mutation ($id: [ID!]!, $at: DateTime) {
              pubPage(id: $id, at: $at) {
                id
              }
            }`,
            variables: {
              id: [this.item.id],
              at: at?.toISOString()?.substring(0, 19)?.replace('T', ' ')
            }
          }).then(response => {
            if(response.errors) {
              throw response.errors
            }

            if(!at) {
              this.item.published = true
              this.messages.add(this.$gettext('Page published successfully'), 'success')
            } else {
              this.item.publish_at = at
              this.messages.add(this.$gettext('Page scheduled for publishing at %{date}', {date: at.toLocaleDateString()}), 'info')
            }

            this.closeView()
          }).catch(error => {
            this.messages.add(this.$gettext('Error publishing page'), 'error')
            this.$log(`PageDetail::publish(): Error publishing page`, at, error)
          })
        })
      },


      reset() {
        this.$refs.page?.reset()
        this.$refs.content?.reset()

        this.changed = {}
        this.errors = {}
      },


      save(quiet = false) {
        if(!this.auth.can('page:save')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return Promise.resolve(false)
        }

        if(!this.hasChanged) {
          return Promise.resolve(true)
        }

        return this.validate().then(valid => {
          if(!valid) {
            this.messages.add(this.$gettext('There are invalid fields, please resolve the errors first'), 'error')
            return valid
          }

          const meta = {}
          for(const key in (this.item.meta || {})) {
            meta[key] = {
              type: this.item.meta[key].type || '',
              data: this.item.meta[key].data || {},
              files: this.item.meta[key].files || [],
            }
          }

          const config = {}
          for(const key in (this.item.config || {})) {
            config[key] = {
              type: this.item.config[key].type || '',
              data: this.item.config[key].data || {},
              files: this.item.config[key].files || [],
            }
          }

          return this.$apollo.mutate({
            mutation: gql`mutation ($id: ID!, $input: PageInput!, $elements: [ID!], $files: [ID!]) {
              savePage(id: $id, input: $input, elements: $elements, files: $files) {
                id
              }
            }`,
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
                meta: JSON.stringify(this.clean(meta, 'meta')),
                config: JSON.stringify(this.clean(config, 'config')),
                content: JSON.stringify(this.clean(this.item.content, 'content'))
              },
              elements: Object.keys(this.elements),
              files: this.fileIds(),
            }
          }).then(response => {
            if(response.errors) {
              throw response.errors
            }

            this.item.published = false
            this.$refs.history?.reset()
            this.reset()

            if(!quiet) {
              this.messages.add(this.$gettext('Page saved successfully'), 'success')
            }

            this.invalidate()
            this.savecnt++

            return true
          }).catch(error => {
            this.messages.add(this.$gettext('Error saving page'), 'error')
            this.$log(`PageDetail::save(): Error saving page`, error)
          })
        })
      },


      translatePage(lang) {
        if(!this.schemas.content) {
          this.messages.add(this.$gettext('No page schema for "content" found'), 'error')
          return
        }

        const allowed = ['text', 'markdown', 'plaintext', 'string']
        const list = [
          {item: this.item, key: 'title', text: this.item.title},
          {item: this.item, key: 'name', text: this.item.name},
          {item: this.item, key: 'path', text: this.item.path}
        ]

        for(const el of Object.values(this.item.meta)) {
          for(const name in el.data) {
            const fieldtype = this.schemas.meta[el.type]?.fields?.[name]?.type

            if(el.data[name] && allowed.includes(fieldtype)) {
              list.push({item: el.data, key: name, text: el.data[name]})
            }
          }
        }

        this.item.content.forEach(el => {
          for(const name in el.data) {
            const fields = this.schemas.content[el.type]?.fields
            const fieldtype = fields?.[name]?.type

            if(fieldtype === 'items') {
              for(const idx in el.data[name]) {
                const item = el.data[name][idx]

                for(const key in item) {
                  if(allowed.includes(fields[name]?.item?.[key]?.type)) {
                    list.push({item: item, key: key, text: item[key]})
                  }
                }
              }
            } else if(el.type !== 'code' && el.data[name] && allowed.includes(fieldtype)) {
              list.push({item: el.data, key: name, text: el.data[name]})
            }
          }
        })

        this.translating = true

        this.translate(list.map(entry => entry.text), lang, this.item.lang).then(result => {
          result.forEach((text, index) => {
            if(list[index]) {
              list[index].item[list[index].key] = text
            }
          })

          this.changed['content'] = true
          this.changed['page'] = true

          this.item.lang = lang
        }).finally(() => {
          this.translating = false
        })
      },


      translateText(texts, to, from = null) {
        return this.translate(texts, to, from || this.item.lang)
      },


      update(what, value) {
        if(what === 'page') {
          Object.assign(this.item, value)
        } else {
          this[what] = value
        }

        this.changed[what] = true
      },


      use(version) {
        Object.assign(this.item, version.data)

        this.assets = version.files
        this.elements = this.elems(version.elements || [])
        this.item.content = this.obsolete(this.item.content)

        this.changed['content'] = true
        this.changed['page'] = true

        this.vhistory = false
      },


      validate() {
        return Promise.all([
          this.$refs.page?.validate(),
          this.$refs.content?.validate()
        ].filter(v => v)).then(results => {
          return results.every(result => result)
        })
      },


      versions(id) {
        if(!this.auth.can('page:view')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return Promise.resolve([])
        }

        if(!id) {
          return Promise.resolve([])
        }

        return this.$apollo.query({
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
        }).then(result => {
          if(result.errors || !result.data.page) {
            throw result
          }

          return (result.data.page.versions || []).map(v => {
            const item = {...v, data: Object.assign(JSON.parse(v.data || '{}'), JSON.parse(v.aux || '{}'))}
            item.files = this.files(v.files || [])
            delete item.aux
            return item
          }).reverse() // latest versions first
        }).catch(error => {
          this.messages.add(this.$gettext('Error fetching page versions'), 'error')
          this.$log(`PageDetail::versions(): Error fetching page versions`, id, error)
        })
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
  <v-app-bar :elevation="0" density="compact">
    <template v-slot:prepend>
      <v-btn
        @click="closeView()"
        :title="$gettext('Back to list view')"
        icon="mdi-keyboard-backspace"
      />
    </template>

    <v-app-bar-title>
      <div class="app-title">
        {{ $gettext('Page') }}: {{ item.name }}
      </div>
    </v-app-bar-title>

    <template v-slot:append>
      <v-menu>
        <template #activator="{ props }">
          <v-btn v-bind="props"
            :title="$gettext('Translate page')"
            :loading="translating"
            icon="mdi-translate"
          />
        </template>
        <v-list>
          <v-list-item v-for="lang in txlocales(item.lang)" :key="lang.code">
            <v-btn
              @click="translatePage(lang.code)"
              prepend-icon="mdi-arrow-right-thin"
              variant="text">
              {{ lang.name }}
            </v-btn>
          </v-list-item>
        </v-list>
      </v-menu>

      <v-btn
        @click="vhistory = true"
        :class="{hidden: item.published && !hasChanged && !latest}"
        icon="mdi-history"
        class="no-rtl"
      ></v-btn>

      <v-btn
        @click="save()"
        :class="{error: hasError}" class="menu-save"
        :disabled="!hasChanged || hasError || !auth.can('page:save')"
        variant="text"
      >{{ $gettext('Save') }}</v-btn>

      <v-menu v-model="pubmenu" :close-on-content-click="false">
        <template #activator="{ props }">
          <v-btn-group class="menu-publish" variant="text">
            <v-btn
              @click="publish()"
              :class="{error: hasError}" class="button"
              :disabled="item.published && !hasChanged || hasError || !auth.can('page:publish')"
            >{{ $gettext('Publish') }}</v-btn>
            <v-btn v-bind="props"
              :class="{error: hasError}" class="icon"
              :disabled="item.published && !hasChanged || hasError || !auth.can('page:publish')"
              :title="$gettext('Schedule publishing')"
              icon="mdi-menu-down"
            />
          </v-btn-group>
        </template>
        <div class="menu-content">
          <v-date-picker v-model="publishAt" hide-header show-adjacent-months />
          <v-btn
            @click="publish(publishAt); pubmenu = false"
            :disabled="!publishAt || hasError"
            :color="publishAt ? 'primary' : ''"
            variant="flat"
          >{{ $gettext('Publish') }}</v-btn>
        </div>
      </v-menu>

      <v-btn
        @click.stop="drawer.toggle('aside')"
        :title="$gettext('Toggle side menu')"
        :icon="drawer.aside ? 'mdi-chevron-right' : 'mdi-chevron-left'"
      />
    </template>
  </v-app-bar>

  <v-main class="page-details">
    <v-form @submit.prevent>
      <v-tabs fixed-tabs v-model="tab">
        <v-tab value="editor"
          @click="aside = ''">
          {{ $gettext('Editor') }}
        </v-tab>
        <v-tab value="content"
          :class="{changed: changed.content, error: errors.content}"
          @click="aside = 'count'">
          {{ $gettext('Content') }}
        </v-tab>
        <v-tab value="page"
          :class="{changed: changed.page, error: errors.page}"
          @click="aside = asidePage">
          {{ $gettext('Page') }}
        </v-tab>
      </v-tabs>

      <v-window v-model="tab">

        <v-window-item value="editor">
          <PageDetailEditor
            :save="{fcn: save, count: savecnt}"
            :item="item"
            :assets="assets"
            :elements="elements"
            @change="changed.content = true"
          />
        </v-window-item>

        <v-window-item value="content">
          <PageDetailContent ref="content"
            :item="item"
            :assets="assets"
            :elements="elements"
            @error="errors.content = $event"
            @change="changed.content = true"
          />
        </v-window-item>

        <v-window-item value="page">
          <PageDetailItem ref="page"
            :item="item"
            :assets="assets"
            @update:item="Object.assign(item, $event); changed.page = true"
            @update:aside="asidePage = $event"
            @error="errors.page = $event"
          />
        </v-window-item>

      </v-window>
    </v-form>
  </v-main>

  <AsideMeta v-if="aside === 'meta'" :item="item" />
  <AsideCount v-if="aside === 'count'" />

  <Teleport to="body">
    <HistoryDialog ref="history"
      v-model="vhistory"
      :current="{
        data: {
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
          content: clean(item.content, 'content'),
        },
        elements: latest?.elements || [],
        files: currentAssets
      }"
      :load="() => versions(item.id)"
      @use="use($event)"
      @revert="use($event); reset()"
    />
  </Teleport>
</template>

<style scoped>
  .v-toolbar-title {
    margin-inline-start: 0;
  }
</style>
