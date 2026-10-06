/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import { mdiLock } from '@mdi/js'
import { useUserStore, useViewStack } from '../stores'

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
    panel: [0, 1, 2],
    versions: {},
    refs: {}
  }),

  setup() {
    const viewStack = useViewStack()
    const user = useUserStore()
    return { mdiLock, user, viewStack }
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
            ? new Date(item.publish_at).toLocaleDateString()
            : this.$gettext('no')
      }
    },

    // opens the detail view of the referenced element, file or page
    async open(type, item) {
      const { default: view } = await views[type]()
      this.viewStack.openView(view, { item: { ...item }, stacked: true })
    }
  },

  watch: {
    item: {
      immediate: true,
      handler(item) {
        if (!item.id || !this.user.can(this.type + ':view')) {
          return
        }

        this.$apollo
          .query({
            query: query(this.type),
            fetchPolicy: 'no-cache',
            variables: {
              id: item.id
            }
          })
          .then((result) => {
            const refs = result.data?.[this.type] || {}
            this.refs = Object.freeze({
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
            this.$log(`DetailRefs::watch(item): Error fetching ${this.type} references`, item, error)
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
        <v-expansion-panel v-if="refs.bypages?.length && user.can('page:view')">
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
                <tr v-for="v in refs.bypages" :key="v.id" @click="open('Page', v)">
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

        <v-expansion-panel v-if="refs.byelements?.length && user.can('element:view')">
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
                <tr v-for="v in refs.byelements" :key="v.id" @click="open('Element', v)">
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
                <tr v-for="v in versions" :key="v.key" @click="open(v.type, { id: v.id })">
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
  height: calc(100vh - 96px);
}
</style>
