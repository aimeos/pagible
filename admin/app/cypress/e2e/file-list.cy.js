/**
 * E2E tests for the file list view at /files.
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
  'image:erase': true,
  'image:inpaint': true,
  'image:isolate': true,
  'image:repaint': true,
  'image:uncrop': true,
  'image:upscale': true,
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
 *
 * The list transform (FileListItems) parses `latest.data` JSON as the base item,
 * then overrides id, deleted_at, timestamps, editor, published, publish_at, usage.
 * So name/lang/mime/path/previews/description/transcription must be in latest.data.
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
  dropFile = null,
  keepFile = null,
  purgeFile = null,
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
      if (query.includes('dropFile')) {
        return { data: { dropFile: dropFile || { id: '1' } } }
      }
      if (query.includes('keepFile')) {
        return { data: { keepFile: keepFile || { id: '1' } } }
      }
      if (query.includes('purgeFile')) {
        return { data: { purgeFile: purgeFile || { id: '1' } } }
      }
      if (query.includes('pubFile')) {
        return { data: { pubFile: pubFile || { id: '1' } } }
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

// ---------------------------------------------------------------------------
// Test Suite
// ---------------------------------------------------------------------------

describe('File List', () => {
  // ---- Layout & app bar ----

  it('shows "Files" title in app bar', () => {
    visitFiles()
    cy.get('.v-app-bar-title').should('contain', 'Files')
  })

  it('shows navigation toggle and aside toggle buttons in app bar', () => {
    visitFiles()
    cy.get('.v-app-bar .v-btn').should('have.length.at.least', 2)
  })

  // ---- Loading & empty state ----

  it('shows "No entries found" when file list is empty', () => {
    visitFiles([])
    cy.get('.file-list').should('contain', 'No entries found')
  })

  it('shows file items when files are returned', () => {
    const file = makeFile()
    visitFiles([file])
    cy.get('.items .v-list-item').should('have.length.at.least', 1)
    cy.get('.item-title').first().should('contain', 'test.png')
  })

  it('displays MIME type as subtitle', () => {
    const file = makeFile()
    visitFiles([file])
    cy.get('.item-mime').first().should('contain', 'image/png')
  })

  it('displays the language badge', () => {
    const file = makeFile()
    visitFiles([file])
    cy.get('.item-lang').first().should('contain', 'en')
  })

  it('displays usage count', () => {
    const file = makeFile({ byversions_count: 3 })
    visitFiles([file])
    cy.get('.item-usage').first().should('contain', '3')
  })

  it('shows notused class when usage is zero', () => {
    const file = makeFile({ byversions_count: 0 })
    visitFiles([file])
    cy.get('.item-usage.notused').should('exist')
  })

  // ---- Search ----

  it('has a search field', () => {
    visitFiles()
    cy.get('.search input').should('exist')
  })

  it('search field triggers reload on input', () => {
    const file = makeFile()
    visitFiles([file])
    cy.get('.search input').type('test')
    cy.wait('@gql') // debounced search query
  })

  // ---- Sort ----

  it('shows sort menu with options', () => {
    visitFiles()
    cy.get('.layout .v-btn[title="Sort by"]').click()
    cy.get('.v-list').should('contain', 'latest')
    cy.get('.v-list').should('contain', 'oldest')
    cy.get('.v-list').should('contain', 'name')
    cy.get('.v-list').should('contain', 'mime')
    cy.get('.v-list').should('contain', 'language')
    cy.get('.v-list').should('contain', 'editor')
    cy.get('.v-list').should('contain', 'usage')
  })

  it('clicking a sort option triggers GQL reload', () => {
    visitFiles()
    cy.get('.layout .v-btn[title="Sort by"]').click()
    cy.contains('.v-list .v-btn', 'name').click()
    cy.wait('@gql')
  })

  // ---- Grid / list view toggle ----

  it('shows grid view toggle button', () => {
    visitFiles()
    cy.get('.layout .v-btn[title="Grid view"]').should('exist')
  })

  it('clicking grid view switches to grid layout', () => {
    const file = makeFile()
    visitFiles([file])
    cy.get('.layout .v-btn[title="Grid view"]').click()
    cy.get('.items.grid').should('exist')
  })

  it('clicking list view switches back to list layout', () => {
    const file = makeFile()
    visitFiles([file])
    cy.get('.layout .v-btn[title="Grid view"]').click()
    cy.get('.items.grid').should('exist')
    cy.get('.layout .v-btn[title="List view"]').click()
    cy.get('.items.list').should('exist')
  })

  // ---- Add files ----

  it('shows add files button for users with file:add permission', () => {
    visitFiles()
    cy.get('.v-btn[title="Add files"]').should('exist')
  })

  it('hides add files button for users without file:add permission', () => {
    const me = {
      permission: JSON.stringify({ 'file:view': true }),
      email: 'viewer@example.com',
      name: 'Viewer',
    }
    visitFiles([], me)
    cy.get('.v-btn[title="Add files"]').should('not.exist')
  })

  // ---- Item display ----

  it('shows file name in .item-title', () => {
    const file = makeFile({
      name: 'photo.jpg',
      latest: { ...makeFile().latest, data: JSON.stringify({
        name: 'photo.jpg', lang: 'en', mime: 'image/jpeg', path: 'cms/photo.jpg',
        previews: {}, description: {}, transcription: {},
      })},
    })
    visitFiles([file])
    cy.get('.item-title').first().should('contain', 'photo.jpg')
  })

  it('shows trashed class on trashed items', () => {
    const file = makeFile({ deleted_at: '2026-01-15 00:00:00' })
    visitFiles([file])
    cy.get('.item-content.trashed').should('exist')
  })

  it('shows draft checkbox class for unpublished items', () => {
    const file = makeFile({ latest: { ...makeFile().latest, published: false } })
    visitFiles([file])
    cy.get('.items .v-checkbox-btn.draft').should('exist')
  })

  it('shows clock icon for items with publish_at', () => {
    const file = makeFile({ latest: { ...makeFile().latest, publish_at: '2026-06-01 00:00:00' } })
    visitFiles([file])
    cy.get('.publish-at').should('exist')
  })

  // ---- Item context menu ----

  it('shows context menu with actions when clicking three-dot button on an item', () => {
    const file = makeFile()
    visitFiles([file])
    cy.get('.items .v-list-item .item-menu').first().click()
    cy.get('.v-card .v-toolbar-title').should('contain', 'Actions')
  })

  it('context menu shows Publish for unpublished file', () => {
    const file = makeFile({ latest: { ...makeFile().latest, published: false } })
    visitFiles([file])
    cy.get('.items .v-list-item .item-menu').first().click()
    cy.get('.v-card .v-list').should('contain', 'Publish')
  })

  it('context menu hides Publish for already published file', () => {
    const file = makeFile()
    visitFiles([file])
    cy.get('.items .v-list-item .item-menu').first().click()
    cy.get('.v-card .v-list .v-list-item').filter(':visible').then(($items) => {
      const texts = [...$items].map((item) => item.textContent.trim())
      expect(texts.some((t) => t === 'Publish')).to.be.false
    })
  })

  it('context menu shows Delete for non-trashed file', () => {
    const file = makeFile()
    visitFiles([file])
    cy.get('.items .v-list-item .item-menu').first().click()
    cy.get('.v-card .v-list').should('contain', 'Delete')
  })

  it('context menu shows Restore for trashed file', () => {
    const file = makeFile({ deleted_at: '2026-01-15 00:00:00' })
    visitFiles([file])
    cy.get('.items .v-list-item .item-menu').first().click()
    cy.get('.v-card .v-list').should('contain', 'Restore')
  })

  it('context menu hides Delete for trashed file', () => {
    const file = makeFile({ deleted_at: '2026-01-15 00:00:00' })
    visitFiles([file])
    cy.get('.items .v-list-item .item-menu').first().click()
    cy.get('.v-card .v-list .v-btn').then(($btns) => {
      const texts = [...$btns].map((b) => b.textContent.trim())
      expect(texts).to.not.include('Delete')
    })
  })

  it('context menu shows Purge button', () => {
    const file = makeFile()
    visitFiles([file])
    cy.get('.items .v-list-item .item-menu').first().click()
    cy.get('.v-card .v-list').should('contain', 'Purge')
  })

  // ---- Context menu mutations ----

  it('clicking Publish sends pubFile mutation', () => {
    const file = makeFile({ latest: { ...makeFile().latest, published: false } })
    visitFiles([file])
    cy.get('.items .v-list-item .item-menu').first().click()
    cy.contains('.v-card .v-list .v-btn', 'Publish').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      expect(ops.some((op) => (op.query || '').includes('pubFile'))).to.be.true
    })
  })

  it('clicking Delete sends dropFile mutation', () => {
    const file = makeFile()
    visitFiles([file])
    cy.get('.items .v-list-item .item-menu').first().click()
    cy.contains('.v-card .v-list .v-btn', 'Delete').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      expect(ops.some((op) => (op.query || '').includes('dropFile'))).to.be.true
    })
  })

  it('clicking Purge sends purgeFile mutation', () => {
    const file = makeFile()
    visitFiles([file])
    cy.get('.items .v-list-item .item-menu').first().click()
    cy.contains('.v-card .v-list .v-btn', 'Purge').click()
    cy.wait('@gql').its('request.body').should((body) => {
      const ops = Array.isArray(body) ? body : [body]
      expect(ops.some((op) => (op.query || '').includes('purgeFile'))).to.be.true
    })
  })

  // ---- Bulk actions ----

  it('shows bulk checkbox and actions button in header', () => {
    visitFiles()
    cy.get('.header .bulk .v-checkbox-btn').should('exist')
    cy.get('.header .bulk .v-btn[title="Actions"]').should('exist')
  })

  it('bulk actions button is disabled when no items are checked', () => {
    const file = makeFile()
    visitFiles([file])
    cy.get('.header .bulk .v-btn[title="Actions"]').should('be.disabled')
  })

  it('checking a file item enables the bulk actions button', () => {
    const file = makeFile()
    visitFiles([file])
    cy.get('.items .v-list-item .item-check').first().click()
    cy.get('.header .bulk .v-btn[title="Actions"]').should('not.be.disabled')
  })

  it('toggle all checkbox checks all items', () => {
    const files = [
      makeFile({ id: '1' }),
      makeFile({
        id: '2', name: 'photo.jpg',
        latest: { ...makeFile().latest, id: '20', data: JSON.stringify({
          name: 'photo.jpg', lang: 'en', mime: 'image/jpeg', path: 'cms/photo.jpg',
          previews: {}, description: {}, transcription: {},
        })},
      }),
    ]
    visitFiles(files)
    cy.get('.header .bulk .v-checkbox-btn').click()
    cy.get('.items .v-list-item .item-check').each(($cb) => {
      cy.wrap($cb).find('input').should('be.checked')
    })
  })

  it('bulk actions menu shows Publish, Delete, Restore, Purge', () => {
    // Need both a trashed and non-trashed item for all options to appear
    const files = [
      makeFile({
        id: '1',
        latest: { ...makeFile().latest, published: false },
      }),
      makeFile({
        id: '2', deleted_at: '2026-01-15 00:00:00',
        latest: { ...makeFile().latest, id: '20', published: false },
      }),
    ]
    visitFiles(files)
    // Toggle all to check both items
    cy.get('.header .bulk .v-checkbox-btn').click()
    cy.get('.header .bulk .v-btn[title="Actions"]').click()
    cy.get('.v-card .v-list').should('contain', 'Publish')
    cy.get('.v-card .v-list').should('contain', 'Delete')
    cy.get('.v-card .v-list').should('contain', 'Restore')
    cy.get('.v-card .v-list').should('contain', 'Purge')
  })

  // ---- Multiple files ----

  it('displays multiple files in list', () => {
    const files = [
      makeFile({ id: '1' }),
      makeFile({
        id: '2', name: 'video.mp4',
        latest: { ...makeFile().latest, id: '20', data: JSON.stringify({
          name: 'video.mp4', lang: 'en', mime: 'video/mp4', path: 'cms/video.mp4',
          previews: {}, description: {}, transcription: {},
        })},
      }),
      makeFile({
        id: '3', name: 'song.mp3',
        latest: { ...makeFile().latest, id: '30', data: JSON.stringify({
          name: 'song.mp3', lang: 'en', mime: 'audio/mpeg', path: 'cms/song.mp3',
          previews: {}, description: {}, transcription: {},
        })},
      }),
    ]
    visitFiles(files)
    cy.get('.items .v-list-item').should('have.length', 3)
    cy.get('.item-title').eq(0).should('contain', 'test.png')
    cy.get('.item-title').eq(1).should('contain', 'video.mp4')
    cy.get('.item-title').eq(2).should('contain', 'song.mp3')
  })

  // ---- Permission-based visibility ----

  it('does not show file list when user lacks file:view permission', () => {
    const me = {
      permission: JSON.stringify({ 'page:view': true }),
      email: 'limited@example.com',
      name: 'Limited',
    }
    setupIntercept({ meResponse: me, files: [] })
    cy.visit('/files')
    cy.wait('@gql')
    cy.get('.file-list').should('not.exist')
  })
})
