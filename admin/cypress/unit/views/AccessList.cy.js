import AccessList from '../../../js/views/AccessList.vue'

describe('AccessList', () => {
  it('uses the standard shell for access dialogs', () => {
    let view

    cy.mount(AccessList, {
      global: {
        mocks: {
          $route: { name: 'access:view', query: {} },
          $router: { replace: cy.stub() }
        },
        stubs: {
          AccessUsers: { template: '<div />' },
          Navigation: { template: '<div />' },
          User: { template: '<div />' }
        }
      }
    }).then(({ wrapper }) => {
      view = wrapper.findComponent(AccessList).vm
      view.addDialog = true
    })

    cy.contains('.v-dialog:visible .v-toolbar-title', 'Add access value').should('exist')
    cy.get('.v-dialog:visible button[aria-label="Close"]').click()

    cy.then(() => {
      view.deleteDialog = true
    })

    cy.contains('.v-dialog:visible .v-toolbar-title', 'Delete access values').should('exist')
    cy.get('.v-dialog:visible .v-toolbar').should('have.class', 'bg-warning')
    cy.get('.v-dialog:visible').should('have.attr', 'role', 'alertdialog')
    cy.get('.v-dialog:visible .v-card-actions').within(() => {
      cy.contains('.v-btn', 'Cancel').should('exist')
      cy.contains('.v-btn', 'Delete').should('exist')
    })
  })
})
