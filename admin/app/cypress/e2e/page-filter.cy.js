/**
 * E2E tests for the filter sidebar (AsideList) and navigation drawer
 * on the /pages route.
 *
 * GraphQL is intercepted at POST /graphql. Apollo's BatchHttpLink sends
 * requests as JSON arrays, so the handler checks whether req.body is an
 * array (batched) or an object (single) and replies in the same shape.
 */

const ALL_PERMISSIONS = {
  'page:view': true,
  'page:add': true,
  'page:save': true,
  'page:move': true,
  'page:drop': true,
  'page:keep': true,
  'page:purge': true,
  'page:publish': true,
  'page:synthesize': true,
  'audio:transcribe': true,
  'element:view': true,
  'file:view': true,
}

const ME_ADMIN = {
  permission: JSON.stringify(ALL_PERMISSIONS),
  email: 'admin@example.com',
  name: 'Admin',
}

/** A minimal page entry as the GraphQL `pages` query would return. */
function makePage(overrides = {}) {
  return Object.assign({
    id: '1',
    parent_id: null,
    created_at: '2026-01-01 00:00:00',
    deleted_at: null,
    editor: 'admin@example.com',
    has: false,
    latest: {
      id: '10',
      published: true,
      publish_at: null,
      data: JSON.stringify({
        name: 'Home',
        title: 'Home Page',
        path: 'home',
        lang: 'en',
        status: 1,
        domain: '',
        to: '',
        tag: '',
        type: '',
        theme: '',
        cache: 5,
      }),
      editor: 'admin@example.com',
      created_at: '2026-01-01 00:00:00',
    },
  }, overrides)
}

/** Wraps a pages array in the paginated GraphQL response shape. */
function pagesResponse(pages) {
  return {
    pages: {
      data: pages,
      paginatorInfo: { currentPage: 1, lastPage: 1 },
    },
  }
}

/**
 * Register a single intercept that handles all GraphQL operations.
 */
