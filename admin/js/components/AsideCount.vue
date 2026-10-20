/** @license MIT, https://opensource.org/license/mit */

<script>
import { useDrawerStore, useSideStore } from '../stores'
import { typeLabelMixin } from '../i18n'

// number of leading groups which are expanded when they appear
const OPEN_GROUPS = 3

export default {
  mixins: [typeLabelMixin],

  data: () => ({
    open: []
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

  watch: {
    // groups are tracked by key so added or removed groups don't toggle other groups
    stores: {
      immediate: true,
      handler(stores) {
        // immediate watchers run before created()
        this.seen ??= new Set()
        Object.keys(stores).forEach((key, index) => {
          if (this.seen.has(key)) return
          this.seen.add(key)
          if (index < OPEN_GROUPS) this.open.push(key)
        })
      }
    }
  },

  methods: {
    // hidden entries are marked as active
    isActive(key, code) {
      return !this.side.shown(key, code)
    },

    toggle(key, code) {
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
        :value="key"
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
          <span class="name">{{ typeLabel(code) }}</span>
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
