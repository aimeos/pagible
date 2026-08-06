/**
 * E2E tests for the access catalog at /access.
 */

const ALL_PERMISSIONS = {
  'access:view': true,
  'access:add': true,
  'access:delete': true,
  'user:access': true,
  'user:create': true,
  'user:permission': true,
  'page:view': true,
  'element:view': true,
  'file:view': true
}

function setupIntercept({
  permissions = ALL_PERMISSIONS,
  values = ['alpha', 'member'],
  userDelay = 0,
  setDelay = 0,
  createDelay = 0
} = {}) {
  const users = new Map([
    ['member@example.com', { id: 'user-1', access: ['member'], permissions: ['viewer'] }]
  ])
  let nextId = 2
  const requests = { access: 0, cmsUser: 0 }

  cy.intercept('POST', '/graphql', (req) => {
    const batched = Array.isArray(req.body)
    const operations = batched ? req.body : [req.body]
    const queries = operations.map((operation) => operation.query || '')

    if (queries.some((query) => query.includes('createUser('))) req.alias = 'createUser'
    else if (queries.some((query) => query.includes('setUserPermissions'))) {
      req.alias = 'setUserPermissions'
    } else if (queries.some((query) => query.includes('setUserAccess'))) req.alias = 'setUserAccess'
    else if (queries.some((query) => query.includes('addAccess'))) req.alias = 'addAccess'
    else if (queries.some((query) => query.includes('deleteAccess'))) req.alias = 'deleteAccess'
    else if (queries.some((query) => query.includes('cmsUser(email:'))) {
      req.alias = 'cmsUser'
    } else if (queries.some((query) => query.includes('permissions {'))) {
      req.alias = 'permissions'
    } else if (queries.some((query) => query.match(/\baccess\b/))) {
      req.alias = 'initialAccess'
    } else req.alias = null

    const responses = operations.map((operation) => {
      const query = operation.query || ''

      if (query.includes('addAccess')) {
        return { data: { addAccess: [...values, operation.variables.value].sort() } }
      }

      if (query.includes('deleteAccess')) {
        const deleted = operation.variables.values
        return { data: { deleteAccess: values.filter((value) => !deleted.includes(value)) } }
      }

      if (query.includes('setUserAccess')) {
        const assigned = [...new Set(operation.variables.access)].sort()
        const user = [...users.values()].find(({ id }) => id === operation.variables.id)
        user.access = assigned
        return { data: { assignments: assigned } }
      }

      if (query.includes('setUserPermissions')) {
        const assigned = [...new Set(operation.variables.permissions)].sort()
        const user = [...users.values()].find(({ id }) => id === operation.variables.id)
        user.permissions = assigned
        return { data: { assignments: assigned } }
      }

      if (query.includes('createUser(')) {
        const user = { id: `user-${nextId++}`, access: [], permissions: [] }
        users.set(operation.variables.email, user)
        const data = { id: user.id, email: operation.variables.email }

        if (operation.variables.withAccess) data.access = []
        if (operation.variables.withPermissions) data.permissions = []

        return { data: { createUser: data } }
      }

      if (query.includes('cmsUser(email:')) {
        requests.cmsUser++
        const user = users.get(operation.variables.email)
        const data = user ? { id: user.id, email: operation.variables.email } : null

        if (data && operation.variables.withAccess) data.access = user.access
        if (data && operation.variables.withPermissions) data.permissions = user.permissions

        return { data: { cmsUser: data } }
      }

      if (query.includes('permissions {')) {
        return {
          data: {
            permissions: {
              roles: ['admin', 'editor', 'viewer'],
              permissions: ['page:save', 'page:view', 'user:access', 'user:permission']
            }
          }
        }
      }

      if (/\baccess\s*\n?\s*}/.test(query)) {
        requests.access++
        return { data: { access: values } }
      }

      return {
        data: {
          me: {
            permission: JSON.stringify(permissions),
            settings: '{}',
            email: 'admin@example.com',
            name: 'Admin',
            token: ''
          }
        }
      }
    })

    const body = batched ? responses : responses[0]
    const delay = queries.some((query) => query.includes('createUser('))
      ? createDelay
      : queries.some(
            (query) => query.includes('setUserAccess') || query.includes('setUserPermissions')
          )
        ? setDelay
        : queries.some((query) => query.includes('cmsUser(email:'))
          ? userDelay
          : 0

    delay ? req.reply({ delay, body }) : req.reply(body)
  })

  return requests
}

function visitAccess(options) {
  const requests = setupIntercept(options)
  cy.visit('/access')
  cy.wait('@initialAccess')
  cy.get('.access-roles', { timeout: 20000 }).should('exist')
  return requests
}

