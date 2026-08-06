/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import { mdiAccountPlus, mdiDatabaseArrowDown, mdiMagnify } from '@mdi/js'
import { apolloClient } from '../graphql'
import { useMessageStore, useUserStore } from '../stores'

const USER_DATA = gql`
  fragment CmsUserDataFields on CmsUserData {
    id
    email
    access @include(if: $withAccess)
    permissions @include(if: $withPermissions)
  }
`

const FETCH_USER = gql`
  ${USER_DATA}
  query ($email: String!, $withAccess: Boolean!, $withPermissions: Boolean!) {
    cmsUser(email: $email) {
      ...CmsUserDataFields
    }
  }
`

const FETCH_PERMISSIONS = gql`
  query {
    permissions {
      roles
      permissions
    }
  }
`

const SET_USER_ACCESS = gql`
  mutation ($id: ID!, $access: [String!]!) {
    assignments: setUserAccess(id: $id, access: $access)
  }
`

const SET_USER_PERMISSIONS = gql`
  mutation ($id: ID!, $permissions: [String!]!) {
    assignments: setUserPermissions(id: $id, permissions: $permissions)
  }
`

const CREATE_USER = gql`
  ${USER_DATA}
  mutation ($email: String!, $withAccess: Boolean!, $withPermissions: Boolean!) {
    createUser(email: $email) {
      ...CmsUserDataFields
    }
  }
`

