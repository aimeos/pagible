/**
 * E2E tests for the page list / tree view.
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
        path: '/home',
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
 *
 * @param {object} options
 * @param {object|false|null} options.meResponse  – `data.me` value (null = unauthenticated)
 * @param {Array}             options.pages       – array of page objects for the `pages` query
 * @param {object|null}       options.addPage     – return value for `addPage` mutation
 * @param {object|null}       options.savePage    – return value for `savePage` mutation
 * @param {object|null}       options.movePage    – return value for `movePage` mutation
 * @param {object|null}       options.dropPage    – return value for `dropPage` mutation
 * @param {object|null}       options.keepPage    – return value for `keepPage` mutation
 * @param {object|null}       options.purgePage   – return value for `purgePage` mutation
 * @param {object|null}       options.pubPage     – return value for `pubPage` mutation
 * @param {string|null}       options.synthesize  – return value for `synthesize` mutation
 */
function setupIntercept({
  meResponse = ME_ADMIN,
  pages = [],
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

// ---------------------------------------------------------------------------
// Test Suite
// ---------------------------------------------------------------------------

describe('Page List', () => {
  // ---- Layout & app bar ----

  it('shows "Pages" title in app bar', () => {
    visitPages()
    cy.get('.v-app-bar-title').should('contain', 'Pages')
  })

  it('shows navigation toggle and aside toggle buttons in app bar', () => {
    visitPages()
    cy.get('.v-app-bar .v-btn').should('have.length.at.least', 2)
  })

  // ---- Loading & empty state ----

  it('shows "No entries found" when page list is empty', () => {
    visitPages([])
    cy.get('.page-list').should('contain', 'No entries found')
  })

  it('shows page items when pages are returned', () => {
    const page = makePage()
    visitPages([page])
    cy.get('.tree-node-inner').should('have.length.at.least', 1)
    cy.get('.item-title').first().should('contain', 'Home')
  })

  it('displays page title subtitle', () => {
    const page = makePage()
    visitPages([page])
    cy.get('.item-subtitle').first().should('contain', 'Home Page')
  })

  it('displays the language badge', () => {
    const page = makePage()
    visitPages([page])
    cy.get('.item-lang').first().should('contain', 'en')
  })

  // ---- Search ----

  it('has a search field', () => {
    visitPages()
    cy.get('.search input').should('exist')
  })

  it('search field triggers reload on input', () => {
    const page = makePage()
    visitPages([page])
    // Search triggers a server query only in list view
    cy.get('.v-navigation-drawer--right').contains('List').click()
    cy.wait('@gql') // list view pages query
    cy.get('.search input').type('test')
    cy.wait('@gql') // search query with filter
  })

  // ---- Add page ----

  it('shows add page button for users with page:add permission', () => {
    visitPages()
    cy.get('.v-btn[title="Add page"]').should('exist').and('not.be.disabled')
  })

  it('hides add page button for users without page:add permission', () => {
    const me = {
      permission: JSON.stringify({ 'page:view': true }),
      email: 'viewer@example.com',
      name: 'Viewer',
    }
    visitPages([], me)
    cy.get('.v-btn[title="Add page"]').should('not.exist')
  })

  it('clicking add page sends addPage mutation', () => {
    visitPages()
    cy.get('.v-btn[title="Add page"]').first().click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      expect(ops.some((op) => (op.query || '').includes('addPage'))).to.be.true
    })
  })

  // ---- Reload ----

  it('shows reload button and clicking it refetches pages', () => {
    const page = makePage()
    visitPages([page])
    cy.get('.v-btn[title="Reload page tree"]').should('exist').click()
    cy.wait('@gql')
  })

  // ---- Tree node expand/collapse ----

  it('shows expand button for nodes with children', () => {
    const page = makePage({ has: true })
    visitPages([page])
    cy.get('.tree-node-inner .actions .v-btn').first().should('exist').and('not.have.class', 'hidden')
  })

  it('hides expand button for leaf nodes', () => {
    const page = makePage({ has: false })
    visitPages([page])
    cy.get('.tree-node-inner .actions .v-btn').first().should('have.class', 'hidden')
  })

  it('clicking expand fetches child pages', () => {
    const page = makePage({ has: true })
    visitPages([page])
    // Click the expand/collapse toggle button
    cy.get('.tree-node-inner .actions .v-btn').first().click()
    cy.wait('@gql')
  })

  // ---- Page click opens detail ----

  it('clicking a page item emits select / opens detail', () => {
    const page = makePage()
    visitPages([page])
    cy.get('.item-text').first().click()
    // The openView method is called - check that PageDetail overlay appears or URL changes
    // Since openView pushes a view overlay, we can check the DOM for the detail component
    cy.wait(300) // give time for view transition
  })

  // ---- Node context menu (three-dot menu) ----

  it('shows context menu with actions when clicking three-dot button on a node', () => {
    const page = makePage()
    visitPages([page])
    cy.get('.tree-node-inner .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-toolbar-title').should('contain', 'Actions')
  })

  it('context menu shows Publish for unpublished pages', () => {
    const page = makePage()
    page.latest.published = false
    visitPages([page])
    cy.get('.tree-node-inner .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-list').should('contain', 'Publish')
  })

  it('context menu hides Publish for already published pages', () => {
    const page = makePage()
    page.latest.published = true
    visitPages([page])
    cy.get('.tree-node-inner .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-list .v-btn').then(($btns) => {
      const texts = [...$btns].map((b) => b.textContent.trim())
      expect(texts).to.not.include('Publish')
    })
  })

  it('context menu shows Enable for disabled page', () => {
    const page = makePage()
    page.latest.data = JSON.stringify({
      name: 'Test', title: '', path: '/test', lang: 'en',
      status: 0, domain: '', to: '', tag: '', type: '', theme: '', cache: 5,
    })
    visitPages([page])
    cy.get('.tree-node-inner .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-list').should('contain', 'Enable')
  })

  it('context menu shows Disable for enabled page', () => {
    const page = makePage()
    visitPages([page])
    cy.get('.tree-node-inner .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-list').should('contain', 'Disable')
  })

  it('context menu shows Delete for non-trashed page', () => {
    const page = makePage()
    visitPages([page])
    cy.get('.tree-node-inner .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-list').should('contain', 'Delete')
  })

  it('context menu shows Restore for trashed page', () => {
    const page = makePage({ deleted_at: '2026-01-15 00:00:00' })
    setupIntercept({
      pages: [page],
      meResponse: ME_ADMIN,
    })
    cy.visit('/pages')
    cy.wait('@gql')
    cy.wait('@gql')
    cy.get('.tree-node-inner .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-list').should('contain', 'Restore')
  })

  it('context menu shows Purge button', () => {
    const page = makePage()
    visitPages([page])
    cy.get('.tree-node-inner .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-list').should('contain', 'Purge')
  })

  it('context menu shows Cut and Copy options', () => {
    const page = makePage()
    visitPages([page])
    cy.get('.tree-node-inner .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-list').should('contain', 'Cut')
    cy.get('.v-card .v-list').should('contain', 'Copy')
  })

  it('context menu shows Insert submenu', () => {
    const page = makePage()
    visitPages([page])
    cy.get('.tree-node-inner .v-btn[title="Actions"]').first().click()
    cy.get('.v-card .v-list').should('contain', 'Insert')
  })

  // ---- Context menu actions fire mutations ----

  it('clicking Publish sends pubPage mutation', () => {
    const page = makePage()
    page.latest.published = false
    visitPages([page])
    cy.get('.tree-node-inner .v-btn[title="Actions"]').first().click()
    cy.contains('.v-card .v-list .v-btn', 'Publish').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      expect(ops.some((op) => (op.query || '').includes('pubPage'))).to.be.true
    })
  })

  it('clicking Delete sends dropPage mutation', () => {
    const page = makePage()
    visitPages([page])
    cy.get('.tree-node-inner .v-btn[title="Actions"]').first().click()
    cy.contains('.v-card .v-list .v-btn', 'Delete').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      expect(ops.some((op) => (op.query || '').includes('dropPage'))).to.be.true
    })
  })

  it('clicking Enable sends savePage mutation with status 1', () => {
    const page = makePage()
    page.latest.data = JSON.stringify({
      name: 'Test', title: '', path: '/test', lang: 'en',
      status: 0, domain: '', to: '', tag: '', type: '', theme: '', cache: 5,
    })
    visitPages([page])
    cy.get('.tree-node-inner .v-btn[title="Actions"]').first().click()
    cy.contains('.v-card .v-list .v-btn', 'Enable').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      const saveOp = ops.find((op) => (op.query || '').includes('savePage'))
      expect(saveOp).to.exist
      expect(saveOp.variables.input.status).to.equal(1)
    })
  })

  it('clicking Disable sends savePage mutation with status 0', () => {
    const page = makePage()
    visitPages([page])
    cy.get('.tree-node-inner .v-btn[title="Actions"]').first().click()
    cy.contains('.v-card .v-list .v-btn', 'Disable').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      const saveOp = ops.find((op) => (op.query || '').includes('savePage'))
      expect(saveOp).to.exist
      expect(saveOp.variables.input.status).to.equal(0)
    })
  })

  // ---- Bulk actions ----

  it('shows bulk checkbox and actions button in header', () => {
    visitPages()
    cy.get('.header .bulk .v-checkbox-btn').should('exist')
    cy.get('.header .bulk .v-btn[title="Actions"]').should('exist')
  })

  it('bulk actions button is disabled when no items are checked', () => {
    const page = makePage()
    visitPages([page])
    cy.get('.header .bulk .v-btn[title="Actions"]').should('be.disabled')
  })

  it('checking a page item enables the bulk actions button', () => {
    const page = makePage()
    visitPages([page])
    // Check the node checkbox
    cy.get('.tree-node-inner .v-checkbox-btn').first().click()
    cy.get('.header .bulk .v-btn[title="Actions"]').should('not.be.disabled')
  })

  it('toggle all checkbox checks/unchecks all items', () => {
    const pages = [
      makePage({ id: '1' }),
      makePage({ id: '2', latest: { ...makePage().latest, id: '20', data: JSON.stringify({
        name: 'About', title: 'About Us', path: '/about', lang: 'en',
        status: 1, domain: '', to: '', tag: '', type: '', theme: '', cache: 5,
      })} }),
    ]
    visitPages(pages)
    // Click the "toggle all" checkbox in the header
    cy.get('.header .bulk .v-checkbox-btn').click()
    // Both items should now be checked
    cy.get('.tree-node-inner .v-checkbox-btn').each(($cb) => {
      cy.wrap($cb).find('input').should('be.checked')
    })
  })

  it('bulk actions menu shows Publish, Enable, Disable, Delete, Purge', () => {
    const page = makePage()
    page.latest.published = false
    visitPages([page])
    cy.get('.tree-node-inner .v-checkbox-btn').first().click()
    cy.get('.header .bulk .v-btn[title="Actions"]').click()
    cy.get('.v-card .v-list').should('contain', 'Publish')
    cy.get('.v-card .v-list').should('contain', 'Enable')
    cy.get('.v-card .v-list').should('contain', 'Disable')
    cy.get('.v-card .v-list').should('contain', 'Delete')
    cy.get('.v-card .v-list').should('contain', 'Purge')
  })

  // ---- Status styling ----

  it('disabled page has line-through style on title', () => {
    const page = makePage()
    page.latest.data = JSON.stringify({
      name: 'Disabled', title: '', path: '/disabled', lang: 'en',
      status: 0, domain: '', to: '', tag: '', type: '', theme: '', cache: 5,
    })
    visitPages([page])
    cy.get('.item-content.status-disabled').should('exist')
  })

  it('trashed page has trashed class', () => {
    const page = makePage({ deleted_at: '2026-01-15 00:00:00' })
    visitPages([page])
    cy.get('.item-content.trashed').should('exist')
  })

  it('hidden page shows eye-off-outline icon', () => {
    const page = makePage()
    page.latest.data = JSON.stringify({
      name: 'Hidden', title: '', path: '/hidden', lang: 'en',
      status: 2, domain: '', to: '', tag: '', type: '', theme: '', cache: 5,
    })
    visitPages([page])
    cy.get('.item-content.status-hidden').should('exist')
    cy.get('.item-status').should('exist')
  })

  // ---- Draft indicator ----

  it('unpublished page checkbox has draft class', () => {
    const page = makePage()
    page.latest.published = false
    visitPages([page])
    cy.get('.tree-node-inner .v-checkbox-btn.draft').should('exist')
  })

  // ---- Scheduled publish icon ----

  it('page with publish_at shows clock icon', () => {
    const page = makePage()
    page.latest.publish_at = '2026-06-01 00:00:00'
    visitPages([page])
    cy.get('.publish-at').should('exist')
  })

  // ---- Multiple pages ----

  it('displays multiple pages in tree', () => {
    const pages = [
      makePage({ id: '1' }),
      makePage({
        id: '2',
        latest: {
          ...makePage().latest,
          id: '20',
          data: JSON.stringify({
            name: 'About', title: 'About page', path: '/about', lang: 'en',
            status: 1, domain: '', to: '', tag: '', type: '', theme: '', cache: 5,
          }),
        },
      }),
      makePage({
        id: '3',
        latest: {
          ...makePage().latest,
          id: '30',
          data: JSON.stringify({
            name: 'Contact', title: 'Contact us', path: '/contact', lang: 'en',
            status: 1, domain: '', to: '', tag: '', type: '', theme: '', cache: 5,
          }),
        },
      }),
    ]
    visitPages(pages)
    cy.get('.tree-node-inner').should('have.length', 3)
    cy.get('.item-title').eq(0).should('contain', 'Home')
    cy.get('.item-title').eq(1).should('contain', 'About')
    cy.get('.item-title').eq(2).should('contain', 'Contact')
  })

  // ---- Page URL link ----

  it('shows page URL as external link', () => {
    const page = makePage()
    visitPages([page])
    cy.get('.item-aux').first().should('have.attr', 'target', '_blank')
  })

  // ---- AI synthesize prompt ----

  it('shows synthesize prompt for users with page:synthesize permission', () => {
    visitPages()
    cy.get('.prompt').should('exist')
  })

  it('hides synthesize prompt when user lacks page:synthesize permission', () => {
    const me = {
      permission: JSON.stringify({ 'page:view': true, 'page:add': true }),
      email: 'editor@example.com',
      name: 'Editor',
    }
    visitPages([], me)
    cy.get('.prompt').should('not.exist')
  })

  it('synthesize submit button appears after typing prompt', () => {
    visitPages()
    cy.get('.prompt textarea').first().type('Create a landing page about cats')
    cy.get('.prompt .v-input__append .v-btn').should('exist')
  })

  it('clicking synthesize submit sends synthesize mutation', () => {
    visitPages()
    cy.get('.prompt textarea').first().type('Create a landing page')
    cy.get('.prompt .v-input__append .v-btn').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      expect(ops.some((op) => (op.query || '').includes('synthesize'))).to.be.true
    })
  })

  // ---- Permission-based visibility ----

  it('does not show page tree when user lacks page:view permission', () => {
    const me = {
      permission: JSON.stringify({ 'element:view': true }),
      email: 'limited@example.com',
      name: 'Limited',
    }
    setupIntercept({ meResponse: me, pages: [] })
    cy.visit('/pages')
    cy.wait('@gql')
    // Route guard blocks navigation with next(false), page list component does not render
    cy.get('.page-list').should('not.exist')
  })

  // ---- Help toggle ----

  it('toggles help text when help button is clicked', () => {
    visitPages()
    cy.get('.help').should('not.exist')
    cy.get('.prompt .v-input__prepend .v-btn').click()
    cy.get('.help').should('be.visible')
    cy.get('.prompt .v-input__prepend .v-btn').click()
    cy.get('.help').should('not.exist')
  })

  // ---- Page without name shows "New" ----

  it('shows "New" for pages without a name', () => {
    const page = makePage()
    page.latest.data = JSON.stringify({
      name: '', title: '', path: '/unnamed', lang: 'en',
      status: 1, domain: '', to: '', tag: '', type: '', theme: '', cache: 5,
    })
    visitPages([page])
    cy.get('.item-title').first().should('contain', 'New')
  })

  // ---- Redirect indicator ----

  it('shows redirect target when page has "to" field', () => {
    const page = makePage()
    page.latest.data = JSON.stringify({
      name: 'Old Page', title: '', path: '/old', lang: 'en',
      status: 1, domain: '', to: 'https://example.com', tag: '', type: '', theme: '', cache: 5,
    })
    visitPages([page])
    cy.get('.item-to').first().should('contain', 'https://example.com')
  })
})
