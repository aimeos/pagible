/**
 * E2E tests for the access catalog at /access.
 */

const ALL_PERMISSIONS = {
  'access:view': true,
  'access:add': true,
  'access:delete': true,
  'page:view': true,
  'element:view': true,
  'file:view': true,
}

function setupIntercept({ permissions = ALL_PERMISSIONS, values = ['alpha', 'member'] } = {}) {
  cy.intercept('POST', '/graphql', (req) => {
    const batched = Array.isArray(req.body)
    const operations = batched ? req.body : [req.body]

    const responses = operations.map((operation) => {
      const query = operation.query || ''

      if (query.includes('addAccess')) {
        return { data: { addAccess: [...values, operation.variables.value].sort() } }
      }

      if (query.includes('deleteAccess')) {
        const deleted = operation.variables.values
        return { data: { deleteAccess: values.filter((value) => !deleted.includes(value)) } }
      }

      if (/\baccess\b/.test(query) && !query.includes('permission')) {
        return { data: { access: values } }
      }

      return {
        data: {
          me: {
            permission: JSON.stringify(permissions),
            settings: '{}',
            email: 'admin@example.com',
            name: 'Admin',
            token: '',
          },
        },
      }
    })

    req.reply(batched ? responses : responses[0])
  }).as('gql')
}

function visitAccess(options) {
  setupIntercept(options)
  cy.visit('/access')
  cy.wait('@gql')
  cy.wait('@gql')
}

describe('Access list', () => {
  it('lists and filters access values', () => {
    visitAccess()

    cy.get('.v-app-bar-title').should('contain', 'Access')
    cy.get('.item-title').should('have.length', 2)
    cy.get('.search input').type('mem')
    cy.get('.item-title').should('have.length', 1).and('contain', 'member')
  })

  it('adds an access value', () => {
    visitAccess()

    cy.get('.btn-add').click()
    cy.get('.v-dialog input').type('editor')
    cy.get('.v-dialog').contains('button', 'Add').click()

    cy.wait('@gql').then(({ request }) => {
      const operation = Array.isArray(request.body) ? request.body[0] : request.body
      expect(operation.variables.value).to.equal('editor')
    })

    cy.get('.item-title').should('contain', 'editor')
  })

  it('deletes selected access values after confirmation', () => {
    visitAccess()

    cy.get('.items .v-checkbox-btn').first().click()
    cy.get('.btn-delete').click()
    cy.get('.v-dialog').should('contain', 'Existing restrictions are not changed')
    cy.get('.v-dialog').contains('button', 'Delete').click()

    cy.wait('@gql').then(({ request }) => {
      const operation = Array.isArray(request.body) ? request.body[0] : request.body
      expect(operation.variables.values).to.deep.equal(['alpha'])
    })

    cy.get('.item-title').should('have.length', 1).and('contain', 'member')
  })

  it('hides add and delete controls without their permissions', () => {
    visitAccess({ permissions: { 'access:view': true } })

    cy.get('.btn-add').should('not.exist')
    cy.get('.items .v-checkbox-btn').should('not.exist')
  })
})
