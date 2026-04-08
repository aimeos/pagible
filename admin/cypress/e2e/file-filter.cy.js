/**
 * E2E tests for the filter sidebar (AsideList) and navigation drawer
 * on the /files route.
 *
 * GraphQL is intercepted at POST /graphql. Apollo's BatchHttpLink sends
 * requests as JSON arrays, so the handler checks whether req.body is an
 * array (batched) or an object (single) and replies in the same shape.
 */

const ALL_PERMISSIONS = {
  'file:view': true,
  'file:add': true,
  'file:save': true,
  'file:drop': true,
  'file:keep': true,
  'file:purge': true,
  'file:publish': true,
  'page:view': true,
  'element:view': true,
}

const ME_ADMIN = {
  permission: JSON.stringify(ALL_PERMISSIONS),
  email: 'admin@example.com',
  name: 'Admin',
}

/**
 * A minimal file entry as the GraphQL `files` query would return.
 * name/lang/mime/path/previews/description/transcription must be in latest.data.
 */
function makeFile(overrides = {}) {
  return Object.assign({
    id: '1',
    lang: 'en',
    name: 'test.png',
    mime: 'image/png',
    path: 'cms/test/test_1234.png',
    previews: JSON.stringify({ 180: 'cms/test/test_180.webp' }),
    description: JSON.stringify({ en: 'A test image' }),
    transcription: JSON.stringify({}),
    editor: 'admin@example.com',
    created_at: '2026-01-01 00:00:00',
    updated_at: '2026-01-01 00:00:00',
    deleted_at: null,
    byversions_count: 1,
    latest: {
      id: '10',
      published: true,
      publish_at: null,
      data: JSON.stringify({
        name: 'test.png',
        lang: 'en',
        mime: 'image/png',
        path: 'cms/test/test_1234.png',
        previews: { 180: 'cms/test/test_180.webp' },
        description: { en: 'A test image' },
        transcription: {},
      }),
      editor: 'admin@example.com',
      created_at: '2026-01-01 00:00:00',
    },
  }, overrides)
}

/** Wraps a files array in the paginated GraphQL response shape. */
function filesResponse(files) {
  return {
    files: {
      data: files,
      paginatorInfo: { lastPage: 1 },
    },
  }
}

/**
 * Register a single intercept that handles all GraphQL operations.
 */
