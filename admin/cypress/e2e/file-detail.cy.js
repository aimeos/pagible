/**
 * E2E tests for the file detail view.
 *
 * The FileDetail component is mounted directly at /files/:id so these tests
 * cover detail behavior independently of the file list.
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
  'file:relocate': true,
  'file:describe': true,
  'audio:transcribe': true,
  'text:translate': true,
  'page:access': true,
  'page:save': true,
  'page:view': true,
  'element:view': true,
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

/**
 * A minimal file entry as the GraphQL `files` query would return. File identity
 * and paths live in latest.data; description/transcription live in latest.aux.
 */
function makeFile(overrides = {}) {
  return Object.assign({
    __typename: 'File',
    disk: 'public',
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
      }),
      aux: JSON.stringify({
        description: { en: 'A test image' },
        transcription: {},
      }),
      editor: 'admin@example.com',
      created_at: '2026-01-01 00:00:00',
    },
  }, overrides)
}

/** Full referenced page response shape for the `page(id)` query. */
function makePageDetail(overrides = {}) {
  return Object.assign({
    id: 'page-version-1',
    aux: JSON.stringify({
      content: [{ id: 'heading-1', type: 'heading', group: 'main', data: { title: 'Welcome' } }],
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
    elements: [],
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
  fileRefs = null,
  pageDetail = null,
  versions = [],
  saveFile = null,
  pubFile = null,
  schemas = ELEMENT_SCHEMAS,
} = {}) {
  cy.intercept('POST', '/graphql', (req) => {
    const isBatch = Array.isArray(req.body)
    const ops = isBatch ? req.body : [req.body]

    const mutation = ['pubFile', 'saveFile']
      .find((name) => ops.some((op) => (op.query || '').includes(name)))

    if (mutation) {
      req.alias = mutation
    } else if (ops.some((op) => /\bfile\s*\(/.test(op.query || '') && (op.query || '').includes('latest'))) {
      req.alias = 'fileDetail'
    }

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
              disk: 'public',
              id: op.variables?.id || '1',
              latest: {
                id: '11',
                data: '{}',
                aux: '{}',
                published: false,
                publish_at: null,
                editor: 'admin@example.com',
                created_at: '2026-01-02 00:00:00',
              },
              changed: null,
            },
          },
        }
      }
      if (query.includes('pubFile')) {
        return { data: { pubFile: pubFile || { id: '1' } } }
      }
      if (query.includes('schemas')) {
        return { data: { schemas } }
      }
      if (query.includes('bypages') || query.includes('byelements')) {
        return { data: { file: fileRefs } }
      }
      // Single file query for detail view — check BEFORE 'files('
      if (query.includes('file(') && !query.includes('files(')) {
        const f = files[0] || makeFile()
        return {
          data: {
            file: {
              id: op.variables?.id || f.id,
              latest: f.latest,
              bypages: [],
              byelements: [],
              byversions: [],
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
 * Navigate directly to /files/:id and wait for the detail data query.
 */
function visitFileDetail(fileOverrides = {}, meResponse = ME_ADMIN) {
  const file = makeFile(fileOverrides)
  const data = JSON.parse(file.latest.data)
  setupIntercept({ meResponse, files: [file] })
  cy.visit(`/files/${file.id}`)
  cy.wait('@fileDetail', { requestTimeout: 20000 })
  cy.url().should('include', '/files/')
  cy.get('.file-details').should('be.visible')
  cy.get('.v-app-bar-title').last().should('contain', 'File: ' + data.name)
}

/** Shorthand to scope selectors to the detail view. */
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
    detailView().find('.v-btn.btn-back').click()
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
    cy.wait('@saveFile')
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
    cy.wait('@pubFile')
  })

  // ---- History ----

  it('shows history button', () => {
    visitFileDetail()
    detailView().find('.v-btn.btn-history').should('exist')
  })

  it('clicking history button opens history dialog', () => {
    visitFileDetail({ latest: { ...makeFile().latest, published: false } })
    detailView().find('.v-btn.btn-history').click()
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

  it('shows content added to a referenced page opened from the shared file', () => {
    const file = makeFile()

    setupIntercept({
      files: [file],
      fileRefs: {
        id: file.id,
        bypages: [{ id: 'page-1', path: 'referenced-page', name: 'Referenced page', restricted: false }],
        byelements: [],
        byversions: [],
      },
      pageDetail: makePageDetail(),
    })

    cy.visit(`/files/${file.id}`)
    cy.wait('@fileDetail', { requestTimeout: 20000 })
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
