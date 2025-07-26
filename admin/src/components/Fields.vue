<script>
  import gql from 'graphql-tag'
  import { useMessageStore } from '../stores'

  export default {
    props: {
      'data': {type: Object, default: () => {}},
      'files': {type: Array, default: () => []},
      'assets': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'fields': {type: Object, required: true},
    },

    emits: ['change', 'error', 'update:files'],

    inject: ['compose', 'translate', 'txlocales'],

    data() {
      return {
        translating: {},
        composing: {},
        errors: {},
      }
    },

    setup() {
      const messages = useMessageStore()
      return { messages }
    },

    methods: {
      addFile(id) {
        const ids = Array.isArray(id) ? id : [id]
        const files = [...this.files]

        files.push(...ids)
        this.$emit('update:files', files)
      },


      composeText(code) {
        const context = [
          'generate for: ' + (this.fields[code].label || code),
          'required output format: ' + this.fields[code].type,
          this.fields[code].min ? 'minimum characters: ' + this.fields[code].min : null,
          this.fields[code].max ? 'maximum characters: ' + this.fields[code].max : null,
          this.fields[code].placeholder ? 'hint text: ' + this.fields[code].placeholder : null,
          'context information as JSON: ' + JSON.stringify(this.data),
        ]

        this.composing[code] = true

        this.compose(this.data[code] ?? '', context).then(result => {
          this.update(code, result)
        }).finally(() => {
          this.composing[code] = false
        })
      },


      error(code, value) {
        this.errors[code] = value
        this.$emit('error', Object.values(this.errors).includes(true))
      },


      removeFile(id) {
        const ids = Array.isArray(id) ? id : [id]
        const files = [...this.files]

        for(const id of ids) {
          const idx = files.findIndex(fileid => fileid === id)

          if(idx !== -1) {
            files.splice(idx, 1)
          }
        }

        this.$emit('update:files', files)
      },


      toName(type) {
        return type?.charAt(0)?.toUpperCase() + type?.slice(1)
      },


      translateText(code, lang) {

        this.translating[code] = true

        this.translate([this.data[code]], lang).then(result => {
          this.update(code, result[0] || '')
        }).finally(() => {
          this.translating[code] = false
        })
      },


      update(code, value) {
        this.data[code] = value
        this.$emit('change', this.data[code])
      },


      validate() {
        const list = []
        this.errors = {}

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
  <div v-for="(field, code) in fields" :key="code" class="item" :class="{error: errors[code]}">
    <v-label v-if="field.type !== 'hidden'">
      {{ $pgettext('fn', field.label || code).replace(/-/g, ' ') }}
      <div v-if="!readonly && ['markdown', 'plaintext', 'string', 'text'].includes(field.type)" class="actions">
        <v-menu>
          <template #activator="{ props }">
            <v-btn v-bind="props"
              :title="$gettext('Translate %{code} field', {code: code})"
              :loading="translating[code]"
              icon="mdi-translate"
              variant="flat"
            />
          </template>
          <v-list>
            <v-list-item v-for="lang in txlocales()" :key="lang.code">
              <v-btn
                @click="translateText(code, lang.code)"
                prepend-icon="mdi-arrow-right-thin"
                variant="text"
              >{{ lang.name }}</v-btn>
            </v-list-item>
          </v-list>
        </v-menu>
        <v-btn
          :title="$gettext('Generate text for %{code} field', {code: code})"
          :loading="composing[code]"
          @click="composeText(code)"
          icon="mdi-creation"
          variant="flat"
        />
      </div>
    </v-label>
    <component ref="field"
      :is="toName(field.type)"
      :key="field.type + '-' + code"
      :assets="assets"
      :config="field"
      :readonly="readonly"
      :modelValue="data[code]"
      @addFile="addFile($event)"
      @removeFile="removeFile($event)"
      @update:modelValue="update(code, $event)"
      @error="error(code, $event)"
    ></component>
  </div>
</template>

<style scoped>
  .item {
    margin: 24px 0;
    padding-inline-start: 8px;
    border-inline-start: 3px solid #D0D8E0;
  }

  .item.error {
    border-inline-start: 3px solid rgb(var(--v-theme-error));
  }

  .v-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-transform: capitalize;
    font-weight: bold;
    margin-bottom: 4px;
  }
</style>