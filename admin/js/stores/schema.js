/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { markRaw } from 'vue'
import { defineStore } from 'pinia'
import { apolloClient } from '../graphql'
import { safeParse, sanitize } from '../json'

const FETCH_SCHEMAS = gql`
  query {
    schemas {
      name
      label
      types
      content
      meta
      config
    }
  }
`

/**
 * Available element schemas fetched from GraphQL
 */
let _generation = 0
let _loading = null

function restart() {
  _generation++
  _loading = null
}

export const useSchemaStore = defineStore('schema', {
  state: () => ({ themes: {}, content: {}, meta: {}, config: {} }),
  actions: {
    clear() {
      restart()
      this.$reset()
    },

    load(fresh = false) {
      if (_loading) return _loading instanceof Promise ? _loading : Promise.resolve()

      const generation = _generation

      _loading = apolloClient.query({
        query: FETCH_SCHEMAS,
        fetchPolicy: fresh ? 'network-only' : 'cache-first'
      }).then((result) => {
        if (generation !== _generation) return

        const content = {}, meta = {}, config = {}
        const parse = (v) => typeof v === 'string' ? safeParse(v) : sanitize(v || {})
        const list = (result.data?.schemas || []).map(t => markRaw({
          ...t,
          types: parse(t.types),
          content: parse(t.content),
          meta: parse(t.meta),
          config: parse(t.config)
        }))

        for (const theme of list) {
          for (const key in theme.content) content[key] = markRaw(theme.content[key])
          for (const key in theme.meta) meta[key] = markRaw(theme.meta[key])
          for (const key in theme.config) config[key] = markRaw(theme.config[key])
        }

        this.themes = Object.freeze(Object.fromEntries(list.map(t => [t.name, t])))
        this.content = Object.freeze(content)
        this.meta = Object.freeze(meta)
        this.config = Object.freeze(config)

        _loading = true
      }).catch((err) => {
        if (generation === _generation) _loading = null
        throw err
      }).then(() => generation === _generation ? _loading : null)

      return _loading
    },

    reload() {
      restart()
      return this.load(true)
    }
  }
})
