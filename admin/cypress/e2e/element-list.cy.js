/**
 * E2E tests for the element list view at /elements.
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
  addElement = null,
  dropElement = null,
  keepElement = null,
  purgeElement = null,
  pubElement = null,
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
      if (query.includes('addElement')) {
        return {
          data: {
            addElement: addElement || {
              id: '99',
              lang: '',
              name: 'New shared element',
              type: 'heading',
              data: '{}',
              editor: 'admin@example.com',
              created_at: '2026-01-01 00:00:00',
              updated_at: '2026-01-01 00:00:00',
              deleted_at: null,
            },
          },
        }
      }
      if (query.includes('dropElement')) {
        return { data: { dropElement: dropElement || { id: '1' } } }
      }
      if (query.includes('keepElement')) {
        return { data: { keepElement: keepElement || { id: '1' } } }
      }
      if (query.includes('purgeElement')) {
        return { data: { purgeElement: purgeElement || { id: '1' } } }
      }
      if (query.includes('pubElement')) {
        return { data: { pubElement: pubElement || { id: '1' } } }
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

// ---------------------------------------------------------------------------
// Test Suite
// ---------------------------------------------------------------------------

describe('Element List', () => {
  // ---- Layout & app bar ----

  it('shows "Shared elements" title in app bar', () => {
    visitElements()
    cy.get('.v-app-bar-title').should('contain', 'Shared elements')
  })

  it('shows navigation toggle and aside toggle buttons in app bar', () => {
    visitElements()
    cy.get('.v-app-bar .v-btn').should('have.length.at.least', 2)
  })

  // ---- Loading & empty state ----

  it('shows "No entries found" when element list is empty', () => {
    visitElements([])
    cy.get('.element-list').should('contain', 'No entries found')
  })

  it('shows element items when elements are returned', () => {
    const el = makeElement()
    visitElements([el])
    cy.get('.items .v-list-item').should('have.length.at.least', 1)
    cy.get('.item-title').first().should('contain', 'Hero Banner')
  })

  it('displays element type as subtitle', () => {
    const el = makeElement()
    visitElements([el])
    cy.get('.item-type').first().should('contain', 'heading')
  })

  it('displays the language badge', () => {
    const el = makeElement()
    visitElements([el])
    cy.get('.item-lang').first().should('contain', 'en')
  })

  it('displays the editor', () => {
    const el = makeElement()
    visitElements([el])
    cy.get('.item-editor').first().should('contain', 'admin@example.com')
  })

  // ---- Search ----

  it('has a search field', () => {
    visitElements()
    cy.get('.search input').should('exist')
  })

  it('search field triggers reload on input', () => {
    const el = makeElement()
    visitElements([el])
    cy.get('.search input').type('test')
    cy.wait('@gql') // debounced search query
  })

  // ---- Sort ----

  it('shows sort menu with options', () => {
    visitElements()
    cy.get('.layout .v-btn').click()
    cy.get('.v-list').should('contain', 'latest')
    cy.get('.v-list').should('contain', 'oldest')
    cy.get('.v-list').should('contain', 'name')
    cy.get('.v-list').should('contain', 'type')
    cy.get('.v-list').should('contain', 'editor')
  })

  it('clicking a sort option triggers GQL reload', () => {
    visitElements()
    cy.get('.layout .v-btn').click()
    cy.contains('.v-list .v-btn', 'name').click()
    cy.wait('@gql')
  })

  // ---- Add element ----

  it('shows add element button for users with element:add permission', () => {
    visitElements()
    cy.get('.v-btn[title="Add element"]').should('exist')
  })

  it('hides add element button for users without element:add permission', () => {
    const me = {
      permission: JSON.stringify({ 'element:view': true }),
      email: 'viewer@example.com',
      name: 'Viewer',
    }
    visitElements([], me)
    cy.get('.v-btn[title="Add element"]').should('not.exist')
  })

  it('clicking add element button opens schema picker dialog', () => {
    visitElements()
    cy.get('.v-btn[title="Add element"]').first().click()
    cy.get('.v-dialog').should('be.visible')
  })

  // ---- Item display ----

  it('shows item name in .item-title', () => {
    const el = makeElement({
      name: 'Footer CTA',
      latest: { ...makeElement().latest, data: JSON.stringify({ name: 'Footer CTA', type: 'heading', lang: 'en' }) },
    })
    visitElements([el])
    cy.get('.item-title').first().should('contain', 'Footer CTA')
  })

  it('shows trashed class on trashed items', () => {
    const el = makeElement({ deleted_at: '2026-01-15 00:00:00' })
    visitElements([el])
    cy.get('.item-content.trashed').should('exist')
  })

  it('shows draft checkbox class for unpublished items', () => {
    const el = makeElement({ latest: { ...makeElement().latest, published: false } })
    visitElements([el])
    cy.get('.items .v-checkbox-btn.draft').should('exist')
  })

  it('shows clock icon for items with publish_at', () => {
    const el = makeElement({ latest: { ...makeElement().latest, publish_at: '2026-06-01 00:00:00' } })
    visitElements([el])
    cy.get('.publish-at').should('exist')
  })

  // ---- Item context menu ----

  it('shows context menu with actions when clicking three-dot button on an item', () => {
    const el = makeElement()
    visitElements([el])
    cy.get('.items .v-list-item .actions .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-toolbar-title').should('contain', 'Actions')
  })

  it('context menu shows Publish for unpublished element', () => {
    const el = makeElement({ latest: { ...makeElement().latest, published: false } })
    visitElements([el])
    cy.get('.items .v-list-item .actions .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-list').should('contain', 'Publish')
  })

  it('context menu hides Publish for already published element', () => {
    const el = makeElement()
    visitElements([el])
    cy.get('.items .v-list-item .actions .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-list .v-list-item').filter(':visible').then(($items) => {
      const texts = [...$items].map((item) => item.textContent.trim())
      expect(texts.some((t) => t === 'Publish')).to.be.false
    })
  })

  it('context menu shows Delete for non-trashed element', () => {
    const el = makeElement()
    visitElements([el])
    cy.get('.items .v-list-item .actions .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-list').should('contain', 'Delete')
  })

  it('context menu shows Restore for trashed element', () => {
    const el = makeElement({ deleted_at: '2026-01-15 00:00:00' })
    visitElements([el])
    cy.get('.items .v-list-item .actions .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-list').should('contain', 'Restore')
  })

  it('context menu hides Delete for trashed element', () => {
    const el = makeElement({ deleted_at: '2026-01-15 00:00:00' })
    visitElements([el])
    cy.get('.items .v-list-item .actions .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-list .v-btn').then(($btns) => {
      const texts = [...$btns].map((b) => b.textContent.trim())
      expect(texts).to.not.include('Delete')
    })
  })

  it('context menu shows Purge button', () => {
    const el = makeElement()
    visitElements([el])
    cy.get('.items .v-list-item .actions .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-list').should('contain', 'Purge')
  })

  // ---- Context menu mutations ----

  it('clicking Publish sends pubElement mutation', () => {
    const el = makeElement({ latest: { ...makeElement().latest, published: false } })
    visitElements([el])
    cy.get('.items .v-list-item .actions .v-btn[title="Actions"]').first().click()
    cy.contains('.v-card .v-list .v-btn', 'Publish').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      expect(ops.some((op) => (op.query || '').includes('pubElement'))).to.be.true
    })
  })

  it('clicking Delete sends dropElement mutation', () => {
    const el = makeElement()
    visitElements([el])
    cy.get('.items .v-list-item .actions .v-btn[title="Actions"]').first().click()
    cy.contains('.v-card .v-list .v-btn', 'Delete').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      expect(ops.some((op) => (op.query || '').includes('dropElement'))).to.be.true
    })
  })

  it('clicking Purge sends purgeElement mutation', () => {
    const el = makeElement()
    visitElements([el])
    cy.get('.items .v-list-item .actions .v-btn[title="Actions"]').first().click()
    cy.contains('.v-card .v-list .v-btn', 'Purge').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      expect(ops.some((op) => (op.query || '').includes('purgeElement'))).to.be.true
    })
  })

  // ---- Bulk actions ----

  it('shows bulk checkbox and actions button in header', () => {
    visitElements()
    cy.get('.header .bulk .v-checkbox-btn').should('exist')
    cy.get('.header .bulk .v-btn[title="Actions"]').should('exist')
  })

  it('bulk actions button is disabled when no items are checked', () => {
    const el = makeElement()
    visitElements([el])
    cy.get('.header .bulk .v-btn[title="Actions"]').should('be.disabled')
  })

  it('checking an element item enables the bulk actions button', () => {
    const el = makeElement()
    visitElements([el])
    cy.get('.items .v-list-item .item-check').first().click()
    cy.get('.header .bulk .v-btn[title="Actions"]').should('not.be.disabled')
  })

  it('toggle all checkbox checks all items', () => {
    const elements = [
      makeElement({ id: '1' }),
      makeElement({ id: '2', name: 'Sidebar Widget', type: 'text' }),
    ]
    visitElements(elements)
    cy.get('.header .bulk .v-checkbox-btn').click()
    cy.get('.items .v-list-item .item-check').each(($cb) => {
      cy.wrap($cb).find('input').should('be.checked')
    })
  })

  it('bulk actions menu shows Publish, Delete, Restore, Purge', () => {
    const el = makeElement({
      deleted_at: '2026-01-15 00:00:00',
      latest: { ...makeElement().latest, published: false },
    })
    visitElements([el])
    cy.get('.items .v-list-item .item-check').first().click()
    cy.get('.header .bulk .v-btn[title="Actions"]').click()
    cy.get('.v-card .v-list').should('contain', 'Publish')
    cy.get('.v-card .v-list').should('contain', 'Delete')
    cy.get('.v-card .v-list').should('contain', 'Restore')
    cy.get('.v-card .v-list').should('contain', 'Purge')
  })

  // ---- Multiple elements ----

  it('displays multiple elements in list', () => {
    const elements = [
      makeElement({ id: '1' }),
      makeElement({
        id: '2', name: 'Footer CTA', type: 'text',
        latest: { ...makeElement().latest, id: '20', data: JSON.stringify({ name: 'Footer CTA', type: 'text', lang: 'en' }) },
      }),
      makeElement({
        id: '3', name: 'Sidebar Widget', type: 'image',
        latest: { ...makeElement().latest, id: '30', data: JSON.stringify({ name: 'Sidebar Widget', type: 'image', lang: 'en' }) },
      }),
    ]
    visitElements(elements)
    cy.get('.items .v-list-item').should('have.length', 3)
    cy.get('.item-title').eq(0).should('contain', 'Hero Banner')
    cy.get('.item-title').eq(1).should('contain', 'Footer CTA')
    cy.get('.item-title').eq(2).should('contain', 'Sidebar Widget')
  })

  // ---- Permission-based visibility ----

  it('does not show element list when user lacks element:view permission', () => {
    const me = {
      permission: JSON.stringify({ 'page:view': true }),
      email: 'limited@example.com',
      name: 'Limited',
    }
    setupIntercept({ meResponse: me, elements: [] })
    cy.visit('/elements')
    cy.wait('@gql')
    cy.get('.element-list').should('not.exist')
  })
})
