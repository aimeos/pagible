/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import { useMessageStore } from '../stores'
import { PAGE_BULK_LIMIT } from '../utils'

const FETCH_ACCESS = gql`
  query PageAccessValues {
    access
  }
`

const SET_PAGE_ACCESS = gql`
  mutation ($id: [ID!]!, $access: [String!], $descendants: Boolean) {
    setPageAccess(id: $id, access: $access, descendants: $descendants)
  }
`

export default {
  props: {
    ids: { type: Array, required: true },
    access: { default: undefined },
    descendants: { type: Number, default: 0 }
  },

  emits: ['applied'],

  setup() {
    const messages = useMessageStore()

    return { messages }
  },

  data() {
    return {
      items: [],
      mode: null,
      values: [],
      loading: false,
      saving: false
    }
  },

  computed: {
    hasDescendants() {
      return this.ids.length === 1 && this.descendants > 0
    },

    limited() {
      return this.ids.length > PAGE_BULK_LIMIT
    },

    recurseLimited() {
      return this.ids.length + this.descendants > PAGE_BULK_LIMIT
    },

    valid() {
      return !!this.mode && (this.mode !== 'restricted' || this.values.length > 0)
    },

    value() {
      if (this.mode === 'public') return null
      if (this.mode === 'authenticated') return []
      return [...this.values]
    }
  },

  created() {
    this.reset()
    this.load()
  },

  methods: {
    async apply(descendants = false) {
      const blocked = descendants ? !this.hasDescendants || this.recurseLimited : this.limited

      if (!this.valid || !this.ids.length || this.saving || blocked) return

      this.saving = true

      try {
        const response = await this.$apollo.mutate({
          mutation: SET_PAGE_ACCESS,
          variables: {
            id: this.ids,
            access: this.value,
            descendants
          }
        })

        if (response.errors) throw response.errors

        this.$emit('applied', this.value, descendants)
      } catch (error) {
        this.messages.add(this.$gettext('Error changing page access') + ':\n' + error, 'error')
        this.$log('PageAccess::apply(): Error changing page access', this.ids, error)
      } finally {
        this.saving = false
      }
    },

    async load() {
      this.loading = true

      try {
        const response = await this.$apollo.query({
          query: FETCH_ACCESS,
          fetchPolicy: 'network-only'
        })

        this.items = response.data.access || []
      } catch (error) {
        this.messages.add(this.$gettext('Error fetching access values') + ':\n' + error, 'error')
        this.$log('PageAccess::load(): Error fetching access values', error)
      } finally {
        this.loading = false
      }
    },

    reset() {
      if (this.access === undefined) {
        this.mode = null
        this.values = []
      } else if (this.access === null) {
        this.mode = 'public'
        this.values = []
      } else if (this.access.length === 0) {
        this.mode = 'authenticated'
        this.values = []
      } else {
        this.mode = 'restricted'
        this.values = [...this.access]
      }
    }
  },

  watch: {
    access: {
      deep: true,
      handler() {
        this.reset()
      }
    }
  }
}
</script>

<template>
  <v-container class="page-access">
    <p class="hint">{{ $gettext('Access changes take effect immediately') }}</p>

    <v-radio-group v-model="mode" :disabled="saving">
      <v-radio value="public" :label="$gettext('Public')" />
      <v-radio value="authenticated" :label="$gettext('Authenticated users')" />
      <v-radio
        value="restricted"
        :label="$gettext('Restricted')"
        :disabled="!items.length && !values.length"
      />
    </v-radio-group>

    <v-select
      v-if="mode === 'restricted'"
      v-model="values"
      :items="items"
      :label="$gettext('Access')"
      :loading="loading"
      :disabled="saving"
      variant="underlined"
      multiple
      chips
    />

    <p v-if="limited || recurseLimited" class="hint">
      {{ $gettext('Page bulk changes are limited to 1,000 pages') }}
    </p>

    <div class="actions">
      <v-btn
        class="btn-apply-access"
        variant="outlined"
        :loading="saving"
        :disabled="!valid || limited"
        @click="apply(false)"
      >{{ $gettext('Apply') }}</v-btn>
      <v-btn
        v-if="hasDescendants"
        class="btn-apply-access-recursive"
        variant="outlined"
        :loading="saving"
        :disabled="!valid || recurseLimited"
        @click="apply(true)"
      >{{ $gettext('Apply recursively') }} ({{ ids.length + descendants }})</v-btn>
    </div>
  </v-container>
</template>

<style scoped>
.hint {
  color: rgb(var(--v-theme-on-surface-variant));
  margin-bottom: 8px;
}

.actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 16px;
}
</style>
