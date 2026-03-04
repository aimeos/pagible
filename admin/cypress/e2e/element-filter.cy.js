/**
 * E2E tests for the filter sidebar (AsideList) and navigation drawer
 * on the /elements route.
 *
 * GraphQL is intercepted at POST /graphql. Apollo's BatchHttpLink sends
 * requests as JSON arrays, so the handler checks whether req.body is an
 * array (batched) or an object (single) and replies in the same shape.
 */

const ALL_PERMISSIONS = {
  'element:view': true,
  'element:add': true,
  'element:save': true,
  'element:drop': true,
  'element:keep': true,
  'element:purge': true,
  'element:publish': true,
  'page:view': true,
  'file:view': true,
}

const ME_ADMIN = {
  permission: JSON.stringify(ALL_PERMISSIONS),
  email: 'admin@example.com',
  name: 'Admin',
}

/** A minimal element entry as the GraphQL `elements` query would return. */
function makeElement(overrides = {}) {
  return Object.assign({
    id: '1',
    lang: 'en',
    name: 'Hero Banner',
    type: 'heading',
    data: JSON.stringify({}),
    editor: 'admin@example.com',
    created_at: '2026-01-01 00:00:00',
    updated_at: '2026-01-01 00:00:00',
    deleted_at: null,
    latest: {
      id: '10',
      published: true,
      publish_at: null,
      data: JSON.stringify({ name: 'Hero Banner', type: 'heading', lang: 'en' }),
      editor: 'admin@example.com',
      created_at: '2026-01-01 00:00:00',
    },
  }, overrides)
}

/** Wraps an elements array in the paginated GraphQL response shape. */
function elementsResponse(elements) {
  return {
    elements: {
      data: elements,
      paginatorInfo: { lastPage: 1 },
    },
  }
}

/**
 * Register a single intercept that handles all GraphQL operations.
 */
function setupIntercept({
  meResponse = ME_ADMIN,
  elements = [],
} = {}) {
  cy.intercept('POST', '/graphql', (req) => {
    const isBatch = Array.isArray(req.body)
    const ops = isBatch ? req.body : [req.body]

    const responses = ops.map((op) => {
      const query = op.query || ''

      if (query.includes('cmsLogin')) {
        return { data: { cmsLogin: meResponse } }
      }
      if (query.includes('cmsLogout')) {
        return { data: { cmsLogout: { email: 'admin@example.com', name: 'Admin' } } }
      }
      if (query.includes('elements')) {
        return { data: elementsResponse(elements) }
      }
      // Default: auth-check (me) query
      if (meResponse && typeof meResponse === 'object') {
        return {
          data: {
            me: {
              permission: meResponse.permission,
              email: meResponse.email,
              name: meResponse.name,
            },
          },
        }
      }
      return { data: { me: null } }
    })

    req.reply(isBatch ? responses : responses[0])
  }).as('gql')
}

/** Authenticate and navigate to /elements, waiting for the initial GQL calls. */
function visitElements(elements = [], meResponse = ME_ADMIN) {
  setupIntercept({ meResponse, elements })
  cy.visit('/elements')
  cy.wait('@gql') // me query
  cy.wait('@gql') // elements query
}

/** At desktop viewport (>=960px) the aside drawer auto-opens on mount. */
function ensureAsideVisible() {
  cy.get('.v-navigation-drawer--right').should('be.visible')
}

// ---------------------------------------------------------------------------
// Test Suite: Filter Sidebar
// ---------------------------------------------------------------------------

