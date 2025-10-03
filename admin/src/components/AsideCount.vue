/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

<script>
  import { useDrawerStore, useSideStore } from '../stores'

  export default {
    data: () => ({
      active: {},
      open: [0, 1, 2],
    }),

    setup() {
      const drawer = useDrawerStore()
      const side = useSideStore()

      return { drawer, side }
    },

    computed: {
      stores() {
        const keys = Object.keys(this.side.store).sort()
        const map = {}

        for(const key of keys) {
          map[key] = this.side.store[key]
        }

        return map
      }
    },

    methods: {
      isActive(key, code) {
        if(typeof this.active[key] === 'undefined') {
          this.active[key] = {}
        }

        if(typeof this.active[key][code] === 'undefined') {
          this.active[key][code] = false
        }

        return this.active[key][code]
      },


      sort(items) {
        const keys = Object.keys(items).sort()
        const map = {}

        for(const key of keys) {
          map[key] = items[key]
        }

        return map
      },


      toggle(key, code) {
        this.side.toggle(key, code)
        this.active[key][code] = !this.active[key][code]
      }
    }
  }
</script>

<template>
  <v-navigation-drawer v-model="drawer.aside" mobile-breakpoint="md" location="end">

    <v-list v-model:opened="open">
      <v-list-group v-for="(items, key) in stores" :key="key" :value="Object.keys(stores).indexOf(key)" v-show="Object.keys(items).length">
        <template v-slot:activator="{ props }">
          <v-list-item v-bind="props">{{ $pgettext('as', key) }}</v-list-item>
        </template>

        <v-list-item v-for="(value, code) in items" :key="code"
          :active="isActive(key, code)"
          @click="toggle(key, code)"
          rounded="lg"
        >
          <span class="name">{{ $pgettext('st', code) }}</span>
          <span class="value">{{ value }}</span>
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

  :deep(.v-list-item--active > .v-list-item__overlay) {
    opacity: 0;
  }

  :deep(.v-list-item--active:not(.v-list-group__header) .v-list-item__content) {
    color: rgba(var(--v-theme-on-surface-light), 0.5);
    text-decoration: line-through;
  }

  :deep(.v-list-item__content) {
    display: flex;
  }

  .v-list-item .value {
    margin-inline-start: 8px;
  }

  .v-list-item .value::before {
    content: ' (';
  }

  .v-list-item .value::after {
    content: ')';
  }
</style>
