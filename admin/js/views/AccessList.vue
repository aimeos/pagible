/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import {
  mdiAlertCircleOutline,
  mdiClose,
  mdiDelete,
  mdiKeyPlus,
  mdiMagnify,
  mdiMenu
} from '@mdi/js'
import Navigation from '../components/Navigation.vue'
import User from '../components/User.vue'
import AccessUsers from '../components/AccessUsers.vue'
import { apolloClient } from '../graphql'
import { useDrawerStore, useMessageStore, useUserStore } from '../stores'

const FETCH_ACCESS = gql`
  query {
    access
  }
`

const ADD_ACCESS = gql`
  mutation ($value: String!) {
    addAccess(value: $value)
  }
`

const DELETE_ACCESS = gql`
  mutation ($values: [String!]!) {
    deleteAccess(values: $values)
  }
`

export default {
  name: 'AccessList',

  components: {
    AccessUsers,
    Navigation,
    User
  },

  setup() {
    const drawer = useDrawerStore()
    const messages = useMessageStore()
    const user = useUserStore()

    return {
      drawer,
      messages,
      user,
      mdiAlertCircleOutline,
      mdiClose,
      mdiDelete,
      mdiKeyPlus,
      mdiMagnify,
      mdiMenu
    }
  },

  data() {
    return {
      items: [],
      checked: new Set(),
      term: '',
      value: '',
      loading: true,
      saving: false,
      addDialog: false,
      deleteDialog: false
    }
  },

  computed: {
    tabNames() {
      return this.canAccess && this.canManageUsers ? ['roles', 'users'] : []
    },

    activeTab: {
      get() {
        return this.$route.name === 'access:users' || this.$route.query?.tab === 'users'
          ? 'users'
          : 'roles'
      },
      set(value) {
        const query = value === 'users' ? { ...this.$route.query, tab: 'users' } : { ...this.$route.query }
        if (value !== 'users') {
          delete query.tab
        }

        if (this.$route.query?.tab === query.tab && this.$route.name !== 'access:users') return

        this.$router.replace({ name: this.$route.name, query })
      }
    },

    filtered() {
      const term = this.term?.trim().toLocaleLowerCase()

      return term
        ? this.items.filter((value) => value.toLocaleLowerCase().includes(term))
        : this.items
    },

    addDisabled() {
      const value = this.value.trim()
      return this.saving || !value || value.length > 100 || this.items.includes(value)
    },

    allSelected() {
      return this.filtered.length > 0 && this.filtered.every((value) => this.checked.has(value))
    },

    canAccess() {
      return this.user.can('access:view')
    },

    canManageUsers() {
      return this.user.can(['user:create', 'user:access', 'user:permission'])
    }
  },

  mounted() {
    if (this.$route.name === 'access:view' && !this.canAccess && this.canManageUsers) {
      return this.$router.replace({ name: 'access:view', query: { ...this.$route.query, tab: 'users' } })
    }

    this.load()
  },

  methods: {
    async load() {
      if (!this.canAccess) {
        this.loading = false
        return
      }

      try {
        const response = await apolloClient.query({
          query: FETCH_ACCESS,
          fetchPolicy: 'network-only'
        })

        this.items = response.data.access
      } catch (error) {
        this.messages.add(this.$gettext('Error fetching access roles') + ':\n' + error, 'error')
      } finally {
        this.loading = false
      }
    },

    openAdd() {
      this.value = ''
      this.addDialog = true

      this.$nextTick(() => this.$refs.value?.focus())
    },

    async add() {
      if (this.addDisabled) return

      this.saving = true

      try {
        const response = await apolloClient.mutate({
          mutation: ADD_ACCESS,
          variables: { value: this.value.trim() }
        })

        this.items = response.data.addAccess
        this.addDialog = false
        this.value = ''
      } catch (error) {
        this.messages.add(this.$gettext('Error adding access value') + ':\n' + error, 'error')
      } finally {
        this.saving = false
      }
    },

    async remove() {
      const values = Array.from(this.checked)
      if (!values.length || this.saving) return

      this.saving = true

      try {
        const response = await apolloClient.mutate({
          mutation: DELETE_ACCESS,
          variables: { values }
        })

        this.items = response.data.deleteAccess
        this.checked = new Set()
        this.deleteDialog = false
      } catch (error) {
        this.messages.add(this.$gettext('Error deleting access values') + ':\n' + error, 'error')
      } finally {
        this.saving = false
      }
    },

    toggle(value) {
      const checked = new Set(this.checked)
      checked.has(value) ? checked.delete(value) : checked.add(value)
      this.checked = checked
    },

    toggleAll() {
      const checked = new Set(this.checked)

      if (this.allSelected) {
        this.filtered.forEach((value) => checked.delete(value))
      } else {
        this.filtered.forEach((value) => checked.add(value))
      }

      this.checked = checked
    }
  }
}
</script>

