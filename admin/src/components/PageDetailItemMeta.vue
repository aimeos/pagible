<script>
  import Fields from './Fields.vue'
  import SchemaItems from './SchemaItems.vue'
  import { useAuthStore, useMessageStore, useSchemaStore, useSideStore } from '../stores'
  import { uid } from '../utils'

  export default {
    components: {
      Fields,
      SchemaItems,
    },

    props: {
      'item': {type: Object, required: true},
      'assets': {type: Object, default: () => {}},
    },

    emits: ['change', 'error'],

    data: () => ({
      vschemas: false,
      panel: []
    }),

    setup() {
      const messages = useMessageStore()
      const schemas = useSchemaStore()
      const side = useSideStore()
      const auth = useAuthStore()

      return { auth, messages, schemas, side }
    },

    computed: {
      available() {
        return Object.keys(this.schemas).length
      }
    },

    methods: {
      add(item) {
        if(!this.item.meta) {
          this.item.meta = {}
        }

        if(this.item.meta[item.type]) {
          this.messages.add(this.$gettext('The element has already been added'), 'error')
          return
        }

        this.item.meta[item.type] = {id: uid(), type: item.type, data: {}}
        this.panel.push(Object.keys(this.item.meta).length - 1)
        this.vschemas = false
      },


      error(el, value) {
        el._error = value
        this.$emit('error', Object.values(this.item.meta || {}).some(item => item._error))
        this.store()
      },


      fields(type) {
        if(!this.schemas.meta[type]?.fields) {
          console.warn(`No definition of fields for "${type}" available`)
          return []
        }

        return this.schemas.meta[type]?.fields
      },


      remove(code) {
        delete this.item.meta[code]
        this.$emit('change', true)

        this.$nextTick(() => {
          this.validate().then(valid => {
            this.$emit('error', !valid)
          })
        })
      },


      reset() {
        Object.values(this.item.meta || {}).forEach(el => {
          delete el._changed
          delete el._error
        })
      },


      shown(el) {
        const valid = this.side.shown('state', 'valid')
        const error = this.side.shown('state', 'error')
        const changed = this.side.shown('state', 'changed')

        return this.side.shown('type', el.type) && (
          error && el._error || changed && el._changed || valid && !el._error && !el._changed
        )
      },


      store(isVisible = true) {
        if(!isVisible) {
          return
        }

        const types = {}
        const state = {}

        for(const el of Object.values(this.item.meta || {})) {
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
        }

        this.side.store = {type: types, state: state}
      },


      title(el) {
        return (el.data?.title || el.data?.text || Object.values(el.data || {})
          .map(v => v && typeof v !== 'object' && typeof v !== 'boolean' ? v : null)
          .filter(v => !!v)
          .join(' - '))
          .substring(0, 50) || ''
      },


      update(el) {
        el._changed = true
        this.$emit('change', true)
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
  <v-container v-observe-visibility="store">
    <v-sheet>

      <v-expansion-panels class="list" v-model="panel" elevation="0" multiple>

        <v-expansion-panel v-for="(el, code) in item.meta || {}" :key="code" :class="{changed: el._changed, error: el._error}" v-show="shown(el)">
          <v-expansion-panel-title expand-icon="mdi-pencil">
            <v-btn v-if="auth.can('page:save')"
              @click="remove(code)"
              icon="mdi-delete"
              variant="text"
            />
            <div class="element-title">{{ title(el) }}</div>
            <div class="element-type">{{ el.type }}</div>
          </v-expansion-panel-title>
          <v-expansion-panel-text>

            <Fields ref="field"
              v-model:data="el.data"
              v-model:files="el.files"
              @error="error(el, $event)"
              @change="update(el)"
              :readonly="!auth.can('page:save')"
              :fields="fields(el.type)"
              :assets="assets"
              :type="el.type"
            />

          </v-expansion-panel-text>
        </v-expansion-panel>

      </v-expansion-panels>

      <div v-if="available && auth.can('page:save')" class="btn-group">
        <v-btn
          @click="vschemas = true"
          :title="$gettext('Add element')"
          icon="mdi-view-grid-plus"
          color="primary"
          variant="flat"
        />
      </div>

    </v-sheet>
  </v-container>

  <Teleport to="body">
    <v-dialog v-model="vschemas" @afterLeave="vschemas = false" scrollable width="auto">
      <SchemaItems type="meta" @add="add($event)" />
    </v-dialog>
  </Teleport>
</template>

<style scoped>
.v-expansion-panel {
  border-inline-start: 3px solid transparent;
}

.v-expansion-panel.changed {
  border-inline-start: 3px solid rgb(var(--v-theme-warning));
}

.v-expansion-panel.error .v-expansion-panel-title {
  color: rgb(var(--v-theme-error));
}
</style>
