/** @license MIT, https://opensource.org/license/mit */

<script>
import PageAccess from './PageAccess.vue'
import PageDetailItemProps from './PageDetailItemProps.vue'
import PageDetailItemSection from './PageDetailItemSection.vue'
import { useUserStore } from '../stores'
import { hasTrue } from '../utils'

export default {
  components: {
    PageAccess,
    PageDetailItemProps,
    PageDetailItemSection
  },

  props: {
    item: { type: Object, required: true },
    assets: { type: Object, default: () => ({}) }
  },

  emits: ['patch', 'add-assets', 'update:access', 'update:aside', 'error'],

  data: () => ({
    changed: {},
    errors: {},
    tab: 'details'
  }),

  setup() {
    const user = useUserStore()

    return { user }
  },

  methods: {
    error(what, value) {
      this.errors[what] = value
      this.$emit('error', hasTrue(this.errors))
    },

    reset() {
      this.changed = {}
      this.errors = {}

      this.$refs.details?.reset()
      this.$refs.meta?.reset()
      this.$refs.config?.reset()
    },

    async showError() {
      const tab = ['details', 'meta', 'config'].find((key) => this.errors[key])

      if (!tab) {
        return
      }

      this.tab = tab
      this.$emit('update:aside', tab === 'details' ? 'meta' : 'count')

      // the details are a plain form, only the sections need to open the failed panel
      await this.$nextTick()
      await this.$refs[tab]?.showError?.()
    },

    update(what, patch) {
      this.changed[what] = true
      this.$emit('patch', patch)
    }
  }
}
</script>

<template>
  <v-container>
    <v-sheet class="box scroll">
      <v-tabs class="subtabs" v-model="tab" align-tabs="center">
        <v-tab
          value="details"
          :class="{ changed: changed.details, error: errors.details }"
          @click="$emit('update:aside', 'meta')"
        >
          {{ $gettext('Detail') }}
        </v-tab>
        <v-tab
          value="meta"
          :class="{ changed: changed.meta, error: errors.meta }"
          @click="$emit('update:aside', 'count')"
        >
          {{ $gettext('Meta') }}
        </v-tab>
        <v-tab
          value="config"
          :class="{ changed: changed.config, error: errors.config }"
          @click="$emit('update:aside', 'count')"
        >
          {{ $gettext('Config') }}
        </v-tab>
        <v-tab
          v-if="user.can('page:access')"
          value="access"
          @click="$emit('update:aside', 'count')"
        >
          {{ $gettext('Access') }}
        </v-tab>
      </v-tabs>

      <v-window v-model="tab" :touch="false">
        <v-window-item value="details">
          <PageDetailItemProps
            ref="details"
            :item="item"
            @patch="update('details', $event)"
            @error="error('details', $event)"
          />
        </v-window-item>

        <v-window-item value="meta">
          <PageDetailItemSection
            ref="meta"
            section="meta"
            :item="item"
            :assets="assets"
            @patch="update('meta', $event)"
            @add-assets="$emit('add-assets', $event)"
            @error="error('meta', $event)"
          />
        </v-window-item>

        <v-window-item value="config">
          <PageDetailItemSection
            ref="config"
            section="config"
            permission="page:config"
            :item="item"
            :assets="assets"
            @patch="update('config', $event)"
            @add-assets="$emit('add-assets', $event)"
            @error="error('config', $event)"
          />
        </v-window-item>

        <v-window-item
          v-if="user.can('page:access')"
          value="access"
        >
          <PageAccess
            :ids="[item.id]"
            :access="item.access"
            :descendants="item.has || 0"
            @applied="$emit('update:access', $event)"
          />
        </v-window-item>
      </v-window>
    </v-sheet>
  </v-container>
</template>

<style scoped>
.v-sheet {
  margin: 0;
  padding-top: 0;
}

.v-sheet.scroll {
  height: var(--cms-detail-height);
}
</style>
