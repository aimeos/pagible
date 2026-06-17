import PageBulkDialog from '../../../js/components/PageBulkDialog.vue'

describe('PageBulkDialog', () => {
  it('opens the status dropdown and shows options on a real click', () => {
    cy.mount(PageBulkDialog, { props: { modelValue: true, count: 2 } })
    cy.get('.btn-apply').should('be.visible')
    cy.get('.prop').first().find('.v-field').realClick()
    cy.get('.v-overlay-container [role="option"]').should('have.length.gte', 1)
  })
})