describe('Element Filter Sidebar', () => {
  // ---- Sidebar open/close ----

  it('aside toggle button opens the filter sidebar', () => {
    cy.viewport(800, 660) // mobile width (< md 960px threshold)
    visitElements([makeElement()])
    cy.get('.v-navigation-drawer--right').should('not.be.visible')
    cy.get('.v-app-bar .v-btn[title="Toggle side menu"]').click()
    cy.get('.v-navigation-drawer--right').should('be.visible')
  })

  it('aside toggle button closes the filter sidebar when already open', () => {
    visitElements([makeElement()])
    ensureAsideVisible()
    cy.get('.v-app-bar .v-btn[title="Toggle side menu"]').click()
    cy.get('.v-navigation-drawer--right').should('not.be.visible')
  })

  // ---- Filter groups: verify options exist ----

  it('shows publish filter group with Published, Scheduled, Drafts options', () => {
    visitElements([makeElement()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('publish').click()
    const drawer = cy.get('.v-navigation-drawer--right')
    drawer.should('contain', 'Published')
    drawer.should('contain', 'Scheduled')
    drawer.should('contain', 'Drafts')
  })

  it('shows trashed filter group with Available only and Only trashed options', () => {
    visitElements([makeElement()])
    ensureAsideVisible()
    const drawer = cy.get('.v-navigation-drawer--right')
    drawer.should('contain', 'Available only')
    drawer.should('contain', 'Only trashed')
  })

  it('shows editor filter group with Edited by me option', () => {
    visitElements([makeElement()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('editor').click()
    cy.get('.v-navigation-drawer--right').should('contain', 'Edited by me')
  })

  it('shows languages filter group', () => {
    visitElements([makeElement()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').should('contain', 'languages')
  })

  // ---- Filter interactions ----

  it('clicking a filter option triggers elements reload', () => {
    visitElements([makeElement()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('publish').click()
    cy.get('.v-navigation-drawer--right').contains('Published').click()
    cy.wait('@gql')
  })

  it('Reset button is disabled when filters are at defaults', () => {
    visitElements([makeElement()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right .v-btn.reset').should('be.disabled')
  })

  it('Reset button becomes enabled after changing a filter', () => {
    visitElements([makeElement()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('publish').click()
    cy.get('.v-navigation-drawer--right').contains('Published').click()
    cy.wait('@gql')
    cy.get('.v-navigation-drawer--right .v-btn.reset').should('not.be.disabled')
  })

  it('clicking Reset button resets filters to defaults', () => {
    visitElements([makeElement()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('publish').click()
    cy.get('.v-navigation-drawer--right').contains('Published').click()
    cy.wait('@gql')
    cy.get('.v-navigation-drawer--right .v-btn.reset').click()
    cy.get('.v-navigation-drawer--right .v-btn.reset').should('be.disabled')
  })

  // ---- GQL filter assertions ----

  it('selecting "Only trashed" sends trashed filter in GQL request', () => {
    visitElements([makeElement()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('Only trashed').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      const elementsOp = ops.find((op) => (op.query || '').includes('elements'))
      expect(elementsOp).to.exist
      expect(elementsOp.variables.trashed).to.equal('ONLY')
    })
  })

  it('selecting "Published" sends publish filter in GQL request', () => {
    visitElements([makeElement()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('publish').click()
    cy.get('.v-navigation-drawer--right').contains('Published').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      const elementsOp = ops.find((op) => (op.query || '').includes('elements'))
      expect(elementsOp).to.exist
      expect(elementsOp.variables.publish).to.equal('PUBLISHED')
    })
  })
})

// ---------------------------------------------------------------------------
// Test Suite: Navigation Drawer (from elements route)
// ---------------------------------------------------------------------------

describe('Navigation Drawer (Elements)', () => {
  it('nav toggle button opens the navigation drawer', () => {
    visitElements([makeElement()])
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').should('be.visible')
  })

  it('nav toggle button closes the navigation drawer when already open', () => {
    visitElements([makeElement()])
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').should('be.visible')
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').should('not.be.visible')
  })

  it('shows Pages link when user has page:view permission', () => {
    visitElements([makeElement()])
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').contains('Pages').should('exist')
  })

  it('shows Shared elements link when user has element:view permission', () => {
    visitElements([makeElement()])
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').contains('Shared elements').should('exist')
  })

  it('shows Files link when user has file:view permission', () => {
    visitElements([makeElement()])
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').contains('Files').should('exist')
  })

  it('hides Pages link when user lacks page:view permission', () => {
    const perms = { ...ALL_PERMISSIONS }
    delete perms['page:view']
    const me = {
      permission: JSON.stringify(perms),
      email: 'admin@example.com',
      name: 'Admin',
    }
    visitElements([], me)
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').should('be.visible')
    cy.get('.v-navigation-drawer--left').contains('Pages').should('not.exist')
  })

  it('hides Files link when user lacks file:view permission', () => {
    const perms = { ...ALL_PERMISSIONS }
    delete perms['file:view']
    const me = {
      permission: JSON.stringify(perms),
      email: 'admin@example.com',
      name: 'Admin',
    }
    visitElements([], me)
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').should('be.visible')
    cy.get('.v-navigation-drawer--left').contains('Files').should('not.exist')
  })
})