export default {
  name: 'AccessUsers',

  props: {
    roles: { type: Array, default: () => [] },
    rolesLoading: { type: Boolean, default: false }
  },

  setup() {
    const messages = useMessageStore()
    const user = useUserStore()

    return {
      messages,
      user,
      mdiAccountPlus,
      mdiDatabaseArrowDown,
      mdiMagnify
    }
  },

  data() {
    return {
      creating: false,
      email: '',
      loadingPermissions: false,
      loadingUser: false,
      customRoleValue: '__cms_role_custom__',
      backendRoleDraft: [],
      frontendRoleDraft: [],
      permissionOptions: { roles: [], permissions: [] },
      result: undefined,
      savingAccess: false,
      savingPermissions: false
    }
  },

  computed: {
    accessItems() {
      return [...new Set([...(this.result?.access || []), ...this.roles])].sort()
    },

    canAccess() {
      return this.user.can('user:access')
    },

    canCreate() {
      return this.user.can('user:create')
    },

    canManage() {
      return this.canAccess || this.canPermission
    },

    canPermission() {
      return this.user.can('user:permission')
    },

    emailValid() {
      return (
        this.searchEmail.length <= 255 && /^[^\s@]+@[^\s@]+$/.test(this.searchEmail)
      )
    },

    assignedPermissionRoles() {
      return [...new Set((this.result?.permissions || []).filter((entry) => this.isRole(entry)))]
    },

    isBackendPermissionCustom() {
      if (!this.result?.permissions?.length) return false

      const known = new Set(this.permissionOptions.roles || [])

      return (this.result.permissions || []).some((entry) => {
        if (!this.isRole(entry)) {
          return true
        }

        return !known.has(entry)
      })
    },

    permissionRoleItems() {
      const options = [...this.permissionOptions.roles].sort()
      const items = [...new Set([...options, ...this.assignedPermissionRoles])]

      if (this.isBackendPermissionCustom) {
        items.push(this.customRoleValue)
      }

      return items
    },

    permissionRole() {
      if (this.isBackendPermissionCustom) {
        return [this.customRoleValue]
      }

      return this.assignedPermissionRoles
    },

    searchEmail() {
      return this.email?.trim().toLocaleLowerCase() || ''
    },

    hasFrontendRoleChanges() {
      return !this.selectionEqual(this.frontendRoleDraft, this.result?.access || [])
    },

    hasBackendRoleChanges() {
      return !this.selectionEqual(this.backendRoleDraft, this.permissionRole)
    },

    isCurrentUser() {
      const userEmail = (this.user.me?.email || '').trim().toLocaleLowerCase()
      const resultEmail = (this.result?.email || '').trim().toLocaleLowerCase()

      return !!userEmail && !!resultEmail && userEmail === resultEmail
    },

    canSaveRoleChanges() {
      return (this.canAccess && this.hasFrontendRoleChanges) || (this.canPermission && this.hasBackendRoleChanges)
    },

    savingRoleChanges() {
      return this.savingAccess || this.savingPermissions
    }
  },

  mounted() {
    if (this.canPermission) this.loadPermissions()
  },

  methods: {
    async change(field, values, mutation) {
      const saving = field === 'access' ? 'savingAccess' : 'savingPermissions'
      if (!this.result || this[saving]) return

      const email = this.result.email
      const id = this.result.id
      const current = new Set(this.result[field] || [])
      const assignments = [...new Set(Array.isArray(values) ? values : [])]

      if (
        assignments.length === current.size &&
        assignments.every((value) => current.has(value))
      ) {
        return
      }

      this[saving] = true

      try {
        const response = await apolloClient.mutate({
          mutation,
          variables: { id, [field]: assignments }
        })

        if (email === this.searchEmail) {
          this.result = { ...this.result, [field]: response.data.assignments }

          if (field === 'access') {
            this.messages.add(this.$gettext('Access roles updated'), 'success')
            this.syncFrontendRoleDraft()
          } else {
            this.syncBackendRoleDraft()
          }
        }
      } catch (error) {
        if (email === this.searchEmail) {
          const message =
            field === 'access'
              ? this.$gettext('Error updating access roles')
              : this.$gettext('Error updating CMS permissions')

          this.messages.add(message + ':\n' + error, 'error')
        }
      } finally {
        this[saving] = false
      }
    },

    syncBackendRoleDraft() {
      this.backendRoleDraft = this.permissionRole
    },

    syncFrontendRoleDraft() {
      this.frontendRoleDraft = [...new Set(this.result?.access || [])]
    },

    syncRoleDrafts() {
      this.syncFrontendRoleDraft()
      this.syncBackendRoleDraft()
    },

    selectionEqual(a, b) {
      const left = [...new Set((a || []).filter((v) => v !== undefined))].sort()
      const right = [...new Set((b || []).filter((v) => v !== undefined))].sort()

      return left.length === right.length && left.every((value, index) => value === right[index])
    },

    changeAccess(values) {
      return this.change('access', values, SET_USER_ACCESS)
    },

    changePermissions(values) {
      return this.change('permissions', values, SET_USER_PERMISSIONS)
    },

    async applyRoleChanges() {
      const promises = []

      if (this.canAccess && this.hasFrontendRoleChanges) {
        promises.push(this.changeAccess(this.frontendRoleDraft))
      }

      if (this.canPermission && this.hasBackendRoleChanges) {
        promises.push(this.changePermissionRole(this.backendRoleDraft))
      }

      if (!promises.length) {
        return
      }

      await Promise.all(promises)
    },

    changePermissionRole(roles) {
      if (!this.result || this.savingPermissions) return

      const remaining = (this.result.permissions || []).filter((entry) =>
        !this.isRole(entry) || !this.permissionOptions.roles.includes(entry)
      )
      const selected = (roles || []).filter((value) => value !== this.customRoleValue)
      const assignments = new Set([...remaining, ...selected])

      this.changePermissions([...assignments])
    },

    changePermissionRoleDraft(roles) {
      this.backendRoleDraft = [...new Set(Array.isArray(roles) ? roles : [])]
    },

    changeAccessDraft(values) {
      this.frontendRoleDraft = [...new Set(Array.isArray(values) ? values : [])]
    },

    isRole(entry) {
      return typeof entry === 'string' && !entry.startsWith('!') && !entry.includes(':')
    },

    async createUser() {
      const email = this.searchEmail
      if (!this.canCreate || !this.emailValid || this.creating || this.loadingUser) return

      this.creating = true

      try {
        const response = await apolloClient.mutate({
          mutation: CREATE_USER,
          variables: {
            email,
            withAccess: this.canAccess,
            withPermissions: this.canPermission
          }
        })

        if (email === this.searchEmail) {
          this.messages.add(this.$gettext('User created'), 'success')
          this.result = response.data.createUser
          this.syncRoleDrafts()
        }
      } catch (error) {
        if (email === this.searchEmail) {
          this.messages.add(this.$gettext('Error creating user') + ':\n' + error, 'error')
        }
      } finally {
        this.creating = false
      }
    },

    emailChanged() {
      this.result = undefined
    },

    async loadPermissions() {
      if (this.loadingPermissions) return

      this.loadingPermissions = true

      try {
        const response = await apolloClient.query({
          query: FETCH_PERMISSIONS,
          fetchPolicy: 'network-only'
        })

        this.permissionOptions = response.data.permissions
        this.syncBackendRoleDraft()
      } catch (error) {
        this.messages.add(this.$gettext('Error fetching access roles') + ':\n' + error, 'error')
      } finally {
        this.loadingPermissions = false
      }
    },

    async search() {
      const email = this.searchEmail
      if (!this.canManage || !this.emailValid || this.loadingUser || this.creating) return

      this.loadingUser = true
      this.result = undefined

      try {
        const response = await apolloClient.query({
          query: FETCH_USER,
          variables: {
            email,
            withAccess: this.canAccess,
            withPermissions: this.canPermission
          },
          fetchPolicy: 'no-cache'
        })

        if (email === this.searchEmail) {
          this.result = response.data.cmsUser
          this.syncRoleDrafts()
        }
      } catch (error) {
        if (email === this.searchEmail) {
          this.messages.add(this.$gettext('Error fetching user access roles') + ':\n' + error, 'error')
        }
      } finally {
        this.loadingUser = false
      }
    },

    clearEmail() {
      this.email = ''
      this.emailChanged()
    },

  }
}
</script>

