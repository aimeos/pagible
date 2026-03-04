/**
 * E2E tests for the element detail stacked view.
 *
 * The ElementDetail component opens as an overlay on top of the element list
 * when an element item is clicked. Both views coexist in the DOM (stacked
 * via z-index), so selectors must be scoped to the detail's .view container.
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

/** Full element detail response shape for the `element(id)` query. */
function makeElementDetail(overrides = {}) {
  return Object.assign({
    id: '1',
    files: [],
    latest: {
      id: '10',
      published: true,
      data: JSON.stringify({}),
      editor: 'admin@example.com',
      created_at: '2026-01-01 00:00:00',
      files: [],
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
  elementDetail = null,
  versions = [],
  saveElement = null,
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
      if (query.includes('saveElement')) {
        return { data: { saveElement: saveElement || { id: op.variables?.id || '1' } } }
      }
      if (query.includes('pubElement')) {
        return { data: { pubElement: pubElement || { id: '1' } } }
      }
      // Versions query (contains 'versions')
      if (query.includes('versions')) {
        return { data: { element: { id: op.variables?.id || '1', versions: versions } } }
      }
      // Single element query for detail view — check BEFORE 'elements'
      if (query.includes('element(') && !query.includes('elements(')) {
        const detail = elementDetail || makeElementDetail()
        return {
          data: {
            element: {
              id: op.variables?.id || '1',
              files: detail.files || [],
              latest: detail.latest,
            },
          },
        }
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

/**
 * Navigate to /elements, wait for initial queries, click an element to open
 * the detail overlay, and wait for the detail data query.
 */
function visitElementDetail(elementOverrides = {}, detailOverrides = {}, meResponse = ME_ADMIN) {
  const element = makeElement(elementOverrides)
  const detail = makeElementDetail(detailOverrides)
  setupIntercept({ meResponse, elements: [element], elementDetail: detail })
  cy.visit('/elements')
  cy.wait('@gql') // me query
  cy.wait('@gql') // elements query
  cy.get('.item-text').first().click()
  cy.wait('@gql') // element(id) detail query
  cy.get('.element-details').should('be.visible')
}

/** Shorthand to scope selectors to the detail view (topmost stacked view). */
function detailView() {
  return cy.get('.view').last()
}

// ---------------------------------------------------------------------------
// Test Suite
// ---------------------------------------------------------------------------

describe('Element Detail', () => {
  // ---- App Bar ----

  it('shows "Element: {name}" title in detail app bar', () => {
    visitElementDetail()
    detailView().find('.v-app-bar-title').should('contain', 'Element: Hero Banner')
  })

  it('back button closes the detail view', () => {
    visitElementDetail()
    detailView().find('.v-btn[title="Back to list view"]').click()
    cy.get('.element-details').should('not.exist')
  })

  // ---- Save ----

  it('shows save button disabled when no changes are made', () => {
    visitElementDetail()
    detailView().find('.menu-save').should('be.disabled')
  })

  it('save button enables after changing the name field', () => {
    visitElementDetail()
    detailView().find('input[maxlength="255"]').first().clear().type('Updated Name')
    detailView().find('.menu-save').should('not.be.disabled')
  })

  it('save button sends saveElement mutation after making changes', () => {
    visitElementDetail()
    detailView().find('input[maxlength="255"]').first().clear().type('Updated Name')
    detailView().find('.menu-save').click()
    function waitForSaveElement() {
      return cy.wait('@gql').then((interception) => {
        const body = interception.request.body
        const ops = Array.isArray(body) ? body : [body]
        if (ops.some((op) => (op.query || '').includes('saveElement'))) {
          return
        }
        return waitForSaveElement()
      })
    }
    waitForSaveElement()
  })

  // ---- Publish ----

  it('shows publish button disabled when element is already published with no changes', () => {
    visitElementDetail({}, { latest: { ...makeElementDetail().latest, published: true } })
    detailView().find('.menu-publish').first().should('be.disabled')
  })

  it('shows publish button enabled when element is unpublished', () => {
    visitElementDetail(
      { latest: { ...makeElement().latest, published: false } },
      { latest: { ...makeElementDetail().latest, published: false } }
    )
    detailView().find('.menu-publish').last().should('not.be.disabled')
  })

  it('clicking publish fires pubElement mutation for unpublished element', () => {
    visitElementDetail(
      { latest: { ...makeElement().latest, published: false } },
      { latest: { ...makeElementDetail().latest, published: false } }
    )
    detailView().find('.menu-publish').last().click()
    function waitForPubElement() {
      return cy.wait('@gql').then((interception) => {
        const body = interception.request.body
        const ops = Array.isArray(body) ? body : [body]
        if (ops.some((op) => (op.query || '').includes('pubElement'))) {
          return
        }
        return waitForPubElement()
      })
    }
    waitForPubElement()
  })

  // ---- Schedule publish ----

  it('shows schedule publish button', () => {
    visitElementDetail(
      { latest: { ...makeElement().latest, published: false } },
      { latest: { ...makeElementDetail().latest, published: false } }
    )
    detailView().find('.menu-publish').should('exist')
  })

  // ---- History ----

  it('shows history button', () => {
    visitElementDetail()
    detailView().find('.v-btn[title="View history"]').should('exist')
  })

  it('clicking history button opens history dialog', () => {
    visitElementDetail({}, { latest: { ...makeElementDetail().latest, published: false } })
    detailView().find('.v-btn[title="View history"]').click()
    cy.get('.v-dialog').should('be.visible')
  })

  // ---- Tabs ----

  it('shows Element and Used by tabs', () => {
    visitElementDetail()
    detailView().find('.v-tab').should('have.length', 2)
    detailView().find('.v-tab').eq(0).should('contain', 'Element')
    detailView().find('.v-tab').eq(1).should('contain', 'Used by')
  })

  it('clicking Used by tab switches tab', () => {
    visitElementDetail()
    detailView().find('.v-tab').contains('Used by').click()
    detailView().find('.v-tab--selected').should('contain', 'Used by')
  })

  // ---- Detail fields ----

  it('shows Name and Language fields on Element tab', () => {
    visitElementDetail()
    cy.get('.element-details').should('contain', 'Name')
    cy.get('.element-details').should('contain', 'Language')
  })

  // ---- Permission-based behavior ----

  it('save button is disabled without element:save permission', () => {
    const me = {
      permission: JSON.stringify({
        'element:view': true,
        'element:publish': true,
      }),
      email: 'viewer@example.com',
      name: 'Viewer',
    }
    visitElementDetail({}, {}, me)
    detailView().find('.menu-save').should('be.disabled')
  })

  it('publish button is disabled without element:publish permission', () => {
    const me = {
      permission: JSON.stringify({
        'element:view': true,
        'element:save': true,
      }),
      email: 'editor@example.com',
      name: 'Editor',
    }
    visitElementDetail(
      { latest: { ...makeElement().latest, published: false } },
      { latest: { ...makeElementDetail().latest, published: false } },
      me
    )
    detailView().find('.menu-publish').first().should('be.disabled')
  })
})
