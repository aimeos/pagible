<script>
  import PageDetailItemMeta from './PageDetailItemMeta.vue'
  import PageDetailItemProps from './PageDetailItemProps.vue'
  import PageDetailItemConfig from './PageDetailItemConfig.vue'

  export default {
    components: {
      PageDetailItemMeta,
      PageDetailItemProps,
      PageDetailItemConfig,
    },

    props: {
      'item': {type: Object, required: true},
      'assets': {type: Object, default: () => {}},
    },

    emits: ['update:item', 'update:aside', 'error'],

    data: () => ({
      changed: {},
      errors: {},
      tab: 'details',
    }),

    methods: {
      error(what, value) {
        this.errors[what] = value
        this.$emit('error', Object.values(this.errors).includes(true))
      },


      reset() {
        this.changed = {}
        this.errors = {}

        this.$refs.props?.reset()
        this.$refs.meta?.reset()
        this.$refs.config?.reset()
      },


      update(what) {
        this.changed[what] = true
        this.$emit('update:item', this.item)
      },


      validate() {
        const promises = Object.values(this.$refs).map(ref => ref.validate())

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
      <v-tabs class="subtabs" v-model="tab" align-tabs="center">
        <v-tab value="details"
          :class="{changed: changed.details, error: errors.details}"
          @click="$emit('update:aside', 'meta')">
          {{ $gettext('Detail') }}
        </v-tab>
        <v-tab value="meta"
          :class="{changed: changed.meta, error: errors.meta}"
          @click="$emit('update:aside', 'count')">
          {{ $gettext('Meta') }}
        </v-tab>
        <v-tab value="config"
          :class="{changed: changed.config, error: errors.config}"
          @click="$emit('update:aside', 'count')">
          {{ $gettext('Config') }}
        </v-tab>
      </v-tabs>

      <v-window v-model="tab" :touch="false">
        <v-window-item value="details">
          <PageDetailItemProps ref="props"
            :item="item"
            :assets="assets"
            @change="update('details')"
            @error="error('details', $event)"
          />
        </v-window-item>

        <v-window-item value="meta">
          <PageDetailItemMeta ref="meta"
            :item="item"
            :assets="assets"
            @change="update('meta')"
            @error="error('meta', $event)"
          />
        </v-window-item>

        <v-window-item value="config">
          <PageDetailItemConfig ref="config"
            :item="item"
            :assets="assets"
            @change="update('config')"
            @error="error('config', $event)"
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
    max-height: calc(100vh - 96px);
  }
</style>
