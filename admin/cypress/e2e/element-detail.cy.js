/**
 * E2E tests for the element detail view.
 *
 * The ElementDetail component opens through its Vue Router route
 * (/elements/:id) and is replaced by the list view when navigating back.
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
  'page:access': true,
  'page:save': true,
  'page:view': true,
  'file:view': true,
}

const ME_ADMIN = {
  permission: JSON.stringify(ALL_PERMISSIONS),
  email: 'admin@example.com',
  name: 'Admin',
}

const ELEMENT_SCHEMAS = [{
  name: 'cms',
  label: 'CMS',
  types: '{}',
  content: JSON.stringify({
    heading: { group: 'basic', fields: { title: { type: 'string' } } },
    contact: { group: 'forms', fields: { title: { type: 'string' } } },
  }),
  meta: '{}',
  config: '{}',
}, {
  name: 'pagible',
  label: 'Pagible',
  types: JSON.stringify({ page: { sections: ['main', 'footer'] } }),
  content: '{}',
  meta: '{}',
  config: '{}',
}]

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
      data: JSON.stringify({ name: 'Hero Banner', type: 'heading', lang: 'en', data: {} }),
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
      data: JSON.stringify({ name: 'Hero Banner', type: 'heading', lang: 'en', data: {} }),
      editor: 'admin@example.com',
      created_at: '2026-01-01 00:00:00',
      files: [],
    },
  }, overrides)
}

/** Full referenced page response shape for the `page(id)` query. */
function makePageDetail(overrides = {}) {
  return Object.assign({
    id: 'page-version-1',
    aux: JSON.stringify({
      content: [{ id: 'ref-1', type: 'reference', group: 'main', refid: '1' }],
      meta: {},
      config: {},
    }),
    data: JSON.stringify({
      name: 'Referenced page',
      title: 'Referenced page',
      path: 'referenced-page',
      lang: 'en',
      status: 1,
      domain: '',
      to: '',
      tag: '',
      type: 'page',
      theme: 'pagible',
      cache: 5,
    }),
    published: true,
    publish_at: null,
    created_at: '2026-01-01 00:00:00',
    editor: 'admin@example.com',
    files: [],
    elements: [{
      id: '1',
      type: 'heading',
      name: 'Hero Banner',
      data: JSON.stringify({ title: 'Welcome' }),
      editor: 'admin@example.com',
      updated_at: '2026-01-01 00:00:00',
      files: [],
    }],
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
  elementRefs = null,
  pageDetail = null,
  versions = [],
  saveElement = null,
  pubElement = null,
  schemas = ELEMENT_SCHEMAS,
} = {}) {
  cy.intercept('POST', '/graphql', (req) => {
    const isBatch = Array.isArray(req.body)
    const ops = isBatch ? req.body : [req.body]

    if (ops.some((op) => /\belement\s*\(/.test(op.query || '') && !/\belements\s*\(/.test(op.query || ''))) {
      req.alias = 'elementDetail'
    }

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
      if (query.includes('schemas')) {
        return { data: { schemas } }
      }
      if (query.includes('bypages') || query.includes('byversions')) {
        return { data: { element: elementRefs } }
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
      if (query.includes('page(') && !query.includes('pages(')) {
        return {
          data: {
            page: pageDetail ? {
              id: op.variables?.id || 'page-1',
              access: [],
              has: 0,
              restricted: false,
              latest: pageDetail,
            } : null,
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
              settings: meResponse.settings || '{}',
              token: meResponse.token || null,
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
 * Navigate to /elements/:id and wait for the detail data query.
 */
function visitElementDetail(elementOverrides = {}, detailOverrides = {}, meResponse = ME_ADMIN) {
  const element = makeElement(elementOverrides)
  const detail = makeElementDetail(detailOverrides)
  setupIntercept({ meResponse, elementDetail: detail })
  cy.visit(`/elements/${element.id}`)
  cy.wait('@elementDetail', { requestTimeout: 20000 })
  cy.url().should('include', '/elements/')
  cy.get('.element-details', { timeout: 10000 }).should('be.visible')
}

/** Shorthand to scope selectors to the detail view. */
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
    detailView().find('.v-btn.btn-back').click()
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
    detailView().find('.v-btn.btn-history').should('exist')
  })

  it('clicking history button opens history dialog', () => {
    visitElementDetail({}, { latest: { ...makeElementDetail().latest, published: false } })
    detailView().find('.v-btn.btn-history').click()
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

  it('loads fields for a shared contact element', () => {
    const data = JSON.stringify({
      name: 'Contact form',
      type: 'contact',
      lang: 'en',
      data: { title: 'Contact us' },
    })

    visitElementDetail(
      {
        name: 'Contact form',
        type: 'contact',
        latest: { ...makeElement().latest, data },
      },
      { latest: { ...makeElementDetail().latest, data } }
    )

    detailView().find('.label').should('contain', 'title')
    detailView().find('textarea').should('have.value', 'Contact us')
  })

  it('shows content added to a referenced page opened from the shared element', () => {
    const element = makeElement()
    const detail = makeElementDetail()
    const page = makePageDetail()

    setupIntercept({
      elementDetail: detail,
      elementRefs: {
        id: element.id,
        bypages: [{ id: 'page-1', path: 'referenced-page', name: 'Referenced page', restricted: false }],
        byversions: [],
      },
      pageDetail: page,
    })

    cy.visit(`/elements/${element.id}`)
    cy.wait('@elementDetail', { requestTimeout: 20000 })
    detailView().find('.v-tab').contains('Used by').click()
    detailView().find('.v-table.pages tbody tr').click()

    detailView().find('.page-details').should('be.visible')
    detailView().find('.v-tab').contains('Content').click()
    detailView().find('.content').should('have.length', 1)
    detailView().find('button.btn-add').click()
    cy.get('.v-dialog').contains('button', 'heading').click()

    detailView().find('.content').should('have.length', 2)
    detailView().find('.menu-save').should('not.be.disabled')
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