describe('Access list', () => {
  it('lists and filters access values', () => {
    visitAccess()

    cy.get('.v-app-bar-title').should('contain', 'Access')
    cy.contains('.v-tab', 'Roles').should('have.class', 'v-tab--selected')
    cy.contains('.v-tab', 'Users').should('exist')
    cy.get('.item-title').should('have.length', 2)
    cy.get('.search input').type('mem')
    cy.get('.item-title').should('have.length', 1).and('contain', 'member')
  })

  it('adds an access value and exposes it to assignments', () => {
    visitAccess()

    cy.get('.btn-add').click()
    cy.get('.v-dialog input').type('editor')
    cy.get('.v-dialog').contains('button', 'Add').click()
    cy.wait('@addAccess')

    // the added role shows in the roles list
    cy.get('.item-title').should('have.length', 3).and('contain', 'editor')

    // and merges into the users-tab assigned roles options
    cy.contains('.v-tab', 'Users').click()
    cy.get('.user-search input').type('member@example.com')
    cy.get('.user-search').submit()
    cy.wait('@cmsUser')
    cy.get('.assigned-access').should('exist').and('have.class', 'v-autocomplete')
  })

  it('deletes selected access values after confirmation', () => {
    visitAccess()

    cy.get('.items .v-checkbox-btn').first().click()
    cy.get('.btn-delete').click()
    cy.get('.v-dialog').contains('button', 'Delete').click()

    cy.wait('@deleteAccess').then(({ request }) => {
      const operation = Array.isArray(request.body) ? request.body[0] : request.body
      expect(operation.variables.values).to.deep.equal(['alpha'])
    })
    cy.get('.item-title').should('have.length', 1).and('contain', 'member')
  })

  it('hides the Users tab without a user capability', () => {
    visitAccess({ permissions: { 'access:view': true } })

    cy.contains('.v-tab', 'Users').should('not.exist')
  })

  it('hides the add control without the access:add permission', () => {
    visitAccess({ permissions: { 'access:view': true, 'access:delete': true } })

    cy.get('.btn-add').should('not.exist')
    cy.get('.bulk .v-checkbox-btn').should('be.visible')
    cy.get('.items .v-checkbox-btn').should('have.length', 2)
  })

  it('hides the delete controls without the access:delete permission', () => {
    visitAccess({ permissions: { 'access:view': true, 'access:add': true } })

    cy.get('.btn-add').should('exist')
    cy.get('.btn-delete').should('not.exist')
    cy.get('.bulk .v-checkbox-btn').should('not.be.visible')
    cy.get('.items .v-checkbox-btn').should('not.exist')
  })

  it('shows user management directly without the access:view permission', () => {
    const requests = setupIntercept({ permissions: { 'user:access': true } })
    cy.visit('/access')

    cy.get('.v-tab').should('not.exist')
    cy.get('.access-users').should('exist')
    cy.get('.user-search').should('exist')
    cy.then(() => expect(requests.access).to.equal(0))
  })

  it('loads and updates both assignment types with one cmsUser query', () => {
    const requests = visitAccess()
    cy.contains('.v-tab', 'Users').click()
    cy.wait('@permissions')

    cy.get('.user-search input').type('member@example.com')
    cy.get('.user-search').submit()
    cy.wait('@cmsUser').then(({ request }) => {
      const operation = Array.isArray(request.body) ? request.body[0] : request.body
      expect(operation.variables).to.deep.equal({
        email: 'member@example.com',
        withAccess: true,
        withPermissions: true
      })
    })

    cy.get('.user-table').should('contain', 'member@example.com')
    cy.get('.assigned-access').should('contain', 'member')
    cy.get('.assigned-permissions').should('contain', 'viewer')

    cy.get('.assigned-access input').click()
    cy.contains('.v-overlay--active .v-list-item', 'alpha').click()
    cy.wait('@setUserAccess').then(({ request }) => {
      const operation = Array.isArray(request.body) ? request.body[0] : request.body
      expect(operation.variables.id).to.equal('user-1')
      expect(operation.variables).not.to.have.property('email')
      expect(operation.variables.access).to.have.members(['member', 'alpha'])
    })
    cy.get('.assigned-access input').type('{esc}')

    cy.get('.assigned-permissions input').click()
    cy.contains('.v-overlay--active .v-list-item', 'page:view').click()
    cy.wait('@setUserPermissions').then(({ request }) => {
      const operation = Array.isArray(request.body) ? request.body[0] : request.body
      expect(operation.variables.id).to.equal('user-1')
      expect(operation.variables).not.to.have.property('email')
      expect(operation.variables.permissions).to.have.members(['viewer', 'page:view'])
    })

    cy.then(() => expect(requests.cmsUser).to.equal(1))
  })

  it('requests only frontend access for an access manager', () => {
    visitAccess({ permissions: { 'access:view': true, 'user:access': true } })
    cy.contains('.v-tab', 'Users').click()

    cy.get('.user-search input').type('member@example.com')
    cy.get('.user-search').submit()
    cy.wait('@cmsUser').then(({ request }) => {
      const operation = Array.isArray(request.body) ? request.body[0] : request.body
      expect(operation.variables.withAccess).to.equal(true)
      expect(operation.variables.withPermissions).to.equal(false)
    })

    cy.get('.assigned-access').should('exist')
    cy.get('.assigned-permissions').should('not.exist')
  })

  it('requests only CMS permissions for a permission manager', () => {
    visitAccess({ permissions: { 'access:view': true, 'user:permission': true } })
    cy.contains('.v-tab', 'Users').click()
    cy.wait('@permissions')

    cy.get('.user-search input').type('member@example.com')
    cy.get('.user-search').submit()
    cy.wait('@cmsUser').then(({ request }) => {
      const operation = Array.isArray(request.body) ? request.body[0] : request.body
      expect(operation.variables.withAccess).to.equal(false)
      expect(operation.variables.withPermissions).to.equal(true)
    })

    cy.get('.assigned-access').should('not.exist')
    cy.get('.assigned-permissions').should('exist')
  })

  it('creates a user directly without a prerequisite lookup', () => {
    const requests = visitAccess()
    cy.contains('.v-tab', 'Users').click()

    cy.get('.user-search input').type('new@example.com')
    cy.then(() => expect(requests.cmsUser).to.equal(0))
    cy.get('.btn-create').click()

    cy.wait('@createUser').then(({ request }) => {
      const operation = Array.isArray(request.body) ? request.body[0] : request.body
      expect(operation.variables).to.deep.equal({
        email: 'new@example.com',
        withAccess: true,
        withPermissions: true
      })
    })
    cy.get('.user-table').should('contain', 'new@example.com')
    cy.get('.assigned-access .v-chip').should('have.length', 0)
    cy.get('.assigned-permissions .v-chip').should('have.length', 0)
    cy.then(() => expect(requests.cmsUser).to.equal(0))
  })

  it('supports user creation without lookup permissions', () => {
    const requests = visitAccess({
      permissions: { 'access:view': true, 'user:create': true }
    })
    cy.contains('.v-tab', 'Users').click()

    cy.get('.user-search button[type="submit"]').should('not.exist')
    cy.get('.user-search input').type('new@example.com')
    cy.get('.btn-create').click()
    cy.wait('@createUser').then(({ request }) => {
      const operation = Array.isArray(request.body) ? request.body[0] : request.body
      expect(operation.variables).to.deep.equal({
        email: 'new@example.com',
        withAccess: false,
        withPermissions: false
      })
    })
    cy.get('.user-table').should('contain', 'new@example.com')
    cy.then(() => expect(requests.cmsUser).to.equal(0))
  })

  it('handles clearing and stale user lookups', () => {
    const requests = visitAccess({ userDelay: 500 })
    cy.contains('.v-tab', 'Users').click()

    cy.get('.user-search input').type('member@example.com')
    cy.get('.user-search').submit()
    cy.get('.user-search input').clear().type('other@example.com')
    cy.wait('@cmsUser')

    cy.get('.user-search input').should('have.value', 'other@example.com')
    cy.get('.user-table').should('not.exist')
    cy.get('.user-search .v-field__clearable').click()
    cy.get('.user-search input').should('have.value', '')
    cy.then(() => expect(requests.cmsUser).to.equal(1))
  })

  it('discards stale assignment and creation responses', () => {
    visitAccess({ setDelay: 500, createDelay: 500 })
    cy.contains('.v-tab', 'Users').click()
    cy.wait('@permissions')

    cy.get('.user-search input').type('member@example.com')
    cy.get('.user-search').submit()
    cy.wait('@cmsUser')
    cy.get('.assigned-access input').click()
    cy.contains('.v-overlay--active .v-list-item', 'alpha').click()
    cy.get('.user-search input').clear().type('other@example.com')
    cy.wait('@setUserAccess')
    cy.get('.user-table').should('not.exist')

    cy.get('.user-search input').clear().type('member@example.com')
    cy.get('.user-search').submit()
    cy.wait('@cmsUser')
    cy.get('.assigned-permissions input').click()
    cy.contains('.v-overlay--active .v-list-item', 'page:view').click()
    cy.get('.user-search input').clear().type('other@example.com')
    cy.wait('@setUserPermissions')
    cy.get('.user-table').should('not.exist')

    cy.get('.user-search input').clear().type('new@example.com')
    cy.get('.btn-create').click()
    cy.get('.user-search input').clear().type('third@example.com')
    cy.wait('@createUser')
    cy.get('.user-search input').should('have.value', 'third@example.com')
    cy.get('.user-table').should('not.exist')
  })
})
