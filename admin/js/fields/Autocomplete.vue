/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import { VAutocomplete } from 'vuetify/components/VAutocomplete'
import { maxEntries, required } from '../rules'
import { safeParse } from '../json'
import { debounce, REQUEST_DELAY } from '../utils'
import { fieldBase } from '../field'

/**
 * Configuration:
 * - `hint`: string, description shown below the field while it has focus
 * - `api-type`: string, "GQL" or "REST" to search entries via the `query` or `url` setting
 * - `default`: mixed, value used if none is set
 * - `empty-text`: string, text shown if no entries are found
 * - `item-title`: string, slash separated path to the label of each entry
 * - `item-value`: string, slash separated path to the value of each entry
 * - `list-key`: string, slash separated path to the list of entries in the REST response
 * - `max`: int, maximum number of entries allowed if multiple values can be selected
 * - `multiple`: boolean, if true, several entries can be selected
 * - `options`: array, entries available before searching
 * - `placeholder`: string, placeholder text for the input field
 * - `query`: string, GraphQL query where _term_ is replaced by the search term
 * - `required`: boolean, if true, the field must not be empty
 * - `url`: string, REST URL where _term_ is replaced by the search term
 */
export default {
  extends: fieldBase,

  props: {
    modelValue: { type: [Array, Object, String, Number, Boolean, null], default: undefined }
  },

  data() {
    return {
      list: this.options(),
      loading: false,
      // number of the latest request, older responses are ignored
      seq: 0
    }
  },

  created() {
    this.debouncedGraphql = debounce(this.graphql, REQUEST_DELAY)
    this.debouncedRest = debounce(this.rest, REQUEST_DELAY)
  },

  beforeUnmount() {
    this.debouncedGraphql.cancel()
    this.debouncedRest.cancel()
  },

  computed: {
    returnObject() {
      return !!this.config['item-title']
    },

    rules() {
      return [
        required(this.$gettext, this.config.required),
        (v) => !Array.isArray(v) || maxEntries(this.$ngettext, this.config.max)(v)
      ]
    },

    tag() {
      return VAutocomplete
    }
  },

  methods: {
    get(item, keys) {
      return keys.reduce((part, key) => {
        return typeof part === 'object' && part !== null ? part[key] : part
      }, item)
    },

    graphql(value) {
      if (!this.config?.query) {
        return
      }

      // JSON.stringify turns the term into a fully escaped GraphQL string literal,
      // so it stays a single value token and cannot alter the trusted query structure.
      const query = this.config.query.replace(/_term_/g, value ? JSON.stringify(value) : '""')

      const seq = ++this.seq

      this.loading = true
      // invalid configured queries must be handled like failed requests
      Promise.resolve()
        .then(() => this.$apollo.query({ query: gql(query) }))
        .then((result) => {
          if (seq !== this.seq) {
            return
          }

          // parse the latest data if available
          const list = this.toList(result.data).map((item) => {
            if (typeof item !== 'object' || item === null) {
              return item
            }

            return Object.assign({ ...item }, safeParse(item.latest?.data))
          })

          this.list = this.items(list)
        })
        .catch((error) => {
          this.$log('Autocomplete::graphql(): Error fetching data', value, error)
        })
        .finally(() => {
          if (seq === this.seq) {
            this.loading = false
          }
        })
    },

    /**
     * Maps result objects to the configured autocomplete label and value.
     */
    items(data) {
      const flabel = this.config['item-title']?.split('/')
      const fvalue = this.config['item-value']?.split('/')

      return (data || []).map((item) => {
        if (typeof item === 'object' && item !== null) {
          if (flabel) {
            return { label: this.get(item, flabel), value: fvalue ? this.get(item, fvalue) : item }
          } else if (fvalue) {
            return this.get(item, fvalue)
          }
        }

        return item
      })
    },

    rest(value) {
      if (!this.config?.url) {
        return
      }

      const seq = ++this.seq

      this.loading = true
      fetch(this.config.url.replace(/_term_/g, encodeURIComponent(value || '')), {
        mode: 'cors'
      })
        .then((response) => {
          if (!response.ok) {
            throw response
          }
          return response.json()
        })
        .then((result) => {
          if (seq === this.seq) {
            this.list = this.items(this.toList(result))
          }
        })
        .catch((error) => {
          this.$log('Autocomplete::rest(): Error fetching data', value, error)
        })
        .finally(() => {
          if (seq === this.seq) {
            this.loading = false
          }
        })
    },

    // fixed entries from the schema with translated labels, search results are shown as returned
    options() {
      return (this.config.options || []).map((item) =>
        typeof item === 'object' && item !== null && item.label ? { ...item, label: this.$pgettext('op', item.label) } : item
      )
    },

    search(value) {
      switch (this.config?.['api-type']) {
        case 'GQL':
          this.debouncedGraphql(value)
          break
        case 'REST':
          this.debouncedRest(value)
          break
      }
    },

    toList(result) {
      return this.config['list-key'] ? this.get(result, this.config['list-key'].split('/')) : result
    }
  }
}
</script>

<template>
  <!-- the default only applies to new values, a cleared selection stays empty -->
  <component
    :is="tag"
    :hint="config.hint && $pgettext('fh', config.hint)"
    :error="hasError"
    :rules="rules"
    :items="list"
    :loading="loading"
    :readonly="readonly"
    :clearable="!readonly"
    :no-data-text="
      !loading
        ? config['empty-text'] || $gettext('No data available')
        : $gettext('Loading') + ' ...'
    "
    :placeholder="config.placeholder || ''"
    :return-object="returnObject"
    :multiple="config.multiple"
    :chips="config.multiple"
    :modelValue="modelValue === undefined ? config.default ?? null : modelValue"
    @update:modelValue="$emit('update:modelValue', $event)"
    @update:search="search($event)"
    @update:menu="$event && search('')"
    density="comfortable"
    hide-details="auto"
    variant="outlined"
    item-title="label"
    item-value="value"
  ></component>
</template>
