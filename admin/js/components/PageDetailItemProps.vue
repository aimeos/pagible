/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import { domain } from '../rules'
import { useAppStore, useUserStore, useSchemaStore } from '../stores'
import { cacheItems, debounce, locales, slugify, statusItems } from '../utils'

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
    item: { type: Object, required: true }
  },

  emits: ['change', 'error'],

  data: () => ({
    errors: {},
    messages: {}
  }),

  setup() {
    const schemas = useSchemaStore()
    const user = useUserStore()
    const app = useAppStore()

    return { app, user, schemas, debounce, slugify, locales, cacheItems: cacheItems(), statusItems: statusItems() }
  },

  created() {
    this.checkPathd = this.debounce(this.checkPath, 500)
    this.validated = this.debounce(() => this.validate(true), 300)
  },

  computed: {
    domainRules() {
      return [...this.requiredRules, domain(this.$gettext)]
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

    requiredRules() {
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
              result?.data?.pages?.data?.some((page) => page.id !== this.item.id)
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

      const types = this.schemas.themes[event || 'cms']?.types || { page: '' }

      if (!types[this.item.type]) {
        this.item.type = ''
      }
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

      Object.values(this.$refs).filter(field => field).forEach((field) => {
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
            :label="$gettext('Status') + ' ‒ ' + $gettext('Disabled pages are offline, hidden pages are online but not shown in the navigation')"
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
            :rules="requiredRules"
            :readonly="readonly || item.variants?.some((v) => v.id && v.lang !== item.lang)"
            :modelValue="item.lang"
            :label="$gettext('Language') + ' ‒ ' + $gettext('Language of the page content')"
            @update:modelValue="update('lang', $event)"
            variant="underlined"
          ></v-select>
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12" md="6">
          <v-text-field
            ref="title"
            :rules="requiredRules"
            :readonly="readonly"
            :modelValue="item.title"
            :label="$gettext('Page title') + ' ‒ ' + $gettext('Shown in the browser tab and search results')"
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
            :label="$gettext('Page name') + ' ‒ ' + $gettext('Short name shown in the navigation menus')"
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
            :label="$gettext('URL path') + ' ‒ ' + $gettext('Part of the page URL after the domain, e.g. about-us')"
            @update:modelValue="update('path', $event)"
            @change="updatePath($event.target.value)"
            variant="underlined"
            maxlength="255"
            counter="255"
          >
            <template #prepend-inner>
              <span class="path-prefix">/</span>
            </template>
          </v-text-field>
          <v-text-field
            v-if="app.multidomain"
            ref="domain"
            :rules="domainRules"
            :readonly="readonly"
            :modelValue="item.domain"
            :label="$gettext('Domain') + ' ‒ ' + $gettext('Domain the page is available at, e.g. example.com')"
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
            :label="$gettext('Theme') + ' ‒ ' + $gettext('Design used to display the page')"
            :items="Object.keys(schemas.themes)"
            @update:modelValue="themeUpdated"
            variant="underlined"
          ></v-select>
          <v-select
            ref="type"
            :readonly="readonly"
            :modelValue="item.type"
            :label="$gettext('Page type') + ' ‒ ' + $gettext('Template of the theme which defines the page layout')"
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
            :label="$gettext('Page tag') + ' ‒ ' + $gettext('Internal identifier to find the page in templates, e.g. blog')"
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
            :label="$gettext('Cache time') + ' ‒ ' + $gettext('How long the generated page is cached before it is created again')"
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
            :label="$gettext('Redirect URL') + ' ‒ ' + $gettext('Visitors are forwarded to this URL instead of seeing the page')"
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
:deep(.v-field__prefix) {
  background: rgba(var(--v-theme-on-surface), 0.04);
  border-inline-end: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding-inline: 8px;
  margin-inline-end: 4px;
  align-self: stretch;
  display: flex;
  align-items: center;
}

:deep(.v-text-field--prefixed.v-text-field .v-field:not(.v-field--reverse) .v-field__input) {
  --v-field-padding-start: 0;
}
</style>
