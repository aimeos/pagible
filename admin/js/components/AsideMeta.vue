/** @license MIT, https://opensource.org/license/mit */

<script>
import { useDrawerStore } from '../stores.js'

export default {
  props: {
    item: { type: Object, required: true }
  },

  setup() {
    const drawer = useDrawerStore()
    return { drawer }
  },

  computed: {
    values() {
      const map = {}

      if (this.item.id) {
        map[this.$gettext('ID')] = this.item.id
      }

      if (this.item.mime) {
        map[this.$gettext('MIME')] = this.item.mime
      }

      if (this.item.editor) {
        map[this.$gettext('editor')] = this.item.editor
      }

      const dates = {
        created_at: this.$gettext('created'),
        updated_at: this.$gettext('updated'),
        deleted_at: this.$gettext('deleted')
      }

      for (const [key, label] of Object.entries(dates)) {
        if (this.item[key]) {
          map[label] = new Date(this.item[key]).toLocaleString(this.$vuetify.locale.current)
        }
      }

      return map
    }
  }
}
</script>

<template>
  <v-navigation-drawer v-model="drawer.aside" mobile-breakpoint="md" location="end" tag="aside" :aria-label="$gettext('Meta data')">
    <v-list>
      <v-list-subheader>{{ $gettext('Meta data') }}</v-list-subheader>
      <v-list-item v-for="(value, key) in values" :key="key" rounded="lg">
        <v-list-item-title class="name">{{ key }}</v-list-item-title>
        <div>{{ value }}</div>
      </v-list-item>
    </v-list>
  </v-navigation-drawer>
</template>

<style scoped>
.v-navigation-drawer {
  border-start-start-radius: 8px;
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
