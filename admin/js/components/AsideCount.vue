/** @license MIT, https://opensource.org/license/mit */

<script>
import { useDrawerStore, useSideStore } from '../stores'

export default {
  data: () => ({
    active: {},
    open: [0, 1, 2]
  }),

  setup() {
    const drawer = useDrawerStore()
    const side = useSideStore()

    return { drawer, side }
  },

  computed: {
    stores() {
      return Object.fromEntries(Object.entries(this.side.store).sort(([a], [b]) => a < b ? -1 : 1))
    }
  },

  methods: {
    isActive(key, code) {
      return !!this.active[key]?.[code]
    },

    toggle(key, code) {
      const group = this.active[key] ??= {}

      group[code] = !group[code]
      this.side.toggle(key, code)
    }
  }
}
</script>

<template>
  <v-navigation-drawer v-model="drawer.aside" mobile-breakpoint="md" location="end" tag="aside" :aria-label="$gettext('Used elements')">
    <v-list v-model:opened="open">
      <v-list-group
        v-for="(items, key) in stores"
        :key="key"
        :value="Object.keys(stores).indexOf(key)"
        v-show="Object.keys(items).length"
      >
        <template v-slot:activator="{ props }">
          <v-list-item v-bind="props">{{ $pgettext('as', key) }}</v-list-item>
        </template>

        <v-list-item
          v-for="(value, code) in items"
          :key="code"
          :active="isActive(key, code)"
          @click="toggle(key, code)"
          rounded="lg"
        >
          <span class="name">{{ $pgettext('st', code).replace('::', ' ') }}</span>
          <span class="value">{{ value }}</span>
        </v-list-item>
      </v-list-group>
    </v-list>
  </v-navigation-drawer>
</template>

<style scoped>
.v-navigation-drawer {
  border-start-start-radius: 8px;
}

:deep(.v-list-group__items) {
  --v-list-indent: 0px;
}

:deep(.v-list-item--active > .v-list-item__overlay) {
  opacity: 0;
}

:deep(.v-list-item--active:not(.v-list-group__header) .v-list-item__content) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
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
