import AsideList from '../../../js/components/AsideList.vue'

const content = [
  {
    key: 'status',
    title: 'Status',
    items: [
      { title: 'Published', value: { publish: 'PUBLISHED' }, icon: 'mdi-check' },
      { title: 'Draft', value: { publish: 'DRAFT' }, icon: 'mdi-pencil' },
    ],
  },
  {
    key: 'trashed',
    title: 'Trashed',
    items: [
      { title: 'With trashed', value: { trashed: 'WITH' }, icon: 'mdi-delete' },
    ],
  },
]

function mountList(filter = {}, props = {}) {
  return cy.mount(AsideList, {
    props: { content, filter, ...props },
  })
}

describe('AsideList', () => {
  it('renders a navigation drawer', () => {
    mountList()
    cy.get('.v-navigation-drawer').should('exist')
  })

  it('renders a list group for each content entry', () => {
    mountList()
    cy.get('.v-list-group').should('have.length', 2)
  })

  it('renders the group title', () => {
    mountList()
    cy.contains('Status').should('exist')
    cy.contains('Trashed').should('exist')
  })

  it('renders a reset button', () => {
    mountList()
    cy.contains('.v-btn', 'Reset').should('exist')
  })

  it('disables reset button when filter is unchanged', () => {
    mountList({})
    cy.contains('.v-btn', 'Reset').should('be.disabled')
  })

  it('renders item titles inside groups', () => {
    mountList({ status: 'PUBLISHED' })
    cy.get('.v-list-group').first().find('.v-list-item').first().click({ force: true })
    cy.contains('.v-btn', 'Published').should('exist')
    cy.contains('.v-btn', 'Draft').should('exist')
  })

  it('emits update:filter when reset is clicked after changes', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(AsideList, {
      props: { content, filter: { status: 'PUBLISHED' }, 'onUpdate:filter': onUpdate },
    })
    cy.get('.v-list-group').first().find('.v-list-item').first().click({ force: true })
    cy.contains('.v-btn', 'Draft').click({ force: true })
    cy.contains('.v-btn', 'Reset').click({ force: true })
    cy.get('@update').should('have.been.called')
  })
})
