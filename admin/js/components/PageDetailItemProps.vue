/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import { domain } from '../rules'
import { useAppStore, useUserStore, useSchemaStore } from '../stores'
import { cacheItems, localeItems, statusItems } from '../options'
import { debounce, INPUT_DELAY, REQUEST_DELAY, slugify } from '../utils'

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

  emits: ['patch', 'error'],

  data: () => ({
    errors: {},
    fieldMessages: {},
    // sequence numbers of the latest path check and validation
    pathSeq: 0,
    validateSeq: 0
  }),

  setup() {
    const schemas = useSchemaStore()
    const user = useUserStore()
    const app = useAppStore()

    return { app, user, schemas, slugify, localeItems, cacheItems: cacheItems(), statusItems: statusItems() }
  },

  created() {
    this.debouncedCheckPath = debounce(this.checkPath, REQUEST_DELAY)
    this.validated = debounce(() => this.validate(true), INPUT_DELAY)
    this.defaultLanguage()
  },

  mounted() {
    // the form shows its errors when it opens, the field refs exist from now on
    this.validated()
  },

  beforeUnmount() {
    this.debouncedCheckPath.cancel()
    this.validated.cancel()
  },

  computed: {
    // values whose changes revalidate the form
    validatedValues() {
      const { title, path, domain, status, theme, type, to, cache, tag, name } = this.item
      return [title, path, domain, status, theme, type, to, cache, tag, name]
    },

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
    },

    // schemas without page types only add elements and aren't selectable as themes
    themeItems() {
      return Object.keys(this.schemas.themes).filter((name) => this.schemas.themes[name].types)
    },

    // the only configured language, there's nothing to choose from then
    defaultLang() {
      const list = this.localeItems()
      return list.length === 1 ? list[0].value : null
    }
  },

  methods: {
    // resolves to the messages of the latest path check, older responses wait for the latest one
    checkPath() {
      const seq = ++this.pathSeq

      this.pathCheck = this.$apollo
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
          if (seq !== this.pathSeq) {
            return this.pathCheck
          }

          this.fieldMessages.path = result?.data?.pages?.data?.some((page) => page.id !== this.item.id)
            ? [this.$gettext('The path is already in use by another page')]
            : []

          return this.fieldMessages.path
        })
        .catch((error) => {
          this.$log('PageDetailItemProps::checkPath: Error checking path', error)
          return []
        })

      return this.pathCheck || []
    },

    // sets the only configured language if none is chosen
    defaultLanguage() {
      if (!this.item.lang && this.defaultLang && !this.readonly) {
        this.update('lang', this.defaultLang)
      }
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
        this.update('type', '')
      }
    },

    update(what, value) {
      this.$emit('patch', { [what]: typeof value === 'string' ? value.trim() : value })
    },

    updatePath(value) {
      this.update('path', this.slugify(value))
      this.validate()
    },

    // emits the combined error state of the latest validation
    async validate(lazy = false) {
      const seq = ++this.validateSeq
      await this.$nextTick()
      const list = [lazy ? this.debouncedCheckPath() : this.checkPath()]

      Object.values(this.$refs).filter(field => field).forEach((field) => {
        list.push(field.validate())
      })

      return Promise.all(list).then((result) => {
        const res = result.reduce((sum, r) => sum + (r?.length || 0), 0)

        if (seq === this.validateSeq) {
          this.$emit('error', !!res)
        }
        return res || true
      })
    }
  },

  watch: {
    validatedValues() {
      this.validated()
    },

    'item.lang'() {
      this.defaultLanguage()
      this.validated()
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
        <v-col v-if="!defaultLang || item.lang !== defaultLang" cols="12" md="6">
          <v-select
            ref="lang"
            :items="localeItems()"
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
            class="path"
            :rules="pathRules"
            :error="!!(fieldMessages.path || []).length"
            :error-messages="fieldMessages.path"
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
            :items="themeItems"
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

.path :deep(.v-field__input) {
  padding-inline: 0;
}
</style>
