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

  emits: ['change', 'error'],

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

  beforeUnmount() {
    this.dirty = null
    this.errors = null
  },

  computed: {
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
    contentUpdated(section, event) {
      this.update(section, event)
      this.$emit('change', 'content')
    },

    error(what, value) {
      this.errors[what] = value
      const has = hasTrue(this.errors)
      if (has !== this.lastError) {
        this.lastError = has
        this.$emit('error', has)
      }
    },

    mainContentUpdated(event) {
      this.item.content = event
      this.$emit('change', 'content')
    },

    flush() {
      Array.isArray(this.$refs.content)
        ? this.$refs.content.forEach((ref) => ref.flush())
        : this.$refs.content?.flush()
    },

    reset() {
      this.dirty = {}
      this.errors = {}

      Array.isArray(this.$refs.content)
        ? this.$refs.content.forEach((ref) => ref.reset())
        : this.$refs.content?.reset()
    },

    update(section, list) {
      const sections = this.sections
      sections[section] = list

      this.item.content = Object.values(sections).flat()

      this.dirty[section] = true
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
              changed: dirty[section],
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
  max-height: calc(100vh - 96px);
}

.subtabs {
  margin-bottom: 16px;
}
</style>
