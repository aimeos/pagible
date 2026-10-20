/** @license MIT, https://opensource.org/license/mit */

<script>
import PageDetailContentList from './PageDetailContentList.vue'
import { useSchemaStore } from '../stores'
import { hasTrue } from '../utils'
import { markRaw } from 'vue'

export default {
  components: {
    PageDetailContentList
  },

  props: {
    item: { type: Object, required: true },
    assets: { type: Object, required: true },
    changed: { type: Object, default: null },
    elements: { type: Object, required: true }
  },

  emits: ['change', 'error', 'update:content', 'patch-entry', 'add-assets', 'add-elements'],

  data: () => ({
    dirty: {},
    errors: {},
    lastError: false,
    tab: 'default'
  }),

  setup() {
    const schemas = useSchemaStore()
    return { schemas }
  },

  computed: {
    changedGroups() {
      const groups = {}

      for (const el of this.item.content || []) {
        if (el._changed) groups[el.group || 'main'] = true
      }

      return groups
    },

    names() {
      const type = this.item.type || 'page'
      const theme = this.item.theme || 'cms'

      return this.schemas.themes[theme]?.types?.[type]?.sections || ['main']
    },

    sections() {
      if (this.names.length <= 1) {
        return {}
      }

      const sections = {}

      for (const name of this.names) {
        sections[name] = []
      }

      for (const item of this.item.content || []) {
        const name = item.group || 'main'

        if (!sections[name]) {
          sections[name] = []
        }

        sections[name].push(item)
      }

      return markRaw(sections)
    }
  },

  methods: {
    markChanged(section) {
      this.dirty[section] = true
      this.$emit('change', 'content')
    },

    contentUpdated(section, list) {
      this.$emit('update:content', Object.values({ ...this.sections, [section]: list }).flat())
      this.markChanged(section)
    },

    error(what, value) {
      this.errors[what] = value
      const has = hasTrue(this.errors)
      if (has !== this.lastError) {
        this.lastError = has
        this.$emit('error', has)
      }
    },

    mainContentUpdated(list) {
      this.$emit('update:content', list)
      this.$emit('change', 'content')
    },

    reset() {
      this.dirty = {}
      this.errors = {}

      Array.isArray(this.$refs.content)
        ? this.$refs.content.forEach((ref) => ref.reset())
        : this.$refs.content?.reset()
    },

    async showError() {
      const section = Object.keys(this.errors).find((key) => this.errors[key])

      if (!section) {
        return
      }

      if (Array.isArray(this.$refs.content)) {
        this.tab = section
        await this.$nextTick()
        await this.$refs.content.find((ref) => ref.section === section)?.showError()
      } else {
        await this.$refs.content?.showError()
      }
    }
  }
}
</script>

<template>
  <v-container>
    <v-sheet class="box scroll">
      <div v-if="Object.keys(sections).length > 1">
        <v-tabs class="subtabs" v-model="tab" align-tabs="center">
          <v-tab
            v-for="(list, section) in sections"
            :key="section"
            :class="{
              changed: dirty[section] || changedGroups[section],
              error: errors[section]
            }"
            :value="section"
            >{{ $pgettext('cs', section) }}</v-tab
          >
        </v-tabs>

        <v-window v-model="tab" :touch="false">
          <v-window-item v-for="(list, section) in sections" :key="section" :value="section">
            <PageDetailContentList
              ref="content"
              :section="section"
              :item="item"
              :assets="assets"
              :changed="changed"
              :content="list"
              :elements="elements"
              @error="error(section, $event)"
              @change="markChanged(section)"
              @patch-entry="(entry, values) => $emit('patch-entry', entry, values)"
              @add-assets="$emit('add-assets', $event)"
              @add-elements="$emit('add-elements', $event)"
              @update:content="contentUpdated(section, $event)"
            />
          </v-window-item>
        </v-window>
      </div>
      <div v-else>
        <div class="subtabs"></div>

        <PageDetailContentList
          ref="content"
          :item="item"
          :assets="assets"
          :changed="changed"
          :content="item.content || []"
          :elements="elements"
          @error="error('main', $event)"
          @change="$emit('change', 'content')"
          @patch-entry="(entry, values) => $emit('patch-entry', entry, values)"
          @add-assets="$emit('add-assets', $event)"
          @add-elements="$emit('add-elements', $event)"
          @update:content="mainContentUpdated"
        />
      </div>
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

.subtabs {
  margin-bottom: 16px;
}
</style>