<template>
  <v-app-bar :elevation="0" density="compact" role="sectionheader" :aria-label="$gettext('Menu')">
    <template #prepend>
      <v-btn
        @click="drawer.toggle('nav')"
        :title="drawer.nav ? $gettext('Close navigation') : $gettext('Open navigation')"
        :icon="drawer.nav ? mdiClose : mdiMenu"
      />
    </template>

    <v-app-bar-title><h1>{{ $gettext('Access') }}</h1></v-app-bar-title>

    <template #append>
      <User />
    </template>
  </v-app-bar>

  <Navigation />

  <v-main class="access-list" :aria-label="$gettext('Access')">
    <v-container>
      <v-sheet class="box scroll">
        <v-tabs v-if="tabNames.length" fixed-tabs v-model="activeTab" class="subtabs">
          <v-tab value="roles">{{ $gettext('Roles') }}</v-tab>
          <v-tab value="users">{{ $gettext('Users') }}</v-tab>
        </v-tabs>

        <v-window v-model="activeTab" :touch="false" :disabled="!tabNames.length">
          <v-window-item v-if="canAccess" value="roles">
            <div class="access-roles">
              <div class="header">
                <div class="bulk">
                  <v-checkbox-btn
                    :style="{ visibility: user.can('access:delete') ? undefined : 'hidden' }"
                    :model-value="allSelected"
                    :disabled="loading || !filtered.length"
                    @click.stop="toggleAll()"
                    :aria-label="$gettext('Toggle selection')"
                  />
                  <v-btn
                    v-if="checked.size"
                    @click="deleteDialog = true"
                    :title="$gettext('Delete')"
                    :icon="mdiDelete"
                    color="error"
                    variant="text"
                    class="btn-delete"
                  />

                  <v-btn
                    v-if="user.can('access:add')"
                    @click="openAdd()"
                    :title="$gettext('Add access value')"
                    :disabled="loading"
                    :icon="mdiKeyPlus"
                    color="primary"
                    variant="tonal"
                    class="btn-add"
                  />
                </div>

                <div class="search">
                  <v-text-field
                    v-model="term"
                    :prepend-inner-icon="mdiMagnify"
                    variant="underlined"
                    :label="$gettext('Search for')"
                    hide-details
                    clearable
                  />
                </div>
              </div>

              <v-progress-linear v-if="loading" indeterminate color="primary" />

              <v-list v-else-if="filtered.length" class="items">
                <v-list-item v-for="item in filtered" :key="item" :value="item">
                  <template v-if="user.can('access:delete')" #prepend>
                    <v-checkbox-btn
                      :model-value="checked.has(item)"
                      @click.stop="toggle(item)"
                      :aria-label="$gettext('Toggle selection')"
                    />
                  </template>
                  <v-list-item-title class="item-title">{{ item }}</v-list-item-title>
                </v-list-item>
              </v-list>

              <p v-else class="notfound">{{ $gettext('No entries found') }}</p>
            </div>
          </v-window-item>

          <v-window-item v-if="canManageUsers" value="users">
            <AccessUsers
              :roles="items"
              :roles-loading="loading"
            />
          </v-window-item>
        </v-window>
      </v-sheet>
    </v-container>
  </v-main>

  <Teleport to="body">
    <v-dialog v-model="addDialog" max-width="480" :aria-label="$gettext('Add access value')">
      <v-card>
        <v-toolbar density="compact">
          <v-toolbar-title>{{ $gettext('Add access value') }}</v-toolbar-title>
          <v-btn :icon="mdiClose" :aria-label="$gettext('Close')" @click="addDialog = false" />
        </v-toolbar>
        <v-card-text>
          <v-text-field
            ref="value"
            v-model="value"
            :label="$gettext('Access value')"
            maxlength="100"
            counter
            autofocus
            @keyup.enter="add()"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="addDialog = false" variant="text">{{ $gettext('Cancel') }}</v-btn>
          <v-btn @click="add()" :disabled="addDisabled" :loading="saving" color="primary" variant="flat">
            {{ $gettext('Add') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog
      v-model="deleteDialog"
      max-width="520"
      role="alertdialog"
      :aria-label="$gettext('Delete access values')"
    >
      <v-card>
        <v-toolbar density="compact" color="warning">
          <v-toolbar-title>{{ $gettext('Delete access values') }}</v-toolbar-title>
          <v-btn :icon="mdiClose" :aria-label="$gettext('Close')" @click="deleteDialog = false" />
        </v-toolbar>
        <v-card-text class="warning">
          <v-icon :icon="mdiAlertCircleOutline" color="warning" size="40" />
          <p>
            {{
              $gettext(
                'Existing restrictions are not changed and will continue to reference the deleted access values.'
              )
            }}
          </p>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="deleteDialog = false" variant="text">{{ $gettext('Cancel') }}</v-btn>
          <v-btn @click="remove()" :loading="saving" color="error" variant="flat">
            {{ $gettext('Delete') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </Teleport>
</template>

<style scoped>
.v-main {
  overflow-y: auto;
}

.items {
  margin: 0;
  padding: 0;
}

.items .v-list-item {
  padding: 4px 0;
}

.item-title {
  font-family: monospace;
}

.notfound {
  padding: 32px;
  text-align: center;
}

.warning {
  align-items: center;
  display: flex;
  gap: 16px;
}

.warning p {
  margin: 0;
}

.subtabs {
  margin-bottom: 16px;
}
</style>
