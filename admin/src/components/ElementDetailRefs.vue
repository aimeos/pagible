<script>
  import gql from 'graphql-tag'
  import { useAuthStore } from '../stores'


  export default {
    props: {
      'item': {type: Object, required: true}
    },

    emits: [],

    data: () => ({
      panel: [0, 1, 2],
      versions: {},
      element: {}
    }),

    setup() {
      const auth = useAuthStore()

      return { auth }
    },

    watch: {
      item: {
        immediate: true,
        handler(item) {
          if(!item.id || !this.auth.can('element:view')) {
            return
          }

          this.$apollo.query({
            query: gql`query ($id: ID!) {
              element(id: $id) {
                id
                bypages {
                  id
                  path
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
            }`,
            variables: {
              id: item.id,
            }
          }).then(result => {
            if(result.errors) {
              throw result.errors
            }

            this.element = result.data?.element || {}
            this.versions = (result.data?.element?.byversions || []).map(item => {
              return {
                id: item.versionable_id,
                type: item.versionable_type.split('\\').at(-1),
                published: item.published
                  ? this.$gettext('yes')
                  : (item.publish_at
                    ? (new Date(item.publish_at)).toLocaleDateString()
                    : this.$gettext('no')
                  ),
              }
            }).filter(item => {
              return this.auth.can(item.type.toLowerCase() + ':view')
            })
          }).catch(error => {
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

        <v-expansion-panel v-if="element.bypages?.length && auth.can('page:view')">
          <v-expansion-panel-title>{{ $gettext('Shared elements') }}</v-expansion-panel-title>
          <v-expansion-panel-text>
            <v-table density="comfortable" hover>
              <thead>
                <tr>
                  <th>{{ $gettext('ID') }}</th>
                  <th>{{ $gettext('URL') }}</th>
                  <th>{{ $gettext('Name') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="v in element.bypages" :key="v.id">
                  <td>{{ v.id }}</td>
                  <td>{{ v.path }}</td>
                  <td>{{ v.name }}</td>
                </tr>
              </tbody>
            </v-table>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel v-if="versions?.length">
          <v-expansion-panel-title>Versions</v-expansion-panel-title>
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
  .v-expansion-panel-title {
    font-weight: bold;
    font-size: 110%;
  }

  thead th {
    font-weight: bold !important;
    width: 33%
  }
</style>