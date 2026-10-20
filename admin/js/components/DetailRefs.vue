/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import { mdiLock } from '@mdi/js'
import { useMessageStore, useUserStore, useViewStack } from '../stores'
import { formatDate } from '../utils'

// indexes of the panels which are expanded initially
const OPEN_PANELS = [0, 1, 2]

const queries = new Map()
const views = {
  Element: () => import('../views/ElementDetail.vue'),
  File: () => import('../views/FileDetail.vue'),
  Page: () => import('../views/PageDetail.vue')
}

// returns the cached query for the pages, elements (files only) and versions referencing the item
function query(type) {
  if (!queries.has(type)) {
    queries.set(type, gql`
      query ($id: ID!) {
        ${type}(id: $id) {
          id
          bypages {
            id
            path
            name
            restricted
          }
          ${type === 'file' ? 'byelements { id type name }' : ''}
          byversions {
            id
            versionable_id
            versionable_type
            published
            publish_at
          }
        }
      }
    `)
  }

  return queries.get(type)
}

export default {
  props: {
    item: { type: Object, required: true },
    type: { type: String, required: true }
  },

  data: () => ({
    panel: [...OPEN_PANELS],
    versions: [],
    references: {},
    // number of the latest request, older responses are ignored; set up here because the
    // immediate item watcher runs before created()
    seq: 0
  }),

  setup() {
    const messages = useMessageStore()
    const viewStack = useViewStack()
    const user = useUserStore()
    return { messages, mdiLock, user, viewStack }
  },

  methods: {
    mapVersion(item) {
      const type = item.versionable_type.slice(item.versionable_type.lastIndexOf('\\') + 1)
      return {
        key: item.id,
        id: item.versionable_id,
        type,
        published: item.published
          ? this.$gettext('yes')
          : item.publish_at
            ? formatDate(item.publish_at, this.$vuetify.locale.current, false)
            : this.$gettext('no')
      }
    },

    // opens the detail view of the referenced element, file or page
    async open(type, item) {
      const { default: view } = await views[type]()
      this.viewStack.openView(view, { initial: item, stacked: true })
    }
  },

  watch: {
    // the parent replaces the item object on every save, only another item needs new references
    'item.id': {
      immediate: true,
      handler(id) {
        const seq = ++this.seq

        this.references = {}
        this.versions = []

        if (!id || !this.user.can(this.type + ':view')) {
          return
        }

        this.$apollo
          .query({
            query: query(this.type),
            fetchPolicy: 'no-cache',
            variables: {
              id
            }
          })
          .then((result) => {
            if (seq !== this.seq) {
              return
            }

            const refs = result.data?.[this.type] || {}
            this.references = Object.freeze({
              ...refs,
              bypages: Object.freeze((refs.bypages || []).map(p => Object.freeze(p))),
              byelements: Object.freeze((refs.byelements || []).map(e => Object.freeze(e)))
            })
            this.versions = Object.freeze((refs.byversions || [])
              .map((item) => Object.freeze(this.mapVersion(item)))
              .filter((item) => {
                return this.user.can(item.type.toLowerCase() + ':view')
              })
            )
          })
          .catch((error) => {
            if (seq === this.seq) {
              this.messages.error(this.$gettext('Error fetching references'), error, this.item)
            }
          })
      }
    }
  }
}
</script>

<template>
  <v-container>
    <v-sheet class="scroll refs">
      <v-expansion-panels v-model="panel" elevation="0" multiple>
        <v-expansion-panel v-if="references.bypages?.length && user.can('page:view')">
          <v-expansion-panel-title>{{ $gettext('Pages') }}</v-expansion-panel-title>
          <v-expansion-panel-text>
            <v-table class="pages" density="comfortable" hover>
              <thead>
                <tr>
                  <th>{{ $gettext('ID') }}</th>
                  <th>{{ $gettext('URL') }}</th>
                  <th>{{ $gettext('Name') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="v in references.bypages" :key="v.id" tabindex="0" @click="open('Page', v)" @keydown.enter="open('Page', v)">
                  <td>{{ v.id }}</td>
                  <td>{{ '/' + v.path }}</td>
                  <td>
                    <v-icon
                      v-if="v.restricted"
                      class="item-access"
                      :icon="mdiLock"
                      :title="$gettext('Restricted')"
                    />
                    {{ v.name }}
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel v-if="references.byelements?.length && user.can('element:view')">
          <v-expansion-panel-title>{{ $gettext('Elements') }}</v-expansion-panel-title>
          <v-expansion-panel-text>
            <v-table class="elements" density="comfortable" hover>
              <thead>
                <tr>
                  <th>{{ $gettext('ID') }}</th>
                  <th>{{ $gettext('Type') }}</th>
                  <th>{{ $gettext('Name') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="v in references.byelements" :key="v.id" tabindex="0" @click="open('Element', v)" @keydown.enter="open('Element', v)">
                  <td>{{ v.id }}</td>
                  <td>{{ v.type }}</td>
                  <td>{{ v.name }}</td>
                </tr>
              </tbody>
            </v-table>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel v-if="versions?.length">
          <v-expansion-panel-title>{{ $gettext('Versions') }}</v-expansion-panel-title>
          <v-expansion-panel-text>
            <v-table class="versions" density="comfortable" hover>
              <thead>
                <tr>
                  <th>{{ $gettext('ID') }}</th>
                  <th>{{ $gettext('Type') }}</th>
                  <th>{{ $gettext('Published') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="v in versions" :key="v.key" tabindex="0" @click="open(v.type, { id: v.id })" @keydown.enter="open(v.type, { id: v.id })">
                  <td>{{ v.id }}</td>
                  <td>{{ v.type }}</td>
                  <td>{{ v.published }}</td>
                </tr>
              </tbody>
            </v-table>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
    </v-sheet>
  </v-container>
</template>

<style scoped>
.v-sheet.scroll {
  height: var(--cms-detail-height);
}
</style>
