import ListReload from '../../../js/components/ListReload.vue'

describe('ListReload', () => {
  it('emits reload when clicking the reload button', () => {
    const onReload = cy.spy().as('reload')
    cy.mount(ListReload, { props: { title: 'Reload items', onReload } })

    cy.get('.btn-reload').should('have.attr', 'title', 'Reload items').click()
    cy.get('@reload').should('have.been.calledOnce')
  })

  it('shows the refresh button only if the list is outdated', () => {
    const onReload = cy.spy().as('reload')
    cy.mount(ListReload, { props: { title: 'Reload items', outdated: true, onReload } })

    cy.get('.btn-outdated').click()
    cy.get('@reload').should('have.been.calledOnce')
  })

  it('hides the refresh button if the list is up to date', () => {
    cy.mount(ListReload, { props: { title: 'Reload items' } })
    cy.get('.btn-outdated').should('not.exist')
  })
})
