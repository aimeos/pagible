/**
 * E2E tests for the file detail stacked view.
 *
 * The FileDetail component opens as an overlay on top of the file list
 * when a file item is clicked. Both views coexist in the DOM (stacked
 * via z-index), so selectors must be scoped to the detail's .view container.
 *
 * Unlike pages/elements, FileDetail does NOT fetch additional data on open —
 * the item object from the list is passed directly. So no extra GQL wait
 * is needed after clicking an item.
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
  'file:describe': true,
  'audio:transcribe': true,
  'text:translate': true,
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
  versions = [],
  saveFile = null,
  pubFile = null,
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
      if (query.includes('saveFile')) {
        return {
          data: {
            saveFile: saveFile || {
              id: op.variables?.id || '1',
              latest: { id: '11', data: '{}', created_at: '2026-01-02 00:00:00' },
            },
          },
        }
      }
      if (query.includes('pubFile')) {
        return { data: { pubFile: pubFile || { id: '1' } } }
      }
      // Files list query — check BEFORE versions because the files query
      // contains `byversions_count` which matches `query.includes('versions')`
      if (query.includes('files(')) {
        return { data: filesResponse(files) }
      }
      // Versions query (file detail history)
      if (query.includes('versions')) {
        return { data: { file: { id: op.variables?.id || '1', versions: versions } } }
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
 * Navigate to /files, wait for initial queries, click a file to open
 * the detail overlay. FileDetail does NOT issue an additional query.
 */
function visitFileDetail(fileOverrides = {}, meResponse = ME_ADMIN) {
  const file = makeFile(fileOverrides)
  setupIntercept({ meResponse, files: [file] })
  cy.visit('/files')
  cy.wait('@gql') // me query
  cy.wait('@gql') // files query
  cy.get('.items .v-list-item').should('have.length.at.least', 1)
  cy.get('.item-text').first().click()
  cy.get('.file-details').should('be.visible')
}

/** Shorthand to scope selectors to the detail view (topmost stacked view). */
function detailView() {
  return cy.get('.view').last()
}

// ---------------------------------------------------------------------------
// Test Suite
// ---------------------------------------------------------------------------

describe('File Detail', () => {
  // ---- App Bar ----

  it('shows "File: {name}" title in detail app bar', () => {
    visitFileDetail()
    detailView().find('.v-app-bar-title').should('contain', 'File: test.png')
  })

  it('back button closes the detail view', () => {
    visitFileDetail()
    detailView().find('.v-btn[title="Back to list view"]').click()
    cy.get('.file-details').should('not.exist')
  })

  // ---- Save ----

  it('shows save button disabled when no changes are made', () => {
    visitFileDetail()
    detailView().find('.menu-save').should('be.disabled')
  })

  it('save button enables after changing the name field', () => {
    visitFileDetail()
    detailView().find('input[maxlength="255"]').first().clear().type('renamed.png')
    detailView().find('.menu-save').should('not.be.disabled')
  })

  it('save button sends saveFile mutation after making changes', () => {
    visitFileDetail()
    detailView().find('input[maxlength="255"]').first().clear().type('renamed.png')
    detailView().find('.menu-save').click()
    function waitForSaveFile() {
      return cy.wait('@gql').then((interception) => {
        const body = interception.request.body
        const ops = Array.isArray(body) ? body : [body]
        if (ops.some((op) => (op.query || '').includes('saveFile'))) {
          return
        }
        return waitForSaveFile()
      })
    }
    waitForSaveFile()
  })

  // ---- Publish ----

  it('shows publish button disabled when file is already published with no changes', () => {
    visitFileDetail()
    detailView().find('.menu-publish').first().should('be.disabled')
  })

  it('shows publish button enabled when file is unpublished', () => {
    visitFileDetail({ latest: { ...makeFile().latest, published: false } })
    detailView().find('.menu-publish').last().should('not.be.disabled')
  })

  it('clicking publish fires pubFile mutation for unpublished file', () => {
    visitFileDetail({ latest: { ...makeFile().latest, published: false } })
    detailView().find('.menu-publish').last().click()
    function waitForPubFile() {
      return cy.wait('@gql').then((interception) => {
        const body = interception.request.body
        const ops = Array.isArray(body) ? body : [body]
        if (ops.some((op) => (op.query || '').includes('pubFile'))) {
          return
        }
        return waitForPubFile()
      })
    }
    waitForPubFile()
  })

  // ---- History ----

  it('shows history button', () => {
    visitFileDetail()
    detailView().find('.v-btn[title="View history"]').should('exist')
  })

  it('clicking history button opens history dialog', () => {
    visitFileDetail({ latest: { ...makeFile().latest, published: false } })
    detailView().find('.v-btn[title="View history"]').click()
    cy.get('.v-dialog').should('be.visible')
  })

  // ---- Tabs ----

  it('shows File and Used by tabs', () => {
    visitFileDetail()
    cy.get('.file-details > .v-form > .v-tabs .v-tab').should('have.length', 2)
    cy.get('.file-details > .v-form > .v-tabs .v-tab').eq(0).should('contain', 'File')
    cy.get('.file-details > .v-form > .v-tabs .v-tab').eq(1).should('contain', 'Used by')
  })

  it('clicking Used by tab switches tab', () => {
    visitFileDetail()
    cy.get('.file-details > .v-form > .v-tabs').contains('.v-tab', 'Used by').click()
    cy.get('.file-details > .v-form > .v-tabs .v-tab--selected').should('contain', 'Used by')
  })

  // ---- Detail fields ----

  it('shows Name and Language fields on File tab', () => {
    visitFileDetail()
    cy.get('.file-details').should('contain', 'Name')
    cy.get('.file-details').should('contain', 'Language')
  })

  // ---- Permission-based behavior ----

  it('save button is disabled without file:save permission', () => {
    const me = {
      permission: JSON.stringify({
        'file:view': true,
        'file:publish': true,
      }),
      email: 'viewer@example.com',
      name: 'Viewer',
    }
    visitFileDetail({}, me)
    detailView().find('.menu-save').should('be.disabled')
  })

  it('publish button is disabled without file:publish permission', () => {
    const me = {
      permission: JSON.stringify({
        'file:view': true,
        'file:save': true,
      }),
      email: 'editor@example.com',
      name: 'Editor',
    }
    visitFileDetail({ latest: { ...makeFile().latest, published: false } }, me)
    detailView().find('.menu-publish').first().should('be.disabled')
  })
})
