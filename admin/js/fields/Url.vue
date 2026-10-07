/** @license MIT, https://opensource.org/license/mit */

<script>
/**
 * Configuration:
 * - `hint`: string, description shown below the field while it has focus
 * - `absolute`: boolean, if true, relative paths, fragment/query links and page links are rejected
 * - `allowed`: array of strings, allowed URL schemas (e.g., ['http', 'https'])
 * - `placeholder`: string, placeholder text for the input field
 * - `rel`: boolean, if true, show relationship options for external links
 * - `required`: boolean, if true, the field is required
 *
 * Pages picked from the suggestions are stored as `page:<id>` links which are rendered
 * as URL of the page in the language of the current page.
 *
 * The `rel` prop carries the selected relationship. Its parent stores the value
 * in a sibling `<field>-rel` data property, keeping the URL itself a string.
 */

import gql from 'graphql-tag'
import { required } from '../rules'
import { debounce } from '../utils'
import { fieldBase } from '../field'

export default {
  extends: fieldBase,

  props: {
    rel: { type: String, default: '' }
  },

  emits: ['update:rel'],

  setup() {
    return { debounce }
  },

  data() {
    // Only allow plain alphabetic schemes into the pattern so a misconfigured
    // schema cannot inject regex metacharacters.
    const raw = this.config.allowed || ['http', 'https']
    const allowed = raw.every((s) => /^[a-z]+$/.test(s)) ? raw : ['http', 'https']

    return {
      relItems: [],
      loading: false,
      pages: [],
      linked: null,
      // Dot-separated labels keep this linear (no nested, ambiguous quantifiers)
      // to avoid catastrophic backtracking (ReDoS) on crafted input.
      regex: new RegExp(
        `^(?:(?:${allowed.join('|')})://)?(?:[^/@: ]+(?::[^/@: ]+)?@)?(?:(?:[0-9a-z]+(?:-[0-9a-z]+)*\\.)+[a-z]{2,}(?::[0-9]{1,5})?)?(?:/.*)?$`
      )
    }
  },

  created() {
    this.searchd = this.debounce(this.search, 300)
    this.resolve(this.modelValue)
    this.relItems = [
      { key: '', val: this.$gettext('None') },
      { key: 'sponsored', val: this.$gettext('Sponsored') },
      { key: 'nofollow', val: this.$gettext('Nofollow') }
    ]
  },

  watch: {
    modelValue(value) {
      this.resolve(value)
    }
  },

  computed: {
    // suggested pages and the linked page so the combobox shows its name instead of the ID
    items() {
      const list = this.linked ? [this.linked] : []
      return list.concat(this.pages.filter((item) => item.value !== this.linked?.value))
    },

    external() {
      return this.config.rel && /^(?:https?:)?\/\//i.test(this.modelValue ?? this.config.default ?? '')
    },

    rules() {
      return [
        required(this.$gettext, this.config.required),
        (v) => this.check(v) || this.$gettext(`Not a valid URL`)
      ]
    }
  },

  methods: {
    check(v) {
      const allowed = this.config.allowed || ['http', 'https']

      if (!allowed.every((s) => /^[a-z]+$/.test(s))) {
        return this.$gettext('Invalid URL schema configuration')
      }

      if (v && this.config.absolute) {
        try {
          const url = new URL(v)
          const scheme = url.protocol.slice(0, -1)

          return allowed.includes(scheme) && v.toLowerCase().startsWith(`${scheme}://`) && !!url.hostname
        } catch {
          return false
        }
      }

      return v ? /^[#?][^\s]*$/.test(v) || /^page:[A-Za-z0-9-]+$/.test(v) || this.regex.test(v) : true
    },

    item(page) {
      return { title: `${page.name || page.path} (/${page.path || ''})`, value: `page:${page.id}` }
    },

    resolve(value) {
      const id = /^page:([A-Za-z0-9-]+)$/.exec(value || '')?.[1]

      if (!id || this.linked?.value === value) {
        this.linked = id ? this.linked : null
        return
      }

      this.linked = { title: value, value }
      this.$apollo
        .query({
          query: gql`
            query ($id: ID!, $lang: String) {
              page(id: $id, lang: $lang) {
                id
                name
                path
              }
            }
          `,
          variables: { id, lang: this.$route?.query?.lang || null }
        })
        .then((result) => {
          if (result.data?.page && this.linked?.value === value) {
            this.linked = this.item(result.data.page)
          }
        })
        .catch((error) => {
          this.$log('Url::resolve(): Error fetching page', error)
        })
    },

    search(value) {
      // the combobox shows the title of the picked page as search text
      if (!value || this.config.absolute || this.items.some((item) => item.title === value)) {
        this.pages = []
        return
      }

      this.loading = true
      this.$apollo
        .query({
          query: gql`
            query pages($filter: PageFilter, $lang: String) {
              pages(first: 10, filter: $filter, lang: $lang) {
                data {
                  id
                  name
                  path
                }
              }
            }
          `,
          variables: {
            filter: { any: value.replace(/^\/+/, '') },
            lang: this.$route?.query?.lang || null
          }
        })
        .then((result) => {
          this.pages = (result.data?.pages?.data || []).map((page) => this.item(page))
        })
        .catch((error) => {
          this.$log('Url::search(): Error fetching pages', error)
        })
        .finally(() => {
          this.loading = false
        })
    }
  }
}
</script>

<template>
  <div class="url-field" :class="{ external }">
    <div class="url-row">
      <v-combobox
        :hint="config.hint && $pgettext('fh', config.hint)"
        :error="hasError"
        :rules="rules"
        :items="items"
        :return-object="false"
        :loading="loading"
        :readonly="readonly"
        :placeholder="config.placeholder || ''"
        :no-data-text="!loading ? $gettext('No pages found') : $gettext('Loading') + ' ...'"
        :modelValue="modelValue ?? config.default ?? ''"
        @update:modelValue="$emit('update:modelValue', $event)"
        @update:search="searchd($event)"
        density="comfortable"
        hide-details="auto"
        variant="outlined"
        class="url-input ltr"
        item-title="title"
        item-value="value"
        clearable
        no-filter
      ></v-combobox>
      <v-select
        v-if="external"
        :aria-label="$gettext('Link attribute')"
        :items="relItems"
        :readonly="readonly"
        :modelValue="rel"
        @update:modelValue="$emit('update:rel', $event)"
        density="comfortable"
        hide-details="auto"
        variant="outlined"
        item-title="val"
        item-value="key"
        class="link-rel"
      ></v-select>
    </div>
  </div>
</template>

<style scoped>
/* Layout depends on the width available to the field, not on the viewport */
.url-field {
  container-type: inline-size;
}

.url-row {
  display: flex;
  flex-direction: column;
}

.url-input {
  flex: 1 1 auto;
  min-width: 0;
}

.link-rel {
  margin-top: -1px;
}

@container (width < 576px) {
  .external :deep(.url-input .v-field) {
    border-end-start-radius: 0;
    border-end-end-radius: 0;
  }

  :deep(.link-rel .v-field) {
    border-start-start-radius: 0;
    border-start-end-radius: 0;
  }
}

@container (width >= 576px) {
  .url-row {
    flex-direction: row;
    align-items: flex-start;
  }

  .link-rel {
    flex: 0 0 10rem;
    margin-top: 0;
    margin-inline-start: -1px;
  }

  .external :deep(.url-input .v-field) {
    border-start-end-radius: 0;
    border-end-end-radius: 0;
  }

  :deep(.link-rel .v-field) {
    border-start-start-radius: 0;
    border-end-start-radius: 0;
  }
}
</style>
