<script>
  import gql from 'graphql-tag'
  import Fields from './Fields.vue'
  import SchemaDialog from './SchemaDialog.vue'
  import { VueDraggable } from 'vue-draggable-plus'
  import { toMarkdown } from 'mdast-util-to-markdown'
  import { fromMarkdown } from 'mdast-util-from-markdown'
  import { useAuthStore, useClipboardStore, useMessageStore, useSchemaStore, useSideStore } from '../stores'
  import { uid } from '../utils'

  export default {
    components: {
      Fields,
      SchemaDialog,
      VueDraggable
    },

    props: {
      'item': {type: Object, required: true},
      'assets': {type: Object, required: true},
      'content': {type: Array, required: true},
      'elements': {type: Object, required: true},
      'section': {type: String, default: 'main'}
    },

    emits: ['error', 'update:content'],

    data: () => ({
      panel: [],
      menu: {},
      index: null,
      checked: false,
      vchange: false,
      vschemas: false,
      currentPage: 1,
      lastPage: 1,
    }),

    setup() {
      const clipboard = useClipboardStore()
      const messages = useMessageStore()
      const schemas = useSchemaStore()
      const side = useSideStore()
      const auth = useAuthStore()

      return { auth, clipboard, side, messages, schemas }
    },

    computed: {
      changed() {
        return this.content.some(el => el._changed)
      },


      isChecked() {
        return this.content.some(el => el._checked)
      },
    },

    methods: {
      add(item, idx) {
        let entry = {id: uid(), group: this.section}

        if(item.id) {
          this.elements[item.id] = item
          entry = Object.assign(entry, {type: 'reference', refid: item.id})
        } else {
          entry = Object.assign(entry, {type: item.type, data: {}})
        }

        if(idx !== null) {
          this.content.splice(idx, 0, entry)
          this.panel.push(this.panel.includes(idx) ? idx + 1 : idx)
        } else {
          this.content.push(entry)
          this.panel.push(this.content.length - 1)
        }

        this.$emit('update:content', this.content)
        this.vschemas = false
        this.store()
      },


      change(idx) {
        if(!this.content[idx]) {
          this.messages.add(this.$gettext('Content element not found'), 'error')
          return
        }

        this.index = idx
        this.vchange = true
      },


      changeTo(item, idx) {
        if(!this.content[idx]) {
          this.messages.add(this.$gettext('Content element not found'), 'error')
          return
        }

        this.content[idx]._error = false
        this.content[idx].type = item.type
        this.vchange = false

        this.validate().then(val => {
          this.$emit('update:content', this.content)
          this.$emit('error', !val)
          this.store()
        })
      },


      copy(idx) {
        const list = []

        if(idx === undefined) {
          for(let i = this.content.length - 1; i >= 0; i--) {
            if(this.content[i]._checked) {
              const entry = JSON.parse(JSON.stringify(this.content[i]))
              entry._checked = false
              entry['id'] = null
              list.push(entry)
            }
          }
        } else {
          const entry = JSON.parse(JSON.stringify(this.content[idx]))
          entry._checked = false
          entry['id'] = null
          list.push(entry)
        }

        this.clipboard.set('page-content', list.reverse())
      },


      createMarkdown(el) {
        if(el.type === 'text') {
          return el.data.text || ''
        } else if(el.type === 'code') {
          return `\`\`\`${el.data.lang || ''}\n${el.data.text || ''}\n\`\`\``
        } else if(el.type === 'heading') {
          return `${'#'.repeat(Number(el.data.level) || 1)} ${el.data.title || ''}`
        }
        return ''
      },


      cut(idx) {
        const list = []

        if(idx === undefined) {
          for(let i = this.content.length - 1; i >= 0; i--) {
            if(this.content[i]._checked) {
              const entry = JSON.parse(JSON.stringify(this.content[i]))
              this.content.splice(i, 1)
              entry['id'] = null
              list.push(entry)
            }
          }
        } else {
          const entry = JSON.parse(JSON.stringify(this.content[idx]))
          this.content.splice(idx, 1)
          entry['id'] = null
          list.push(entry)
        }

        this.clipboard.set('page-content', list.reverse())
        this.$emit('update:content', this.content)
        this.store()
      },


      error(el, value) {
        el._error = value
        this.$emit('error', this.content.some(el => el._error))
        this.store()
      },


      fields(type) {
        if(!this.schemas.content[type]?.fields) {
          console.warn(`No definition of fields for "${type}" schemas`)
          return []
        }

        return this.schemas.content[type]?.fields
      },


      insert(idx) {
        this.index = idx
        this.vschemas = true
      },


      merge() {
        let idx = 0
        const entries = []

        for(let i = this.content.length - 1; i >= 0; i--) {
          if(this.content[i]._checked && ['text', 'code', 'heading'].includes(this.content[i].type)) {
            entries.push(this.content[i])
            this.content.splice(i, 1)
            idx = i
          }
        }

        if(entries.length === 0) {
          return
        }

        const entry = entries.reverse().reduce((acc, el) => {
          acc.data.text += this.createMarkdown(el) + '\n\n'
          return acc
        }, {id: uid(), group: this.section, type: 'text', data: {text: ''}})

        this.content.splice(idx, 0, entry)
        this.$emit('update:content', this.content)
        this.store()
      },


      paste(idx = null) {
        if(idx === null) {
          idx = this.content.length
        }

        const entries = (this.clipboard.get('page-content') || []).map(el => {
          el.group = this.section
          return el
        })

        this.content.splice(idx, 0, ...entries)
        this.$emit('update:content', this.content)
        this.store()
      },


      purge() {
        for(let i = this.content.length - 1; i >= 0; i--) {
          if(this.content[i]._checked) {
            this.content.splice(i, 1)
          }
        }

        this.$emit('error', this.content.some(el => el._error))
        this.$emit('update:content', this.content)
        this.checked = false
        this.store()
      },


      remove(idx) {
        this.content.splice(idx, 1)
        this.$emit('error', this.content.some(el => el._error))
        this.$emit('update:content', this.content)
        this.store()
      },


      reset() {
        this.content.forEach(el => {
          delete el._changed
          delete el._error
        })

        this.store()
      },


      search(term) {
        if(term) {
          term = term.toLocaleLowerCase().trim()

          this.content.forEach(el => {
            const item = (el.type === 'reference') ? this.elements[el.refid] || {} : el
            el._hide = !JSON.stringify(Object.values(item?.data || {})).toLocaleLowerCase().includes(term)
          })
        }
      },


      share(idx) {
        if(!this.auth.can('element:add')) {
          this.messages.add(this.$gettext('Permission denied'), 'error')
          return
        }

        const entry = this.content[idx]

        if(!entry) {
          this.messages.add(this.$gettext('Element not found'), 'error')
          return
        }

        if(entry.type === 'reference') {
          this.messages.add(this.$gettext('Element is already shared'), 'error')
          return
        }

        this.$apollo.mutate({
          mutation: gql`
            mutation($input: ElementInput!, $files: [ID!]) {
              addElement(input: $input, files: $files) {
                id
                type
                lang
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
                  updated_at
                  editor
                }
              }
            }
          `,
          variables: {
            input: {
              type: entry.type,
              lang: this.item.lang,
              name: this.title(entry),
              data: JSON.stringify(entry.data || {}),
            },
            files: entry.files?.filter((fileid, idx, self) => {
              return self.indexOf(fileid) === idx
            }) || []
          }
        }).then(result => {
          if(result.errors) {
            throw result.errors
          }

          const element = result.data.addElement

          for(const file of element.files || []) {
            file.previews = JSON.parse(file.previews || '{}')
            this.assets[file.id] = file
          }

          element.data = JSON.parse(element.data)
          element.files = element.files.map(file => file.id)

          this.elements[element.id] = element
          this.content[idx] = {id: uid(), group: this.section, type: 'reference', refid: element.id}
          this.$emit('update:content', this.content)
          this.store()
        }).catch(error => {
          this.messages.add(this.$gettext('Unable to make element shared'), 'error')
          this.$log(`PageDetailContentList::share(): Error making element shared`, idx, error)
        })
      },


      shown(el) {
        const valid = this.side.shown('state', 'valid')
        const error = this.side.shown('state', 'error')
        const changed = this.side.shown('state', 'changed')

        return !el._hide && this.side.shown('type', el.type) && (
          error && el._error || changed && el._changed || valid && !el._error && !el._changed
        )
      },


      split(idx) {
        if(!this.content[idx]) {
          this.messages.add(this.$gettext('Not available for this content element'), 'error')
          return
        }

        const list = []
        const ast = fromMarkdown(this.content[idx].data?.text || '')

        for(const node of ast.children) {
          switch(node.type) {
            case 'code': {
              list.push({id: uid(), type: 'code', group: this.section, data: {lang: node.lang || null, text: node.value}})
              break
            }
            case 'heading': {
              const text = node.children.map(child => child.value).join('')
              list.push({id: uid(), type: 'heading', group: this.section, data: {title: text.trim(), level: String(node.depth) }})
              break
            }
            case 'table': {
              const rows = node.children.map(row =>
                row.children.map(cell =>
                  cell.children.map(c => c.value || '').join('')
                ).join(';')
              ).join('\n')
              list.push({id: uid(), type: 'table', group: this.section, data: {text: rows.trim()}})
              break
            }
            default: {
              // Convert unhandled node types back to raw Markdown
              list.push({id: uid(), type: 'text', group: this.section, data: {text: toMarkdown(node)}})
            }
          }
        }

        this.content.splice(idx, 1, ...list)
        this.$emit('error', this.content.some(el => el._error))
        this.$emit('update:content', this.content)
        this.store()
      },


      store(isVisible = true) {
        if(!isVisible) {
          return
        }

        const types = {}
        const state = {}

        this.content.forEach(el => {
          if(el.type) {
            types[el.type] = (types[el.type] || 0) + 1
          }
          if(!el._changed && !el._error) {
            state['valid'] = (state['valid'] || 0) + 1
          }
          if(el._changed) {
            state['changed'] = (state['changed'] || 0) + 1
          }
          if(el._error) {
            state['error'] = (state['error'] || 0) + 1
          }
        })

        return this.side.store = {type: types, state: state}
      },


      title(el) {
        return (el.data?.title || el.data?.text || Object.values(el.data || {})
          .map(v => v && typeof v !== 'object' && typeof v !== 'boolean' ? v : null)
          .filter(v => !!v)
          .join(' - '))
          .substring(0, 50) || this.$pgettext('st', el.type) || ''
      },


      toggle() {
        this.content.forEach(el => {
          if(this.shown(el)) {
            el._checked = !el._checked
          }
        })
      },


      unshare(idx) {
        if(!this.content[idx]) {
          this.messages.add(this.$gettext('Content element not found'), 'error')
          return
        }

        const entry = this.content[idx]

        if(entry.type !== 'reference' || !this.elements[entry.refid]) {
          this.messages.add(this.$gettext('Element is not shared'), 'error')
          return
        }

        for(const file of this.elements[entry.refid].files || []) {
          this.assets[file.id] = file
        }

        this.content[idx].type = this.elements[entry.refid].type || null
        this.content[idx].data = this.elements[entry.refid].data || {}
        delete this.content[idx].refid

        this.$emit('update:content', this.content)
        this.store()
      },


      update(el) {
        el._changed = true
        el.group = this.section

        this.$emit('error', this.content.some(el => el._error))
        this.$emit('update:content', this.content)
        this.store()
      },


      validate() {
        const list = []

        this.$refs.field?.forEach(field => {
          list.push(field.validate())
        })

        return Promise.all(list).then(result => {
          return result.every(r => r)
        });
      }
    }
  }
