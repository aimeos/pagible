/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import { mdiLock } from '@mdi/js'
import { useUserStore, useViewStack } from '../stores'

const FETCH_ELEMENT_REFS = gql`
  query ($id: ID!) {
    element(id: $id) {
      id
      bypages {
        id
        path
        name
        restricted
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
    element: {}
  }),

  setup() {
    const viewStack = useViewStack()
    const user = useUserStore()

    return { mdiLock, user, viewStack }
  },

  beforeUnmount() {
    this.versions = null
    this.element = null
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

    async openElement(item) {
      const { default: ElementDetail } = await import('../views/ElementDetail.vue')
      this.viewStack.openView(ElementDetail, { item: { ...item }, stacked: true })
    },

    async openFile(item) {
      const { default: FileDetail } = await import('../views/FileDetail.vue')
      this.viewStack.openView(FileDetail, { item: { ...item }, stacked: true })
    },

    async openPage(item) {
      const { default: PageDetail } = await import('../views/PageDetail.vue')
      this.viewStack.openView(PageDetail, { item: { ...item }, stacked: true })
    },

    openVersion(item) {
      const owner = { id: item.id }

      if (item.type === 'Element') return this.openElement(owner)
      if (item.type === 'File') return this.openFile(owner)
      if (item.type === 'Page') return this.openPage(owner)
    }
  },

  watch: {
    item: {
      immediate: true,
      handler(item) {
        if (!item.id || !this.user.can('element:view')) {
          return
        }

        this.$apollo
          .query({
            query: FETCH_ELEMENT_REFS,
            fetchPolicy: 'no-cache',
            variables: {
              id: item.id
            }
          })
          .then((result) => {
            if (result.errors) {
              throw result.errors
            }

            const element = result.data?.element || {}
            this.element = Object.freeze({
              ...element,
              bypages: Object.freeze((element.bypages || []).map(p => Object.freeze(p)))
            })
            this.versions = Object.freeze((result.data?.element?.byversions || [])
              .map((item) => Object.freeze(this.mapVersion(item)))
              .filter((item) => {
                return this.user.can(item.type.toLowerCase() + ':view')
              })
            )
          })
          .catch((error) => {
            this.$log(`ElementDetailRef::watch(item): Error fetching element`, item, error)
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
        <v-expansion-panel v-if="element.bypages?.length && user.can('page:view')">
          <v-expansion-panel-title>{{ $gettext('Shared elements') }}</v-expansion-panel-title>
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
                <tr v-for="v in element.bypages" :key="v.id" @click="openPage(v)">
                  <td>{{ v.id }}</td>
                  <td>{{ v.path }}</td>
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
                <tr v-for="v in versions" :key="v.key" @click="openVersion(v)">
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
.v-expansion-panel-title {
  font-weight: bold;
  font-size: 110%;
}

.v-table.pages tbody tr,
.v-table.versions tbody tr {
  cursor: pointer;
}

thead th {
  font-weight: bold !important;
  width: 33%;
}

.v-sheet.scroll {
  height: calc(100vh - 96px);
}
</style>
