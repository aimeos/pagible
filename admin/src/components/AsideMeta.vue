/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

<script>
  import { useDrawerStore } from '../stores.js'

  export default {
    props: {
      'item': {type: Object, required: true},
    },

    setup() {
      const drawer = useDrawerStore()
      return { drawer }
    },

    computed: {
      values() {
        const map = {}

        if(this.item.id) {
          map[this.$gettext('id')] = this.item.id
        }

        if(this.item.mime) {
          map[this.$gettext('mime')] = this.item.mime
        }

        if(this.item.editor) {
          map[this.$gettext('editor')] = this.item.editor
        }

        if(this.item.created_at) {
          map[this.$gettext('created')] = (new Date(this.item.created_at)).toLocaleString(this.$vuetify.locale.current)
        }

        if(this.item.updated_at) {
          map[this.$gettext('updated')] = (new Date(this.item.updated_at)).toLocaleString(this.$vuetify.locale.current)
        }

        if(this.item.deleted_at) {
          map[this.$gettext('deleted')] = (new Date(this.item.deleted_at)).toLocaleString(this.$vuetify.locale.current)
        }

        return map
      }
    },
  }
</script>

<template>
  <v-navigation-drawer v-model="drawer.aside" mobile-breakpoint="md" location="end">

    <v-list :opened="[0]">
      <v-list-group :value="0">
        <template v-slot:activator="{ props }">
          <v-list-item v-bind="props">{{ $gettext('Meta data') }}</v-list-item>
        </template>

        <v-list-item v-for="(value, key) in values" :key="key" rounded="lg">
          <v-list-item-title class="name">{{ key }}</v-list-item-title>
          <div>{{ value }}</div>
        </v-list-item>
      </v-list-group>
    </v-list>

  </v-navigation-drawer>
</template>

<style scoped>
  .v-navigation-drawer {
    border-top-left-radius: 8px;
  }

  .v-locale--is-rtl .v-navigation-drawer {
    border-top-left-radius: 0;
    border-top-right-radius: 8px;
  }

  :deep(.v-list-group__items) {
    --v-list-indent: 0px;
  }

  .v-list-item {
    margin-bottom: 4px;
  }

  .v-list-item .name {
    text-transform: capitalize;
  }

  .v-list-item .name::after {
    content: ':';
  }
</style>
