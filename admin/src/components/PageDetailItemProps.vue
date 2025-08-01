<script>
  import gql from 'graphql-tag'
  import { useAppStore, useAuthStore, useConfigStore, useLanguageStore, useSideStore } from '../stores'

  export default {
    props: {
      'item': {type: Object, required: true},
      'assets': {type: Object, default: () => {}},
    },

    emits: ['change', 'error'],

    inject: ['debounce', 'locales', 'slugify'],

    data: () => ({
      errors: {},
      messages: {}
    }),

    setup() {
      const languages = useLanguageStore()
      const config = useConfigStore()
      const side = useSideStore()
      const auth = useAuthStore()
      const app = useAppStore()

      return { app, auth, side, config, languages }
    },

    created() {
      this.checkPathd = this.debounce(this.checkPath, 500)
    },

    computed: {
      readonly() {
        return !this.auth.can('page:save')
      }
    },

    methods: {
      checkPath() {
        return this.$apollo?.query({
          query: gql`query($filter: PageFilter) {
            pages(filter: $filter) {
              data {
                id
              }
            }
          }`,
          variables: {
            filter: {
              path: this.item.path || '',
              domain: this.item.domain || '',
            }
          }
        }).then(result => {
          if(result?.data?.pages?.data?.length > 0 && result?.data?.pages?.data?.some(page => page.id != this.item.id)) {
            this.messages.path = [this.$gettext('The path is already in use by another page')]
          } else {
            this.messages.path = []
          }

          this.$emit('error', !!this.messages.path.length)
          return this.messages.path
        }).catch(error => {
          console.error('PageDetailItemProps::checkPath: Error checking path', error)
        }) || []
      },


      reset() {
        this.errors = {}
      },


      setPath(focused) {
        if(!focused && this.item.path?.at(0) === '_') {
          this.updatePath(this.item.name)
        }
      },


      update(what, value) {
        this.item[what] = typeof value === 'string' ? value.trim() : value
        this.$emit('change', true)
      },


      updatePath(value) {
        this.update('path', this.slugify(value))
      },


      async validate(lazy = false) {
        await this.$nextTick()
        const list = [lazy ? this.checkPathd() : this.checkPath()]

        Object.values(this.$refs).forEach(field => {
          list.push(field.validate())
        })

        return Promise.all(list).then(result => {
          const res = result.reduce((sum, r) => sum + r.length, 0)
          this.$emit('error', !!res)
          return res || true
        });
      }
    },

    watch: {
      item: {
        deep: true,
        immediate: true,
        handler(val) {
          this.validate(true)
        }
      }
    }
  }
</script>

