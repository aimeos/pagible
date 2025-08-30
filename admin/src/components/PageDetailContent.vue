<script>
  import PageDetailContentList from './PageDetailContentList.vue'
  import { useConfigStore } from '../stores'

  export default {
    components: {
      PageDetailContentList
    },

    props: {
      'item': {type: Object, required: true},
      'assets': {type: Object, required: true},
      'elements': {type: Object, required: true}
    },

    emits: ['change', 'error'],

    data: () => ({
      changed: {},
      errors: {},
      tab: 'default',
    }),

    setup() {
      const config = useConfigStore()
      return { config }
    },

    computed: {
      names() {
        const type = this.item.type || 'page'
        const theme = this.item.theme || 'cms'

        return this.config.get(`themes.${theme}.types.${type}.sections`, ['main'])
      },


      sections() {
        if(this.names.length <= 1) {
          return {}
        }

        const sections = {}

        for(const name of this.names) {
          sections[name] = []
        }

        for(const item of (this.item.content || [])) {
          const name = item.group || 'main'

          if(!sections[name]) {
            sections[name] = []
          }

          sections[name].push(item)
        }

        return sections
      }
    },

    methods: {
      error(what, value) {
        this.errors[what] = value
        this.$emit('error', Object.values(this.errors).includes(true))
      },


      reset() {
        this.changed = {}
        this.errors = {}

        Array.isArray(this.$refs.content)
          ? this.$refs.content.forEach(ref => ref.reset())
          : this.$refs.content?.reset()
      },


      update(section, list) {
        const sections = this.sections
        sections[section] = list

        this.item.content = Object.values(sections).reduce((acc, entries) => {
          return acc.concat(entries)
        }, [])

        this.changed[section] = true
      },


      validate() {
        const refs = Array.isArray(this.$refs.content) ? this.$refs.content : [this.$refs.content]
        const promises = refs.map(ref => ref?.validate())

        return Promise.all(promises).then(results => {
          return results.every(result => result)
        })
      }
    }
  }
</script>

<template>
  <v-container>
    <v-sheet class="box scroll">
      <div v-if="Object.keys(sections).length > 1">
        <v-tabs class="subtabs" v-model="tab" align-tabs="center">
          <v-tab v-for="(list, section) in sections" :key="section"
            :class="{
              changed: changed[section],
              error: errors[section]
            }"
            :value="section"
          >{{ $pgettext('cs', section) }}</v-tab>
        </v-tabs>

        <v-window v-model="tab" :touch="false">
          <v-window-item v-for="(list, section) in sections" :key="section" :value="section">
            <PageDetailContentList ref="content"
              :section="section"
              :item="item"
              :assets="assets"
              :content="list"
              :elements="elements"
              @error="error(section, $event)"
              @update:content="update(section, $event); this.$emit('change', 'content')"
            />
          </v-window-item>
        </v-window>
      </div>
      <div v-else>
        <div class="subtabs"></div>

        <PageDetailContentList ref="content"
          :item="item"
          :assets="assets"
          :content="item.content || []"
          :elements="elements"
          @error="$emit('error', $event)"
          @update:content="item.content = $event; this.$emit('change', 'content')"
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