<template>
  <div class="access-users">
    <div class="header">
      <div class="bulk">
        <v-btn
          type="button"
          class="btn-create"
          :icon="mdiAccountPlus"
          color="primary"
          variant="tonal"
          :disabled="!canCreate || creating || loadingUser || !emailValid || !!result"
          :loading="creating"
          @click="createUser"
          :title="$gettext('Create user')"
          :aria-label="$gettext('Create user')"
        />
      </div>

      <div class="search user-search">
        <v-text-field
          v-model="email"
          :label="$gettext('Email address')"
          variant="underlined"
          :maxlength="255"
          hide-details
          :disabled="loadingUser || creating"
          clearable
          @update:model-value="emailChanged"
          @keydown.enter.prevent="search()"
          type="email"
          :aria-label="$gettext('Search user by email')"
        />

        <v-btn
          type="button"
          class="btn-search"
          :icon="mdiMagnify"
          color="primary"
          variant="tonal"
          size="small"
          :disabled="!canManage || loadingUser || creating || !emailValid"
          :loading="loadingUser"
          :title="$gettext('Search')"
          :aria-label="$gettext('Search')"
          @click="search()"
        />
      </div>

      <div class="layout">
        <v-btn
          v-if="canAccess || canPermission"
          class="btn-save"
          color="primary"
          variant="tonal"
          :icon="mdiDatabaseArrowDown"
          :disabled="!result || !canSaveRoleChanges || savingRoleChanges || rolesLoading || loadingPermissions || isCurrentUser"
          :loading="savingRoleChanges"
          @click="applyRoleChanges"
          :title="$gettext('Save')"
          :aria-label="$gettext('Save')"
        />
      </div>
    </div>

    <v-progress-linear v-if="loadingUser" indeterminate color="primary" />

    <div v-else-if="result" class="user-table">
      <section class="assignment">
        <h3 class="assignment-title">{{ $gettext('Account') }}</h3>
        <p class="found-email">{{ result.email }}</p>
      </section>

      <section v-if="canAccess" class="assignment">
        <h3 class="assignment-title">{{ $gettext('Assigned frontend roles') }}</h3>
        <v-autocomplete
          class="assigned assigned-access"
          :model-value="frontendRoleDraft"
          :items="accessItems"
          :loading="rolesLoading || savingAccess"
          :disabled="savingAccess"
          :label="$gettext('Assigned frontend roles')"
          variant="underlined"
          multiple
          chips
          closable-chips
          clearable
          hide-selected
          hide-details
          @update:model-value="changeAccessDraft"
        />
      </section>

      <section v-if="canPermission" class="assignment assigned-permissions">
        <h3 class="assignment-title">{{ $gettext('Assigned backend roles') }}</h3>

        <v-autocomplete
          class="assigned"
          :item-title="value => value === customRoleValue ? $gettext('Custom') : value"
          :item-value="(value) => value"
          :model-value="backendRoleDraft"
          :items="permissionRoleItems"
          :loading="savingPermissions"
          :disabled="savingPermissions || isCurrentUser"
          :label="$gettext('Available roles')"
          variant="underlined"
          multiple
          chips
          closable-chips
          clearable
          hide-details
          @update:model-value="changePermissionRoleDraft"
        />
      </section>
    </div>

    <p v-else-if="result === null" class="notfound">{{ $gettext('No entries found') }}</p>

  </div>
</template>

<style scoped>
.user-table {
  padding: 0 16px 16px;
}

.search.user-search :deep(.v-input) {
  min-width: 7.5rem;
  margin: 0 4px;
  flex: 1 1 auto;
  width: auto;
}

.search.user-search {
  align-items: center;
  gap: 4px;
}

.access-users .header {
  align-items: center;
}

.found-email {
  font-family: monospace;
  font-size: 18px;
  margin: 0;
  word-break: break-word;
}

.assignment {
  margin-bottom: 24px;
}

.assignment-title {
  margin: 0 0 8px;
}

.assigned :deep(.v-chip),
.found-email {
  font-family: monospace;
}

:global(.assigned-permissions input[role='combobox']) {
  pointer-events: auto !important;
}

.assigned {
  min-width: 20rem;
}

.notfound {
  margin: 0;
  padding: 16px;
}
</style>
