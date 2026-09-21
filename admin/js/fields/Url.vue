/** @license MIT, https://opensource.org/license/mit */

<script>
/**
 * Configuration:
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
import { debounce } from '../utils'

export default {
  props: {
    modelValue: { type: String },
    rel: { type: String, default: '' },
    config: { type: Object, default: () => {} },
    assets: { type: Object, default: () => {} },
    readonly: { type: Boolean, default: false },
    context: { type: Object }
  },

  emits: ['update:modelValue', 'update:rel', 'error'],

  setup() {
    return { debounce }
  },

  data() {
    // Only allow plain alphabetic schemes into the pattern so a misconfigured
    // schema cannot inject regex metacharacters.
    const raw = this.config.allowed || ['http', 'https']
    const allowed = raw.every((s) => /^[a-z]+$/.test(s)) ? raw : ['http', 'https']

    return {
      lastError: null,
      relItems: [],
      loading: false,
      pages: [],
      // Dot-separated labels keep this linear (no nested, ambiguous quantifiers)
      // to avoid catastrophic backtracking (ReDoS) on crafted input.
      regex: new RegExp(
        `^(?:(?:${allowed.join('|')})://)?(?:[^/@: ]+(?::[^/@: ]+)?@)?(?:(?:[0-9a-z]+(?:-[0-9a-z]+)*\\.)+[a-z]{2,}(?::[0-9]{1,5})?)?(?:/.*)?$`
      )
    }
  },

  created() {
    this.searchd = this.debounce(this.search, 300)
    this.relItems = [
      { key: '', val: this.$gettext('None') },
      { key: 'sponsored', val: this.$gettext('Sponsored') },
      { key: 'nofollow', val: this.$gettext('Nofollow') }
    ]
  },

  computed: {
    external() {
      return this.config.rel && /^(?:https?:)?\/\//i.test(this.modelValue ?? this.config.default ?? '')
    },

    hasError() {
      const val = this.modelValue ?? this.config.default ?? ''
      return !this.rules.every((rule) => rule(val) === true)
    },

    rules() {
      return [
        (v) => !this.config.required || !!v || this.$gettext(`Value is required`),
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

      return v ? /^[#?][^\s]*$/.test(v) || this.regex.test(v) : true
    },

    search(value) {
      if (!value || this.config.absolute) {
        this.pages = []
        return
      }

      this.loading = true
      this.$apollo
        .query({
          query: gql`
            query pages($filter: PageFilter) {
              pages(first: 10, filter: $filter) {
                data {
                  path
                }
              }
            }
          `,
          variables: {
            filter: { any: value.replace(/^\/+/, '') }
          }
        })
        .then((result) => {
          this.pages = (result.data?.pages?.data || []).map((page) => '/' + (page.path || ''))
        })
        .catch((error) => {
          this.$log('Url::search(): Error fetching pages', error)
        })
        .finally(() => {
          this.loading = false
        })
    }
  },

  watch: {
    modelValue: {
      immediate: true,
      handler(val) {
        const hasError = !this.rules.every(
          (rule) => rule(val ?? this.config.default ?? '') === true
        )
        if (hasError !== this.lastError) {
          this.lastError = hasError
          this.$emit('error', hasError)
        }
      }
    }
  }
}
</script>

<template>
  <div class="url-field" :class="{ external }">
    <div class="url-row">
      <v-combobox
        :error="hasError"
        :rules="rules"
        :items="pages"
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
