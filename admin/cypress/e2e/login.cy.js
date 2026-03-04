/**
 * E2E tests for the login flow.
 *
 * GraphQL is intercepted at POST /graphql. Apollo's BatchHttpLink sends
 * requests as JSON arrays, so the handler checks whether req.body is an
 * array (batched) or an object (single) and replies in the same shape.
 */

describe('Login', () => {
  /**
   * Register a single intercept that handles all GraphQL operations for one test.
   *
   * @param {object} options
   * @param {object|null} options.meResponse   – value returned as `data.me`
   * @param {object|null} options.loginResponse – value returned as `data.cmsLogin`
   * @param {string|null} options.loginError    – error message for the cmsLogin mutation
   */
  function setupIntercept({ meResponse = null, loginResponse = null, loginError = null } = {}) {
    cy.intercept('POST', '/graphql', (req) => {
      const isBatch = Array.isArray(req.body)
      const ops = isBatch ? req.body : [req.body]

      const responses = ops.map((op) => {
        const query = op.query || ''

        if (query.includes('cmsLogin')) {
          if (loginError) {
            return { errors: [{ message: loginError }] }
          }
          return { data: { cmsLogin: loginResponse } }
        }

        if (query.includes('pages')) {
          return { data: { pages: { data: [], paginatorInfo: { currentPage: 1, lastPage: 1 } } } }
        }

        // Default: auth-check (me) query
        return { data: { me: meResponse } }
      })

      req.reply(isBatch ? responses : responses[0])
    }).as('gql')
  }

  /** Visit the root and wait until the login form becomes visible. */
  function visitAndShowForm() {
    cy.visit('/')
    cy.wait('@gql')
    cy.get('.login.show').should('be.visible')
  }

  // ---------------------------------------------------------------------------

  it('shows login form when not authenticated', () => {
    setupIntercept({ meResponse: null })
    cy.visit('/')
    cy.wait('@gql')
    cy.get('.login.show').should('be.visible')
  })

  it('shows "Field is required" when email field is blurred empty', () => {
    setupIntercept({ meResponse: null })
    visitAndShowForm()
    cy.get('input[autocomplete="username"]').type('x').clear().blur()
    cy.get('.login').should('contain', 'Field is required')
  })

  it('shows "Invalid e-mail address" for a malformed email', () => {
    setupIntercept({ meResponse: null })
    visitAndShowForm()
    cy.get('input[autocomplete="username"]').type('notanemail').blur()
    cy.get('.login').should('contain', 'Invalid e-mail address')
  })

  it('shows error alert when credentials are rejected by the server', () => {
    setupIntercept({ meResponse: null, loginError: 'Invalid credentials' })
    visitAndShowForm()

    cy.get('input[autocomplete="username"]').type('test@example.com')
    cy.get('input[autocomplete="current-password"]').type('wrongpassword')
    cy.get('button[type="submit"]').should('not.be.disabled').click()
    cy.wait('@gql')

    cy.get('.v-alert').should('be.visible').and('contain', 'Invalid credentials')
  })

  it('redirects to /pages after successful login with page:view permission', () => {
    setupIntercept({
      meResponse: null,
      loginResponse: {
        permission: '{"page:view":true}',
        email: 'admin@example.com',
        name: 'Admin',
      },
    })
    visitAndShowForm()

    cy.get('input[autocomplete="username"]').type('admin@example.com')
    cy.get('input[autocomplete="current-password"]').type('secret')
    cy.get('button[type="submit"]').should('not.be.disabled').click()
    cy.wait('@gql')

    cy.url().should('include', '/pages')
  })

  it('shows "Not a CMS editor" when the logged-in user has no permissions', () => {
    setupIntercept({
      meResponse: null,
      loginResponse: {
        permission: '{}',
        email: 'editor@example.com',
        name: 'Editor',
      },
    })
    visitAndShowForm()

    cy.get('input[autocomplete="username"]').type('editor@example.com')
    cy.get('input[autocomplete="current-password"]').type('secret')
    cy.get('button[type="submit"]').should('not.be.disabled').click()
    cy.wait('@gql')

    cy.get('.v-alert').should('be.visible').and('contain', 'Not a CMS editor')
  })
})
