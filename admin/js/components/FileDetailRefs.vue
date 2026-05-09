/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import { useUserStore, useViewStack } from '../stores'

const FETCH_FILE_REFS = gql`
  query ($id: ID!) {
    file(id: $id) {
      id
      bypages {
        id
        path
        name
      }
      byelements {
        id
        type
        name
      }
      byversions {
        id
        versionable_id
        versionable_type
        published
        publish_at
      }
    }
  }
`

export default {
  props: {
    item: { type: Object, required: true }
  },

  emits: [],

  data: () => ({
    panel: [0, 1, 2],
    versions: {},
    file: {}
  }),

  setup() {
    const viewStack = useViewStack()
    const user = useUserStore()
    return { user, viewStack }
  },

  beforeUnmount() {
    this.versions = null
    this.file = null
  },

  methods: {
    mapVersion(item) {
      const type = item.versionable_type.slice(item.versionable_type.lastIndexOf('\\') + 1)
      return {
        id: item.versionable_id,
        type,
        published: item.published
          ? this.$gettext('yes')
          : item.publish_at
            ? new Date(item.publish_at).toLocaleDateString()
            : this.$gettext('no')
      }
    },

    async openElement(item) {
      const { default: ElementDetail } = await import('../views/ElementDetail.vue')
      this.viewStack.openView(ElementDetail, { item: { ...item } })
    },

    async openPage(item) {
      const { default: PageDetail } = await import('../views/PageDetail.vue')
      this.viewStack.openView(PageDetail, { item: { ...item } })
    }
  },

  watch: {
    item: {
      immediate: true,
      handler(item) {
        if (!item.id || !this.user.can('file:view')) {
          return
        }

        this.$apollo
          .query({
            query: FETCH_FILE_REFS,
            fetchPolicy: 'no-cache',
            variables: {
              id: item.id
            }
          })
          .then((result) => {
            if (result.errors) {
              throw result.errors
            }

            const file = result.data?.file || {}
            this.file = Object.freeze({
              ...file,
              bypages: Object.freeze((file.bypages || []).map(p => Object.freeze(p))),
              byelements: Object.freeze((file.byelements || []).map(e => Object.freeze(e)))
            })
            this.versions = Object.freeze((result.data?.file?.byversions || [])
              .map((item) => Object.freeze(this.mapVersion(item)))
              .filter((item) => {
                return this.user.can(item.type.toLowerCase() + ':view')
              })
            )
          })
          .catch((error) => {
            this.$log(`FileDetailRef::watch(item): Error fetching file`, item, error)
          })
      }
    }
  }
}
</script>

<template>
  <v-container>
    <v-sheet class="scroll">
      <v-expansion-panels v-model="panel" elevation="0" multiple>
        <v-expansion-panel v-if="file.bypages?.length && user.can('page:view')">
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
                <tr v-for="v in file.bypages" :key="v.id" @click="openPage(v)">
                  <td>{{ v.id }}</td>
                  <td>{{ '/' + v.path }}</td>
                  <td>{{ v.name }}</td>
                </tr>
              </tbody>
            </v-table>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel v-if="file.byelements?.length && user.can('element:view')">
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
                <tr v-for="v in file.byelements" :key="v.id" @click="openElement(v)">
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
            <v-table density="comfortable" hover>
              <thead>
                <tr>
                  <th>{{ $gettext('ID') }}</th>
                  <th>{{ $gettext('Type') }}</th>
                  <th>{{ $gettext('Published') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="v in versions" :key="v.id">
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
  max-height: calc(100vh - 96px);
}

.v-expansion-panel-title {
  font-weight: bold;
  font-size: 110%;
}

.v-table.pages tbody tr,
.v-table.elements tbody tr {
  cursor: pointer;
}

thead th {
  font-weight: bold !important;
  width: 33%;
}
</style>