</script>

<template>
  <div v-observe-visibility="store">

    <div class="header">
      <div v-if="auth.can('page:save')" class="bulk">
        <v-checkbox-btn v-model="checked" @click.stop="toggle()" />
        <v-menu location="bottom left">
          <template v-slot:activator="{ props }">
            <v-btn v-bind="props"
              :disabled="!isChecked"
              append-icon="mdi-menu-down"
              variant="text"
            >{{ $gettext('Actions') }}</v-btn>
          </template>
          <v-list>
            <v-list-item v-if="isChecked">
              <v-btn prepend-icon="mdi-delete" variant="text" @click="purge()">{{ $gettext('Delete') }}</v-btn>
            </v-list-item>
            <v-list-item v-if="isChecked">
              <v-btn prepend-icon="mdi-content-copy" variant="text" @click="copy()">{{ $gettext('Copy') }}</v-btn>
            </v-list-item>
            <v-list-item v-if="isChecked">
              <v-btn prepend-icon="mdi-content-cut" variant="text" @click="cut()">{{ $gettext('Cut') }}</v-btn>
            </v-list-item>
            <v-list-item v-if="clipboard.get('page-content')">
              <v-btn prepend-icon="mdi-content-paste" variant="text" @click="paste()">{{ $gettext('Paste') }}</v-btn>
            </v-list-item>
            <v-list-item v-if="isChecked">
              <v-btn prepend-icon="mdi-set-merge" variant="text" @click="merge()">{{ $gettext('Merge') }}</v-btn>
            </v-list-item>
          </v-list>
        </v-menu>
      </div>

      <v-text-field
        @click:clear="search('')"
        @input="search($event.target.value)"
        :label="$gettext('Search for')"
        prepend-inner-icon="mdi-magnify"
        variant="underlined"
        class="search"
        clearable
        hide-details
      />
    </div>

    <v-expansion-panels class="list" v-model="panel" elevation="0" multiple>
      <VueDraggable
        @update:modelValue="$emit('update:content', $event)"
        :disabled="panel.length || !auth.can('page:save')"
        :modelValue="content"
        :forceFallback="true"
        fallbackTolerance="10"
        draggable=".content"
        group="content">

        <v-expansion-panel v-for="(el, idx) in content" :key="idx" v-show="shown(el)" class="content" :class="{changed: el._changed, error: el._error}">
          <v-expansion-panel-title expand-icon="mdi-pencil">
            <v-checkbox-btn v-if="auth.can('page:save')" @click.stop="el._checked = !el._checked" />

            <v-menu v-if="auth.can('page:save')">
              <template v-slot:activator="{ props }">
                <v-btn v-bind="props"
                  :title="$gettext('Actions')"
                  icon="mdi-dots-vertical"
                  variant="text"
                />
              </template>
              <v-list>
                <v-list-item v-if="!el._error">
                  <v-btn prepend-icon="mdi-content-copy" variant="text" @click="copy(idx)">{{ $gettext('Copy') }}</v-btn>
                </v-list-item>
                <v-list-item v-if="!el._error">
                  <v-btn prepend-icon="mdi-content-cut" variant="text" @click="cut(idx)">{{ $gettext('Cut') }}</v-btn>
                </v-list-item>
                <v-list-item>
                  <v-btn prepend-icon="mdi-delete" variant="text" @click="remove(idx)">{{ $gettext('Delete') }}</v-btn>
                </v-list-item>

                <v-divider></v-divider>

                <v-list-item v-if="clipboard.get('page-content')">
                  <v-btn prepend-icon="mdi-arrow-up" variant="text" @click="paste(idx)">{{ $gettext('Paste before') }}</v-btn>
                </v-list-item>
                <v-list-item v-if="clipboard.get('page-content')">
                  <v-btn prepend-icon="mdi-arrow-down" variant="text" @click="paste(idx + 1)">{{ $gettext('Paste after') }}</v-btn>
                </v-list-item>
                <v-list-item>
                  <v-btn prepend-icon="mdi-arrow-up" variant="text" @click="insert(idx)">{{ $gettext('Insert before') }}</v-btn>
                </v-list-item>
                <v-list-item>
                  <v-btn prepend-icon="mdi-arrow-down" variant="text" @click="insert(idx + 1)">{{ $gettext('Insert after') }}</v-btn>
                </v-list-item>

                <v-divider></v-divider>

                <v-list-item v-if="!el._error && el.type !== 'reference' && auth.can('element:add')">
                  <v-btn prepend-icon="mdi-link" variant="text" @click="share(idx)">{{ $gettext('Make shared') }}</v-btn>
                </v-list-item>
                <v-list-item v-if="el.type === 'reference'">
                  <v-btn prepend-icon="mdi-link-off" variant="text" @click="unshare(idx)">{{ $gettext('Merge copy') }}</v-btn>
                </v-list-item>
                <v-list-item v-if="el.type !== 'reference'">
                  <v-btn prepend-icon="mdi-swap-horizontal" variant="text" @click="change(idx)">{{ $gettext('Change to') }}</v-btn>
                </v-list-item>
                <v-list-item v-if="el.type === 'text'">
                  <v-btn prepend-icon="mdi-set-split" variant="text" @click="split(idx)">{{ $gettext('Split') }}</v-btn>
                </v-list-item>
              </v-list>
            </v-menu>

            <v-icon v-if="el.type === 'reference'"
              :title="$gettext('Shared element')"
              class="icon-shared"
              icon="mdi-link"
            />

            <div class="element-title">{{ el.type === 'reference' ? elements[el.refid]?.name : title(el) }}</div>
            <div class="element-type">{{ $pgettext('st', el.type) }}</div>
          </v-expansion-panel-title>
          <v-expansion-panel-text>

            <Fields v-if="el.type === 'reference'"
              :data="elements[el.refid]?.data || {}"
              :fields="fields(elements[el.refid]?.type)"
              :assets="assets"
              :readonly="true"
              :type="el.type"
            />
            <Fields v-else ref="field"
              v-model:data="el.data"
              v-model:files="el.files"
              :readonly="!auth.can('page:save')"
              :fields="fields(el.type)"
              :assets="assets"
              :type="el.type"
              @error="error(el, $event)"
              @change="update(el)"
            />

          </v-expansion-panel-text>
        </v-expansion-panel>

      </VueDraggable>
    </v-expansion-panels>

    <div v-if="auth.can('page:save')" class="btn-group">
      <v-btn @click="vschemas = true"
        :title="$gettext('Add element')"
        icon="mdi-view-grid-plus"
        color="primary"
        variant="flat"
      />
    </div>
  </div>


  <Teleport to="body">
    <SchemaDialog v-model="vschemas" @add="add($event, index)" />
  </Teleport>

  <Teleport to="body">
    <SchemaDialog v-model="vchange" :elements="false" @add="changeTo($event, index)" />
  </Teleport>

</template>

<style scoped>
.header {
  margin-top: 8px;
}

.bulk {
  display: flex;
  align-items: center;
}

.v-input.search {
  max-width: 30rem;
  flex-grow: 1;
  width: 100%;
  margin: auto;
}

.v-input.search > * {
  width: 100%;
}

.v-expansion-panel {
  border-inline-start: 3px solid transparent;
}

.v-expansion-panel.changed {
  border-inline-start: 3px solid rgb(var(--v-theme-warning));
}

.v-expansion-panel.error .v-expansion-panel-title {
  color: rgb(var(--v-theme-error));
}

.v-expansion-panel-title .v-selection-control {
  flex: none;
}

.element-type {
  max-height: 48px;
  max-width: 5rem;
  text-align: end;
}

.icon-shared {
  color: rgb(var(--v-theme-warning));
  margin-inline-end: 4px;
}
</style>
