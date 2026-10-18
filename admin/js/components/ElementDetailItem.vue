/** @license MIT, https://opensource.org/license/mit */

<script>
import Fields from './Fields.vue'
import { useSchemaStore, useUserStore } from '../stores'
import { locales } from '../options'

export default {
  components: {
    Fields
  },

  props: {
    item: { type: Object, required: true },
    assets: { type: Object, default: () => {} }
  },

  emits: ['patch', 'add-assets', 'error'],

  setup() {
    const schemas = useSchemaStore()
    const user = useUserStore()

    return { user, schemas, locales }
  },

  computed: {
    readonly() {
      return !this.user.can('element:save')
    }
  },

  methods: {
    fields(type) {
      if (!type) {
        return []
      }

      if (!this.schemas.content[type]?.fields) {
        console.warn(`No definition of fields for "${type}" schemas`)
        return []
      }

      return this.schemas.content[type]?.fields
    },

    update(what, value) {
      this.$emit('patch', { [what]: value })
    }
  }
}
</script>

<template>
  <v-container>
    <v-sheet class="scroll">
      <v-row>
        <v-col cols="12" md="6">
          <v-text-field
            ref="name"
            :readonly="readonly"
            :modelValue="item.name"
            @update:modelValue="update('name', $event)"
            variant="underlined"
            :label="$gettext('Name') + ' ‒ ' + $gettext('Name to find the shared element in the element list')"
            counter="255"
            maxlength="255"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="6">
          <v-select
            ref="lang"
            :items="locales(true)"
            :readonly="readonly"
            :modelValue="item.lang"
            @update:modelValue="update('lang', $event)"
            variant="underlined"
            :label="$gettext('Language') + ' ‒ ' + $gettext('Language of the element content, choose none if used in all languages')"
          ></v-select>
        </v-col>
      </v-row>
      <v-row>
        <v-col cols="12">
          <Fields
            ref="field"
            :data="item.data"
            :files="item.files"
            :fields="fields(item.type)"
            :readonly="readonly"
            :assets="assets"
            :type="item.type"
            @update:data="update('data', $event)"
            @update:files="update('files', $event)"
            @add-assets="$emit('add-assets', $event)"
            @error="$emit('error', $event)"
          />
        </v-col>
      </v-row>
    </v-sheet>
  </v-container>
</template>

<style scoped>
.v-sheet.scroll {
  height: calc(100vh - 96px);
}
</style>
