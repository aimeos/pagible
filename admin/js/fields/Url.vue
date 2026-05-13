/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
/**
 * Configuration:
 * - `allowed`: array of strings, allowed URL schemas (e.g., ['http', 'https'])
 * - `placeholder`: string, placeholder text for the input field
 * - `required`: boolean, if true, the field is required
 */

import gql from 'graphql-tag'
import { debounce } from '../utils'

export default {
  props: {
    modelValue: { type: String },
    config: { type: Object, default: () => {} },
    assets: { type: Object, default: () => {} },
    readonly: { type: Boolean, default: false },
    context: { type: Object }
  },

  emits: ['update:modelValue', 'error'],

  setup() {
    return { debounce }
  },

  data() {
    const allowed = this.config.allowed || ['http', 'https']

    return {
      lastError: null,
      loading: false,
      pages: [],
      regex: new RegExp(
        `^((${allowed.join('|')}://)?([^/@: ]+(:[^/@: ]+)?@)?([0-9a-z]+(-[0-9a-z]+)*\\.)*[0-9a-z]+(-[0-9a-z]+)*\\.[a-z]{2,}(:[0-9]{1,5})?)?(/.*)?$`
      )
    }
  },

  created() {
    this.searchd = this.debounce(this.search, 300)
  },

  computed: {
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

      if (!allowed.every((s) => /^[a-z]+/.test(s))) {
        return this.$gettext('Invalid URL schema configuration')
      }

      return v ? this.regex.test(v) : true
    },

    search(value) {
      if (!value) {
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
        const hasError = !this.rules.every((rule) => rule(val ?? this.config.default ?? '') === true)
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
    class="ltr"
    clearable
  ></v-combobox>
</template>
