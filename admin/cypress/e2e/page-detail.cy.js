/**
 * E2E tests for the page detail stacked view.
 *
 * The PageDetail component opens as an overlay on top of the page list
 * when a page item is clicked. Both views coexist in the DOM (stacked
 * via z-index), so selectors must be scoped to the detail's .view container.
 *
 * GraphQL is intercepted at POST /graphql. Apollo's BatchHttpLink sends
 * requests as JSON arrays, so the handler checks whether req.body is an
 * array (batched) or an object (single) and replies in the same shape.
 */

Cypress.on('uncaught:exception', (err) => {
  if (err.message.includes('ResizeObserver')) return false
})

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
  'page:metrics': true,
  'text:translate': true,
  'text:write': true,
  'audio:transcribe': true,
  'element:view': true,
  'file:view': true,
  'page:config': true,
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

/** Full page detail response shape for the `page(id)` query. */
function makePageDetail(overrides = {}) {
  return Object.assign({
    id: '10',
    aux: JSON.stringify({
      content: [
        { id: 'c1', type: 'heading', group: 'main', data: { title: 'Welcome' }, files: [] },
        { id: 'c2', type: 'text', group: 'main', data: { text: 'Hello world' }, files: [] },
      ],
      meta: {},
      config: {},
    }),
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
    published: true,
    publish_at: null,
    created_at: '2026-01-01 00:00:00',
    editor: 'admin@example.com',
    files: [],
    elements: [],
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
  pageDetail = null,
  pageVersions = [],
  addPage = null,
  savePage = null,
  movePage = null,
  dropPage = null,
  keepPage = null,
  purgePage = null,
  pubPage = null,
  synthesize = null,
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
      if (query.includes('addPage')) {
        return { data: { addPage: addPage || { id: '99' } } }
      }
      if (query.includes('savePage')) {
        return { data: { savePage: savePage || { id: op.variables?.id || '1' } } }
      }
      if (query.includes('movePage')) {
        return { data: { movePage: movePage || { id: op.variables?.id || '1' } } }
      }
      if (query.includes('dropPage')) {
        return { data: { dropPage: dropPage || { id: '1' } } }
      }
      if (query.includes('keepPage')) {
        return { data: { keepPage: keepPage || { id: '1' } } }
      }
      if (query.includes('purgePage')) {
        return { data: { purgePage: purgePage || { id: '1' } } }
      }
      if (query.includes('pubPage')) {
        return { data: { pubPage: pubPage || { id: '1' } } }
      }
      if (query.includes('synthesize')) {
        return { data: { synthesize: synthesize || 'Generated page content' } }
      }
      // Versions query (contains both 'versions' and 'page(')
      if (query.includes('versions')) {
        return { data: { page: { id: op.variables?.id || '1', versions: pageVersions } } }
      }
      // Single page query for detail view — must check BEFORE 'pages'
      if (query.includes('page(') && !query.includes('pages(')) {
        return {
          data: {
            page: pageDetail
              ? { id: op.variables?.id || '1', latest: pageDetail }
              : null,
          },
        }
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

/**
 * Navigate to /pages, wait for initial queries, click a page to open the
 * detail overlay, and wait for the detail data query.
 */
function visitPageDetail(pageOverrides = {}, detailOverrides = {}, meResponse = ME_ADMIN) {
  const page = makePage(pageOverrides)
  const detail = makePageDetail(detailOverrides)
  setupIntercept({ meResponse, pages: [page], pageDetail: detail })
  cy.visit('/pages')
  cy.wait('@gql') // me query
  cy.wait('@gql') // pages query
  cy.get('.item-text').first().click()
  cy.wait('@gql') // page(id) detail query
  cy.get('.page-details').should('be.visible')
}

/** Shorthand to scope selectors to the detail view (topmost stacked view). */
function detailView() {
  return cy.get('.view').last()
}

// ---------------------------------------------------------------------------
// Test Suite
// ---------------------------------------------------------------------------

describe('Page Detail', () => {
  // ---- App Bar ----

  it('shows "Page: {name}" title in detail app bar', () => {
    visitPageDetail()
    detailView().find('.v-app-bar-title').should('contain', 'Page: Home')
  })

  it('back button closes the detail view', () => {
    visitPageDetail()
    detailView().find('.v-btn[title="Back to list view"]').click()
    cy.get('.page-details').should('not.exist')
  })

  it('shows save button disabled when no changes are made', () => {
    visitPageDetail()
    detailView().find('.menu-save').should('be.disabled')
  })

  it('shows publish button disabled when page is already published with no changes', () => {
    visitPageDetail({}, { published: true })
    detailView().find('.menu-publish').should('be.disabled')
  })

  it('shows publish button enabled when page is unpublished', () => {
    visitPageDetail({ latest: { ...makePage().latest, published: false } }, { published: false })
    detailView().find('.menu-publish').should('have.class', 'active')
  })

  it('shows schedule publish button', () => {
    visitPageDetail()
    detailView().find('.menu-publish').should('exist')
  })

  it('shows history button', () => {
    visitPageDetail()
    detailView().find('.v-btn[title="View history"]').should('exist')
  })

  it('shows translate button when user has text:translate permission', () => {
    visitPageDetail()
    detailView().find('.v-btn[title="Translate page"]').should('exist')
  })

  it('hides translate button when user lacks text:translate permission', () => {
    const me = {
      permission: JSON.stringify({
        'page:view': true,
        'page:save': true,
        'page:publish': true,
      }),
      email: 'editor@example.com',
      name: 'Editor',
    }
    visitPageDetail({}, {}, me)
    detailView().find('.v-btn[title="Translate page"]').should('not.exist')
  })

  it('shows aside toggle button', () => {
    visitPageDetail()
    detailView().find('.v-btn[title="Toggle side menu"]').should('exist')
  })

  // ---- Tabs ----

  it('shows Editor, Content, and Page tabs', () => {
    visitPageDetail()
    detailView().find('.v-tab').should('have.length.at.least', 3)
    detailView().find('.v-tab').eq(0).should('contain', 'Editor')
    detailView().find('.v-tab').eq(1).should('contain', 'Content')
    detailView().find('.v-tab').eq(2).should('contain', 'Page')
  })

  it('shows Metrics tab when user has page:metrics permission', () => {
    visitPageDetail()
    detailView().find('.v-tab').should('have.length', 4)
    detailView().find('.v-tab').eq(3).should('contain', 'Metrics')
  })

  it('hides Metrics tab when user lacks page:metrics permission', () => {
    const perms = { ...ALL_PERMISSIONS }
    delete perms['page:metrics']
    const me = {
      permission: JSON.stringify(perms),
      email: 'admin@example.com',
      name: 'Admin',
    }
    visitPageDetail({}, {}, me)
    detailView().find('.v-tab').should('have.length', 3)
  })

  it('clicking Content tab switches to content view', () => {
    visitPageDetail()
    detailView().find('.v-tab').contains('Content').click()
    // Content tab should now be active
    detailView().find('.v-tab--selected').should('contain', 'Content')
  })

  it('clicking Page tab shows Detail, Meta, Config sub-tabs', () => {
    visitPageDetail()
    detailView().find('.v-tab').contains('Page').click()
    detailView().find('.subtabs .v-tab').should('have.length', 3)
    detailView().find('.subtabs .v-tab').eq(0).should('contain', 'Detail')
    detailView().find('.subtabs .v-tab').eq(1).should('contain', 'Meta')
    detailView().find('.subtabs .v-tab').eq(2).should('contain', 'Config')
  })

  // ---- Save ----

  it('save button sends savePage mutation after making changes', () => {
    visitPageDetail({}, { published: true })
    // Switch to Page tab and modify a field
    detailView().find('.v-tab').contains('Page').click()
    detailView().find('input[maxlength="30"]').first().clear().type('Updated Name')
    // Save button should now be enabled
    detailView().find('.menu-save').should('not.be.disabled').click()
    // Wait for the savePage mutation, skipping any intermediate checkPath queries
    function waitForSavePage() {
      return cy.wait('@gql').then((interception) => {
        const body = interception.request.body
        const ops = Array.isArray(body) ? body : [body]
        if (ops.some((op) => (op.query || '').includes('savePage'))) {
          return
        }
        return waitForSavePage()
      })
    }
    waitForSavePage()
  })

  // ---- Publish ----

  it('clicking publish fires pubPage mutation for unpublished page', () => {
    visitPageDetail({ latest: { ...makePage().latest, published: false } }, { published: false })
    detailView().find('.menu-publish').last().click()
    // Wait for the pubPage mutation, skipping any intermediate queries
    function waitForPubPage() {
      return cy.wait('@gql').then((interception) => {
        const body = interception.request.body
        const ops = Array.isArray(body) ? body : [body]
        if (ops.some((op) => (op.query || '').includes('pubPage'))) {
          return
        }
        return waitForPubPage()
      })
    }
    waitForPubPage()
  })

  it('schedule publish button opens date picker', () => {
    visitPageDetail({ latest: { ...makePage().latest, published: false } }, { published: false })
    detailView().find('.menu-publishat').click()
    cy.get('.v-date-picker').should('be.visible')
  })

  // ---- History ----

  it('clicking history button opens history dialog', () => {
    visitPageDetail({}, { published: false })
    detailView().find('.v-btn[title="View history"]').click()
    cy.get('.v-dialog').should('be.visible')
  })

  // ---- Page Tab - Detail Sub-tab fields ----

  it('Detail sub-tab shows Status, Language, Page title, Page name, URL path, and Cache time fields', () => {
    visitPageDetail()
    detailView().find('.v-tab').contains('Page').click()
    cy.get('.page-details').should('contain', 'Status')
    cy.get('.page-details').should('contain', 'Language')
    cy.get('.page-details').should('contain', 'Page title')
    cy.get('.page-details').should('contain', 'Page name')
    cy.get('.page-details').should('contain', 'URL path')
    cy.get('.page-details').should('contain', 'Cache time')
  })

  it('Detail sub-tab shows Redirect URL field', () => {
    visitPageDetail()
    detailView().find('.v-tab').contains('Page').click()
    cy.get('.page-details').should('contain', 'Redirect URL')
  })

  it('Detail sub-tab shows Theme and Page type selectors', () => {
    visitPageDetail()
    detailView().find('.v-tab').contains('Page').click()
    cy.get('.page-details').should('contain', 'Theme')
    cy.get('.page-details').should('contain', 'Page type')
  })

  it('Detail sub-tab shows Page tag field', () => {
    visitPageDetail()
    detailView().find('.v-tab').contains('Page').click()
    cy.get('.page-details').should('contain', 'Page tag')
  })

  // ---- Permission-based behavior ----

  it('save button is disabled without page:save permission', () => {
    const me = {
      permission: JSON.stringify({
        'page:view': true,
        'page:publish': true,
      }),
      email: 'viewer@example.com',
      name: 'Viewer',
    }
    visitPageDetail({}, { published: false }, me)
    detailView().find('.menu-save').should('be.disabled')
  })

  it('publish button is disabled without page:publish permission', () => {
    const me = {
      permission: JSON.stringify({
        'page:view': true,
        'page:save': true,
      }),
      email: 'editor@example.com',
      name: 'Editor',
    }
    visitPageDetail({}, { published: false }, me)
    detailView().find('.menu-publish').should('be.disabled')
  })
})