function setupIntercept({
  meResponse = ME_ADMIN,
  files = [],
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
      if (query.includes('files')) {
        return { data: filesResponse(files) }
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

/** Authenticate and navigate to /files, waiting for the initial GQL calls. */
function visitFiles(files = [], meResponse = ME_ADMIN) {
  setupIntercept({ meResponse, files })
  cy.visit('/files')
  cy.wait('@gql') // me query
  cy.wait('@gql') // files query
}

/** At desktop viewport (>=960px) the aside drawer auto-opens on mount. */
function ensureAsideVisible() {
  cy.get('.v-navigation-drawer--right').should('be.visible')
}

// ---------------------------------------------------------------------------
// Test Suite: Filter Sidebar
// ---------------------------------------------------------------------------

describe('File Filter Sidebar', () => {
  // ---- Sidebar open/close ----

  it('aside toggle button opens the filter sidebar', () => {
    cy.viewport(800, 660) // mobile width (< md 960px threshold)
    visitFiles([makeFile()])
    cy.get('.v-navigation-drawer--right').should('not.be.visible')
    cy.get('.v-app-bar .v-btn[title="Toggle side menu"]').click()
    cy.get('.v-navigation-drawer--right').should('be.visible')
  })

  it('aside toggle button closes the filter sidebar when already open', () => {
    visitFiles([makeFile()])
    ensureAsideVisible()
    cy.get('.v-app-bar .v-btn[title="Toggle side menu"]').click()
    cy.get('.v-navigation-drawer--right').should('not.be.visible')
  })

  // ---- Filter groups: verify options exist ----

  it('shows publish filter group with Published, Scheduled, Drafts options', () => {
    visitFiles([makeFile()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('publish').click()
    const drawer = cy.get('.v-navigation-drawer--right')
    drawer.should('contain', 'Published')
    drawer.should('contain', 'Scheduled')
    drawer.should('contain', 'Drafts')
  })

  it('shows trashed filter group with Available only and Only trashed options', () => {
    visitFiles([makeFile()])
    ensureAsideVisible()
    const drawer = cy.get('.v-navigation-drawer--right')
    drawer.should('contain', 'Available only')
    drawer.should('contain', 'Only trashed')
  })

  it('shows editor filter group with Edited by me option', () => {
    visitFiles([makeFile()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('editor').click()
    cy.get('.v-navigation-drawer--right').should('contain', 'Edited by me')
  })

  it('shows languages filter group', () => {
    visitFiles([makeFile()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').should('contain', 'languages')
  })

  // ---- Filter interactions ----

  it('clicking a filter option triggers files reload', () => {
    visitFiles([makeFile()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('publish').click()
    cy.get('.v-navigation-drawer--right').contains('Published').click()
    cy.wait('@gql')
  })

  it('Reset button is disabled when filters are at defaults', () => {
    visitFiles([makeFile()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right .v-btn.reset').should('be.disabled')
  })

  it('Reset button becomes enabled after changing a filter', () => {
    visitFiles([makeFile()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('publish').click()
    cy.get('.v-navigation-drawer--right').contains('Published').click()
    cy.wait('@gql')
    cy.get('.v-navigation-drawer--right .v-btn.reset').should('not.be.disabled')
  })

  it('clicking Reset button resets filters to defaults', () => {
    visitFiles([makeFile()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('publish').click()
    cy.get('.v-navigation-drawer--right').contains('Published').click()
    cy.wait('@gql')
    cy.get('.v-navigation-drawer--right .v-btn.reset').click()
    cy.get('.v-navigation-drawer--right .v-btn.reset').should('be.disabled')
  })

  // ---- GQL filter assertions ----

  it('selecting "Only trashed" sends trashed filter in GQL request', () => {
    visitFiles([makeFile()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('Only trashed').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      const filesOp = ops.find((op) => (op.query || '').includes('files'))
      expect(filesOp).to.exist
      expect(filesOp.variables.trashed).to.equal('ONLY')
    })
  })

  it('selecting "Published" sends publish filter in GQL request', () => {
    visitFiles([makeFile()])
    ensureAsideVisible()
    cy.get('.v-navigation-drawer--right').contains('publish').click()
    cy.get('.v-navigation-drawer--right').contains('Published').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      const filesOp = ops.find((op) => (op.query || '').includes('files'))
      expect(filesOp).to.exist
      expect(filesOp.variables.publish).to.equal('PUBLISHED')
    })
  })
})

// ---------------------------------------------------------------------------
// Test Suite: Navigation Drawer (from files route)
// ---------------------------------------------------------------------------

describe('Navigation Drawer (Files)', () => {
  it('nav toggle button opens the navigation drawer', () => {
    visitFiles([makeFile()])
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').should('be.visible')
  })

  it('nav toggle button closes the navigation drawer when already open', () => {
    visitFiles([makeFile()])
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').should('be.visible')
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').should('not.be.visible')
  })

  it('shows Pages link when user has page:view permission', () => {
    visitFiles([makeFile()])
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').contains('Pages').should('exist')
  })

  it('shows Shared elements link when user has element:view permission', () => {
    visitFiles([makeFile()])
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').contains('Shared elements').should('exist')
  })

  it('shows Files link when user has file:view permission', () => {
    visitFiles([makeFile()])
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
    visitFiles([], me)
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').should('be.visible')
    cy.get('.v-navigation-drawer--left').contains('Pages').should('not.exist')
  })

  it('hides Shared elements link when user lacks element:view permission', () => {
    const perms = { ...ALL_PERMISSIONS }
    delete perms['element:view']
    const me = {
      permission: JSON.stringify(perms),
      email: 'admin@example.com',
      name: 'Admin',
    }
    visitFiles([], me)
    cy.get('.v-app-bar').first().find('.v-btn').first().click()
    cy.get('.v-navigation-drawer--left').should('be.visible')
    cy.get('.v-navigation-drawer--left').contains('Shared elements').should('not.exist')
  })
})