function setupIntercept({
  meResponse = ME_ADMIN,
  pages = [],
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
      if (query.includes('pages')) {
        return { data: pagesResponse(pages) }
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

/** Authenticate and navigate to /pages, waiting for the initial GQL calls. */
function visitPages(pages = [], meResponse = ME_ADMIN) {
  setupIntercept({ meResponse, pages })
  cy.visit('/pages')
  cy.wait('@gql') // me query
  cy.wait('@gql') // pages query
}

/** At desktop viewport (≥960px) the aside drawer auto-opens on mount. */
function ensureAsideVisible() {
  cy.get('.v-navigation-drawer--right').should('be.visible')
}

// ---------------------------------------------------------------------------
// Test Suite: Filter Sidebar
// ---------------------------------------------------------------------------

describe('Page Filter Sidebar', () => {
  // ---- Sidebar open/close ----

  it('aside toggle button opens the filter sidebar', () => {
    cy.viewport(800, 660) // mobile width (< md 960px threshold)
    visitPages([makePage()])
    cy.get('.v-navigation-drawer--right').should('not.be.visible')
    cy.get('.v-app-bar .v-btn[title="Toggle side menu"]').click()
    cy.get('.v-navigation-drawer--right').should('be.visible')
  })

  it('aside toggle button closes the filter sidebar when already open', () => {
    visitPages([makePage()])
    ensureAsideVisible()
    cy.get('.v-app-bar .v-btn[title="Toggle side menu"]').click()
    cy.get('.v-navigation-drawer--right').should('not.be.visible')
  })

  // ---- Filter groups: verify options exist ----

  it('shows view filter group with Tree and List options', () => {
    visitPages([makePage()])
    ensureAsideVisible()
    const drawer = cy.get('.v-navigation-drawer--right')
    drawer.should('contain', 'Tree')
    drawer.should('contain', 'List')
  })

  it('shows publish filter group with All, Published, Scheduled, Drafts options', () => {
    visitPages([makePage()])
    ensureAsideVisible()
    // Expand the publish group
    cy.get('.v-navigation-drawer--right').contains('publish').click()
    const drawer = cy.get('.v-navigation-drawer--right')
    drawer.should('contain', 'Published')
    drawer.should('contain', 'Scheduled')
    drawer.should('contain', 'Drafts')
  })

  it('shows trashed filter group with Available only and Only trashed options', () => {
    visitPages([makePage()])
    ensureAsideVisible()
    const drawer = cy.get('.v-navigation-drawer--right')
    drawer.should('contain', 'Available only')
    drawer.should('contain', 'Only trashed')
  })

  it('shows status filter group with Enabled, Hidden, Disabled options', () => {
    visitPages([makePage()])
    ensureAsideVisible()
    // Expand the status group
    cy.get('.v-navigation-drawer--right').contains('status').click()
    const drawer = cy.get('.v-navigation-drawer--right')
    drawer.should('contain', 'Enabled')
    drawer.should('contain', 'Hidden')
    drawer.should('contain', 'Disabled')
  })

  it('shows cache filter group with No cache option', () => {
    visitPages([makePage()])
    ensureAsideVisible()
    // Expand the cache group
    cy.get('.v-navigation-drawer--right').contains('cache').click()
    cy.get('.v-navigation-drawer--right').should('contain', 'No cache')
  })

  it('shows editor filter group with Edited by me option', () => {
    visitPages([makePage()])
    ensureAsideVisible()
    // Expand the editor group
    cy.get('.v-navigation-drawer--right').contains('editor').click()
    cy.get('.v-navigation-drawer--right').should('contain', 'Edited by me')
  })

  // ---- Filter interactions ----

  it('clicking a filter option triggers page reload', () => {
    visitPages([makePage()])
    ensureAsideVisible()
    // Expand the publish group and click Published
    cy.get('.v-navigation-drawer--right').contains('publish').click()
    cy.get('.v-navigation-drawer--right').contains('Published').click()
    cy.wait('@gql')
  })

  it('clicking List view filter option triggers reload', () => {
    visitPages([makePage()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('List').click()
    cy.wait('@gql')
  })

  it('Reset button is disabled when filters are at defaults', () => {
    visitPages([makePage()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right .v-btn.reset').should('be.disabled')
  })

  it('Reset button becomes enabled after changing a filter', () => {
    visitPages([makePage()])
    ensureAsideVisible()
    // Expand publish group and change filter
    cy.get('.v-navigation-drawer--right').contains('publish').click()
    cy.get('.v-navigation-drawer--right').contains('Published').click()
    cy.wait('@gql')
    cy.get('.v-navigation-drawer--right .v-btn.reset').should('not.be.disabled')
  })

  it('clicking Reset button resets filters to defaults', () => {
    visitPages([makePage()])
    ensureAsideVisible()
    // Change a filter first
    cy.get('.v-navigation-drawer--right').contains('publish').click()
    cy.get('.v-navigation-drawer--right').contains('Published').click()
    cy.wait('@gql')
    // Click Reset
    cy.get('.v-navigation-drawer--right .v-btn.reset').click()
    // Reset button should be disabled again
    cy.get('.v-navigation-drawer--right .v-btn.reset').should('be.disabled')
  })

  it('selecting "Only trashed" sends trashed filter in GQL request', () => {
    visitPages([makePage()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('Only trashed').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      const pagesOp = ops.find((op) => (op.query || '').includes('pages'))
      expect(pagesOp).to.exist
      expect(pagesOp.variables.trashed).to.equal('ONLY')
    })
  })

  it('selecting "Published" sends publish filter in GQL request', () => {
    visitPages([makePage()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('publish').click()
    cy.get('.v-navigation-drawer--right').contains('Published').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      const pagesOp = ops.find((op) => (op.query || '').includes('pages'))
      expect(pagesOp).to.exist
      expect(pagesOp.variables.publish).to.equal('PUBLISHED')
    })
  })
})

// ---------------------------------------------------------------------------
// Test Suite: Navigation Drawer
// ---------------------------------------------------------------------------

describe('Navigation Drawer', () => {
  it('nav toggle button opens the navigation drawer', () => {
    visitPages([makePage()])
    // Nav toggle is the first button in the app bar (prepend slot)
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').should('be.visible')
  })

  it('nav toggle button closes the navigation drawer when already open', () => {
    visitPages([makePage()])
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').should('be.visible')
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').should('not.be.visible')
  })

  it('shows Pages link when user has page:view permission', () => {
    visitPages([makePage()])
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').contains('Pages').should('exist')
  })

  it('shows Shared elements link when user has element:view permission', () => {
    visitPages([makePage()])
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').contains('Shared elements').should('exist')
  })

  it('shows Files link when user has file:view permission', () => {
    visitPages([makePage()])
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').contains('Files').should('exist')
  })

  it('hides Shared elements link when user lacks element:view permission', () => {
    const perms = { ...ALL_PERMISSIONS }
    delete perms['element:view']
    const me = {
      permission: JSON.stringify(perms),
      email: 'admin@example.com',
      name: 'Admin',
    }
    visitPages([], me)
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').should('be.visible')
    cy.get('.v-navigation-drawer--left').contains('Shared elements').should('not.exist')
  })

  it('hides Files link when user lacks file:view permission', () => {
    const perms = { ...ALL_PERMISSIONS }
    delete perms['file:view']
    const me = {
      permission: JSON.stringify(perms),
      email: 'admin@example.com',
      name: 'Admin',
    }
    visitPages([], me)
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').should('be.visible')
    cy.get('.v-navigation-drawer--left').contains('Files').should('not.exist')
  })

  it('Pages link navigates to /pages', () => {
    visitPages([makePage()])
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').contains('Pages').should('have.attr', 'href').and('include', '/pages')
  })
})
