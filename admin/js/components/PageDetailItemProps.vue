/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import {
  useAppStore,
  useUserStore,
  useSchemaStore,
  useLanguageStore,
  useSideStore
} from '../stores'
import { debounce, locales, slugify } from '../utils'

const DOMAIN_REGEX = /^([0-9a-z]+[.-])*[0-9a-z]+\.[a-z]{2,}$/
const REDIRECT_REGEX = /^((https?:)?\/\/([^\s/:@]+(:[^\s/:@]+)?@)?([0-9a-z]+(\.|-))*[0-9a-z]+\.[a-z]{2,}(:[0-9]{1,5})?)?(\/[^\s]*)?$/

const CHECK_PATH = gql`
  query ($filter: PageFilter) {
    pages(filter: $filter) {
      data {
        id
      }
    }
  }
`

export default {
  props: {
    item: { type: Object, required: true },
    assets: { type: Object, default: () => {} }
  },

  emits: ['change', 'error'],

  data: () => ({
    cacheItems: [],
    statusItems: [],
    errors: {},
    messages: {}
  }),

  setup() {
    const languages = useLanguageStore()
    const schemas = useSchemaStore()
    const side = useSideStore()
    const user = useUserStore()
    const app = useAppStore()

    return { app, user, side, schemas, languages, debounce, slugify, locales }
  },

  created() {
    this.checkPathd = this.debounce(this.checkPath, 500)
    this.validated = this.debounce(() => this.validate(true), 300)
    this.cacheItems = [
      { key: 0, val: this.$gettext('No cache') },
      { key: 1, val: this.$ngettext('%{num} minute', '%{num} minutes', 1, { num: 1 }) },
      { key: 5, val: this.$ngettext('%{num} minute', '%{num} minutes', 5, { num: 5 }) },
      { key: 15, val: this.$ngettext('%{num} minute', '%{num} minutes', 15, { num: 15 }) },
      { key: 30, val: this.$ngettext('%{num} minute', '%{num} minutes', 30, { num: 30 }) },
      { key: 60, val: this.$ngettext('%{num} hour', '%{num} hours', 1, { num: 1 }) },
      { key: 180, val: this.$ngettext('%{num} hour', '%{num} hours', 3, { num: 3 }) },
      { key: 360, val: this.$ngettext('%{num} hour', '%{num} hours', 6, { num: 6 }) },
      { key: 720, val: this.$ngettext('%{num} hour', '%{num} hours', 12, { num: 12 }) },
      { key: 1440, val: this.$ngettext('%{num} hour', '%{num} hours', 24, { num: 24 }) }
    ]
    this.statusItems = [
      { key: 0, val: this.$gettext('Disabled') },
      { key: 1, val: this.$gettext('Enabled') },
      { key: 2, val: this.$gettext('Hidden in navigation') }
    ]
  },

  computed: {

    domainRules() {
      return [
        (v) => !!v || this.$gettext('Field is required'),
        (v) => !v || DOMAIN_REGEX.test(v) || this.$gettext('Domain name is invalid')
      ]
    },

    pathRules() {
      return [
        (v) => !v || (v && v[0] !== '/') || this.$gettext('Path must not start with a slash (/)')
      ]
    },

    readonly() {
      return !this.user.can('page:save')
    },

    redirectRules() {
      return [
        (v) => !v || REDIRECT_REGEX.test(v) || this.$gettext('URL is not valid')
      ]
    },

    titleRules() {
      return [(v) => !!v || this.$gettext('Field is required')]
    }
  },

  methods: {
    checkPath() {
      return (
        this.$apollo
          ?.query({
            query: CHECK_PATH,
            variables: {
              filter: {
                path: this.item.path || '',
                domain: this.item.domain || ''
              }
            }
          })
          .then((result) => {
            if (
              result?.data?.pages?.data?.length > 0 &&
              result?.data?.pages?.data?.some((page) => page.id != this.item.id)
            ) {
              this.messages.path = [this.$gettext('The path is already in use by another page')]
            } else {
              this.messages.path = []
            }

            this.$emit('error', !!this.messages.path.length)
            return this.messages.path
          })
          .catch((error) => {
            this.$log('PageDetailItemProps::checkPath: Error checking path', error)
          }) || []
      )
    },

    reset() {
      this.errors = {}
    },

    setPath(focused) {
      if (!focused && this.item.path?.at(0) === '_') {
        this.updatePath(this.item.title)
      }
    },

    themeUpdated(event) {
      this.update('theme', event)
      this.item.type = ''
    },

    update(what, value) {
      this.item[what] = typeof value === 'string' ? value.trim() : value
      this.$emit('change', true)
    },

    updatePath(value) {
      this.update('path', this.slugify(value))
      this.checkPath()
    },

    async validate(lazy = false) {
      await this.$nextTick()
      const list = [lazy ? this.checkPathd() : this.checkPath()]

      Object.values(this.$refs).forEach((field) => {
        list.push(field.validate())
      })

      return Promise.all(list).then((result) => {
        const res = result.reduce((sum, r) => sum + (r?.length || 0), 0)
        this.$emit('error', !!res)
        return res || true
      })
    }
  },

  watch: {
    'item.title': {
      immediate: true,
      handler() { this.validated?.() }
    },
    'item.path': {
      immediate: true,
      handler() { this.validated?.() }
    },
    'item.domain': {
      immediate: true,
      handler() { this.validated?.() }
    },
    'item.status': {
      immediate: true,
      handler() { this.validated?.() }
    },
    'item.lang': {
      immediate: true,
      handler() { this.validated?.() }
    },
    'item.theme': {
      immediate: true,
      handler() { this.validated?.() }
    },
    'item.type': {
      immediate: true,
      handler() { this.validated?.() }
    },
    'item.to': {
      immediate: true,
      handler() { this.validated?.() }
    },
    'item.cache': {
      immediate: true,
      handler() { this.validated?.() }
    },
    'item.tag': {
      immediate: true,
      handler() { this.validated?.() }
    },
    'item.name': {
      immediate: true,
      handler() { this.validated?.() }
    }
  }
}
</script>

