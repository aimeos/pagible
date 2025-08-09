<script>
  import Fields from './Fields.vue'
  import { useAppStore, useAuthStore, useLanguageStore, useSchemaStore, useSideStore } from '../stores'


  export default {
    components: {
      Fields
    },

    props: {
      'item': {type: Object, required: true},
      'assets': {type: Object, default: () => {}},
    },

    emits: ['update:item', 'error'],

    inject: ['locales'],

    setup() {
      const languages = useLanguageStore()
      const schemas = useSchemaStore()
      const side = useSideStore()
      const auth = useAuthStore()
      const app = useAppStore()

      return { app, auth, languages, schemas, side }
    },

    computed: {
      readonly() {
        return !this.auth.can('element:save')
      }
    },

    methods: {
      fields(type) {
        if(!type) {
          return []
        }

        if(!this.schemas.content[type]?.fields) {
          console.warn(`No definition of fields for "${type}" schemas`)
          return []
        }

        return this.schemas.content[type]?.fields
      },

      update(what, value) {
        this.item[what] = value
        this.$emit('update:item', this.item)
      }
    }
  }
</script>

<template>
  <v-container>
    <v-sheet class="scroll">
      <v-row>
        <v-col cols="12" md="6">
          <v-text-field ref="name"
            :readonly="readonly"
            :modelValue="item.name"
            @update:modelValue="update('name', $event)"
            variant="underlined"
            :label="$gettext('Name')"
            counter="255"
            maxlength="255"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="6">
          <v-select ref="lang"
            :items="locales(true)"
            :readonly="readonly"
            :modelValue="item.lang"
            @update:modelValue="update('lang', $event)"
            variant="underlined"
            :label="$gettext('Language')"
          ></v-select>
        </v-col>
      </v-row>
      <v-row>
        <v-col cols="12">
          <Fields ref="field"
            v-model:data="item.data"
            v-model:files="item.files"
            :fields="fields(item.type)"
            :readonly="readonly"
            :assets="assets"
            :type="item.type"
            @error="$emit('error', $event)"
            @change="$emit('update:item', item)"
          />
        </v-col>
      </v-row>
    </v-sheet>
  </v-container>
</template>

<style scoped>
  .v-sheet.scroll {
    max-height: calc(100vh - 96px);
  }
</style>