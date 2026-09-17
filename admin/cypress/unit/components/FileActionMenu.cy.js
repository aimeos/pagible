import FileActionMenu from '../../../js/components/FileActionMenu.vue'

describe('FileActionMenu', () => {
  it('emits the available file actions', () => {
    cy.mount(FileActionMenu, { props: { editable: true } })

    cy.get('button[title="Actions"]').click()
    cy.contains('.v-overlay--active .v-btn', 'Edit').click()
    cy.then(() => {
      expect(Cypress.vueWrapper.findComponent(FileActionMenu).emitted('edit')).to.have.length(1)
    })

    cy.get('button[title="Actions"]').click()
    cy.contains('.v-overlay--active .v-btn', 'Remove').click()
    cy.then(() => {
      expect(Cypress.vueWrapper.findComponent(FileActionMenu).emitted('remove')).to.have.length(1)
    })
  })

  it('hides editing when it is unavailable', () => {
    cy.mount(FileActionMenu)
    cy.get('button[title="Actions"]').click()
    cy.contains('.v-overlay--active .v-btn', 'Edit').should('not.exist')
    cy.contains('.v-overlay--active .v-btn', 'Remove').should('exist')
  })
})