<template>
  <v-container>
    <v-sheet>
      <v-row>
        <v-col cols="12" md="6">
          <v-select
            ref="status"
            :items="statusItems"
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
          <v-select
            ref="lang"
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
          <v-text-field
            ref="title"
            :rules="titleRules"
            :readonly="readonly"
            :modelValue="item.title"
            :label="$gettext('Page title')"
            @update:modelValue="update('title', $event)"
            @update:focused="setPath($event)"
            variant="underlined"
            maxlength="60"
            counter="60"
          ></v-text-field>
          <v-text-field
            ref="name"
            :readonly="readonly"
            :modelValue="item.name"
            :label="$gettext('Page name')"
            @update:modelValue="update('name', $event)"
            variant="underlined"
            counter="30"
            maxlength="30"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="6">
          <v-text-field
            ref="path"
            :rules="pathRules"
            :error="!!(messages.path || []).length"
            :error-messages="messages.path"
            :readonly="readonly"
            :modelValue="item.path"
            :label="$gettext('URL path')"
            @update:modelValue="update('path', $event)"
            @change="updatePath($event.target.value)"
            variant="underlined"
            maxlength="255"
            counter="255"
          ></v-text-field>
          <v-text-field
            v-if="app.multidomain"
            ref="domain"
            :rules="domainRules"
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
          <v-select
            ref="theme"
            :readonly="readonly"
            :modelValue="item.theme"
            :label="$gettext('Theme')"
            :items="Object.keys(schemas.themes)"
            @update:modelValue="themeUpdated"
            variant="underlined"
          ></v-select>
          <v-select
            ref="type"
            :readonly="readonly"
            :modelValue="item.type"
            :label="$gettext('Page type')"
            :items="Object.keys(schemas.themes[item.theme || 'cms']?.types || { page: '' })"
            @update:modelValue="update('type', $event)"
            variant="underlined"
          ></v-select>
        </v-col>
        <v-col cols="12" md="6">
          <v-text-field
            ref="tag"
            :modelValue="item.tag"
            :readonly="readonly"
            :label="$gettext('Page tag')"
            @update:modelValue="update('tag', $event)"
            variant="underlined"
            maxlength="30"
            counter="30"
          ></v-text-field>
          <v-select
            ref="cache"
            :items="cacheItems"
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
          <v-text-field
            ref="to"
            :rules="redirectRules"
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

<style scoped></style>