<template>
  <v-container>
    <v-sheet>

      <v-row>
        <v-col cols="12" md="6">
          <v-select ref="status"
            :items="[
              { key: 0, val: $gettext('Disabled') },
              { key: 1, val: $gettext('Enabled') },
              { key: 2, val: $gettext('Hidden in navigation') }
            ]"
            :readonly="readonly"
            :modelValue="item.status"
            :label="$gettext('Status')"
            @update:modelValue="update('status', $event)"
            variant="underlined"
            item-title="val"
            item-value="key"
          ></v-select>
        </v-col>
        <v-col cols="12" md="6">
          <v-select ref="lang"
            :items="locales()"
            :readonly="readonly"
            :modelValue="item.lang"
            :label="$gettext('Language')"
            @update:modelValue="update('lang', $event)"
            variant="underlined"
          ></v-select>
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12" md="6">
          <v-text-field ref="title"
            :rules="[
              v => !!v || $gettext('Field is required'),
            ]"
            :readonly="readonly"
            :modelValue="item.title"
            :label="$gettext('Page title')"
            @update:modelValue="update('title', $event)"
            @update:focused="setPath($event)"
            variant="underlined"
            maxlength="255"
            counter="255"
          ></v-text-field>
          <v-text-field ref="name"
            :readonly="readonly"
            :modelValue="item.name"
            :label="$gettext('Page name')"
            @update:modelValue="update('name', $event)"
            variant="underlined"
            counter="60"
            maxlength="60"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="6">
          <v-text-field ref="path"
            :rules="[
              v => !v || v && v[0] !== '/' || $gettext('Path must not start with a slash (/)'),
            ]"
            :error="!!(messages.path || []).length"
            :error-messages="messages.path"
            :readonly="readonly"
            :modelValue="item.path"
            :label="$gettext('URL path')"
            @update:modelValue="updatePath($event); messages.path = null"
            @change="checkPath()"
            variant="underlined"
            maxlength="255"
            counter="255"
          ></v-text-field>
          <v-text-field ref="domain"
            :rules="[
              v => !v || v && /^([0-9a-z]+[.-])*[0-9a-z]+\.[a-z]{2,}$/.test(v) || $gettext('Domain name is invalid'),
            ]"
            :readonly="readonly"
            :modelValue="item.domain"
            :label="$gettext('Domain')"
            @update:modelValue="update('domain', $event)"
            variant="underlined"
            maxlength="255"
            counter="255"
          ></v-text-field>
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12" md="6">
          <v-select ref="theme"
            :readonly="readonly"
            :modelValue="item.theme"
            :label="$gettext('Theme')"
            :items="Object.keys(config.get('themes', {'cms': ''}))"
            @update:modelValue="update('theme', $event); item.type = ''"
            variant="underlined"
          ></v-select>
          <v-select ref="type"
            :readonly="readonly"
            :modelValue="item.type"
            :label="$gettext('Page type')"
            :items="Object.keys(config.get(`themes.${item.theme || 'cms'}.types`, {'page': ''}))"
            @update:modelValue="update('type', $event)"
            variant="underlined"
          ></v-select>
        </v-col>
        <v-col cols="12" md="6">
          <v-text-field ref="tag"
            v-model="item.tag"
            :readonly="readonly"
            :label="$gettext('Page tag')"
            @update:modelValue="update()"
            variant="underlined"
            maxlength="30"
            counter="30"
          ></v-text-field>
          <v-select ref="cache"
            :items="[
              { key: 0, val: $gettext('No cache') },
              { key: 1, val: $ngettext('%{num} minute', '%{num} minutes', 1, {num: 1}) },
              { key: 5, val: $ngettext('%{num} minute', '%{num} minutes', 5, {num: 5}) },
              { key: 15, val: $ngettext('%{num} minute', '%{num} minutes', 15, {num: 15}) },
              { key: 30, val: $ngettext('%{num} minute', '%{num} minutes', 30, {num: 30}) },
              { key: 60, val: $ngettext('%{num} hour', '%{num} hours', 1, {num: 1}) },
              { key: 180, val: $ngettext('%{num} hour', '%{num} hours', 3, {num: 3}) },
              { key: 360, val: $ngettext('%{num} hour', '%{num} hours', 6, {num: 6}) },
              { key: 720, val: $ngettext('%{num} hour', '%{num} hours', 12, {num: 12}) },
              { key: 1440, val: $ngettext('%{num} hour', '%{num} hours', 24, {num: 24}) },
            ]"
            :readonly="readonly"
            :modelValue="item.cache"
            :label="$gettext('Cache time')"
            @update:modelValue="update('cache', $event)"
            variant="underlined"
            item-title="val"
            item-value="key"
          ></v-select>
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12">
          <v-text-field ref="to"
            :rules="[
              v => !v || v.match('^((https?:)?//([^\\s/:@]+(:[^\\s/:@]+)?@)?([0-9a-z]+(\\.|-))*[0-9a-z]+\\.[a-z]{2,}(:[0-9]{1,5})?)?(/[^\\s]*)*$') !== null || $gettext('URL is not valid'),
            ]"
            :readonly="readonly"
            :modelValue="item.to"
            :label="$gettext('Redirect URL')"
            @update:modelValue="update('to', $event)"
            variant="underlined"
            maxlength="255"
            counter="255"
          ></v-text-field>
        </v-col>
      </v-row>

    </v-sheet>
  </v-container>
</template>

<style scoped>
</style>
