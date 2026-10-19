/** @license MIT, https://opensource.org/license/mit */

<script>
/**
 * Configuration:
 * - `hint`: string, description shown below the field while it has focus
 * - `absolute`: boolean, if true, relative paths and fragment/query links are rejected
 * - `allowed`: array of strings, allowed URL schemas (e.g., ['http', 'https'])
 * - `placeholder`: string, placeholder text for the input field
 * - `rel`: boolean, if true, show relationship options for external links
 * - `required`: boolean, if true, the field is required
 *
 * The `rel` prop carries the selected relationship. Its parent stores the value
 * in a sibling `<field>-rel` data property, keeping the URL itself a string.
 */

import gql from 'graphql-tag'
import { required } from '../rules'
import { debounce } from '../utils'
import { fieldBase } from '../field'

const DEFAULT_SCHEMES = ['http', 'https']

export default {
  extends: fieldBase,

  // language of the page being edited, provided by the page editor
  inject: {
    pageLang: { default: null }
  },

  props: {
    rel: { type: String, default: '' }
  },

  emits: ['update:rel'],

  data() {
    return {
      relItems: [],
      loading: false,
      pages: []
    }
  },

  created() {
    this.debouncedSearch = debounce(this.search, 300)
    this.searchSeq = 0
    this.relItems = [
      { key: '', val: this.$gettext('None') },
      { key: 'sponsored', val: this.$gettext('Sponsored') },
      { key: 'nofollow', val: this.$gettext('Nofollow') }
    ]
  },

  beforeUnmount() {
    this.debouncedSearch.cancel()
    this.searchSeq++
  },

  computed: {
    allowed() {
      return this.config.allowed || DEFAULT_SCHEMES
    },

    // Only plain alphabetic schemes are allowed into the pattern so a misconfigured
    // schema cannot inject regex metacharacters.
    allowedValid() {
      return this.allowed.every((s) => /^[a-z]+$/.test(s))
    },

    external() {
      return this.config.rel && /^(?:https?:)?\/\//i.test(this.modelValue ?? this.config.default ?? '')
    },

    // Dot-separated labels keep this linear (no nested, ambiguous quantifiers)
    // to avoid catastrophic backtracking (ReDoS) on crafted input.
    regex() {
      const allowed = this.allowedValid ? this.allowed : DEFAULT_SCHEMES

      return new RegExp(
        `^(?:(?:${allowed.join('|')})://)?(?:[^/@: ]+(?::[^/@: ]+)?@)?(?:(?:[0-9a-z]+(?:-[0-9a-z]+)*\\.)+[a-z]{2,}(?::[0-9]{1,5})?)?(?:/.*)?$`
      )
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
      if (!this.allowedValid) {
        return this.$gettext('Invalid URL schema configuration')
      }

      if (v && this.config.absolute) {
        try {
          const url = new URL(v)
          const scheme = url.protocol.slice(0, -1)

          return this.allowed.includes(scheme) && v.toLowerCase().startsWith(`${scheme}://`) && !!url.hostname
        } catch {
          return false
        }
      }

      return v ? /^[#?][^\s]*$/.test(v) || this.regex.test(v) : true
    },

    search(value) {
      // invalidates pending requests so older responses can't overwrite newer results
      const seq = ++this.searchSeq

      if (!value || this.config.absolute) {
        this.pages = []
        this.loading = false
        return
      }

      this.loading = true
      this.$apollo
        .query({
          query: gql`
            query pages($filter: PageFilter, $lang: String) {
              pages(first: 10, filter: $filter, lang: $lang) {
                data {
                  path
                }
              }
            }
          `,
          variables: {
            filter: { any: value.replace(/^\/+/, '') },
            lang: this.pageLang?.() || null
          }
        })
        .then((result) => {
          if (seq !== this.searchSeq) return
          this.pages = (result.data?.pages?.data || []).map((page) => '/' + (page.path || ''))
        })
        .catch((error) => {
          // the suggestions are optional and the URL can be entered without them
          this.$log('Url::search(): Error fetching pages', error)
        })
        .finally(() => {
          if (seq === this.searchSeq) this.loading = false
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
        :items="pages"
        :loading="loading"
        :readonly="readonly"
        :placeholder="config.placeholder || ''"
        :no-data-text="!loading ? $gettext('No pages found') : $gettext('Loading') + ' ...'"
        :modelValue="modelValue ?? config.default ?? ''"
        @update:modelValue="$emit('update:modelValue', $event)"
        @update:search="debouncedSearch($event)"
        density="comfortable"
        hide-details="auto"
        variant="outlined"
        class="url-input ltr"
        clearable
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
