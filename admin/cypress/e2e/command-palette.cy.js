/**
 * E2E tests for the command palette (Ctrl/Cmd+K) in the running admin.
 */

const ME_ADMIN = {
  permission: JSON.stringify({ 'element:view': true, 'page:view': true, 'file:view': true }),
  email: 'admin@example.com',
  name: 'Admin',
}

function visitElements() {
  cy.intercept('POST', '/graphql', (req) => {
    const ops = Array.isArray(req.body) ? req.body : [req.body]
    const responses = ops.map((op) => {
      const query = op.query || ''

      if (query.includes('elements') || query.includes('pages') || query.includes('files')) {
        return { data: { elements: { data: [], paginatorInfo: { lastPage: 1 } } } }
      }
      return { data: { me: ME_ADMIN } }
    })

    req.reply(Array.isArray(req.body) ? responses : responses[0])
  }).as('gql')

  cy.visit('/elements')
  cy.wait('@gql')
  cy.get('.v-app-bar-title', { timeout: 30000 }).should('contain', 'Shared elements')
}

const palette = () => cy.get('body').trigger('keydown', { key: 'k', ctrlKey: true })

describe('Command palette', () => {
  it('runs "Search in list" and keeps the focus in the search field', () => {
    visitElements()

    cy.get('.v-app-bar .v-btn').first().focus() // the dialog restores the focus to it
    palette()
    cy.get('.command-palette .palette-input input').should('have.focus').type('search in')
    cy.contains('.palette-item.active', 'Search in list')
    cy.get('.command-palette .palette-input input').type('{enter}')

    cy.get('.command-palette.v-overlay--active').should('not.exist')
    // the dialog restores the focus after its leave transition
    cy.get('body').should(($body) => {
      expect($body.find('.command-palette .v-overlay__content:visible')).to.have.length(0)
    })
    cy.focused().closest('.search').find('input').should('exist')
  })

  it('opens the shortcut sheet from the palette', () => {
    visitElements()

    palette()
    cy.get('.command-palette .palette-input input').type('keyboard{enter}')
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Keyboard shortcuts').should('exist')
  })
})
