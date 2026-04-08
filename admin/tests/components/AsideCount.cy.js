import AsideCount from '../../js/components/AsideCount.vue'
import { useSideStore } from '../../js/stores'

function mountWithData(storeData = {}) {
  return cy.mount(AsideCount).then(() => {
    const side = useSideStore()
    side.store = storeData
  })
}

describe('AsideCount', () => {
  it('renders a navigation drawer', () => {
    mountWithData()
    cy.get('.v-navigation-drawer').should('exist')
  })

  it('renders a list group for each key in the side store', () => {
    mountWithData({
      type: { heading: 3, text: 8 },
    })
    cy.get('.v-list-group').should('have.length', 1)
  })

  it('renders list items for each value in a group', () => {
    mountWithData({
      type: { heading: 3, text: 8 },
    })
    cy.get('.v-list-item .name').should('contain', 'heading')
    cy.get('.v-list-item .value').should('contain', '3')
    cy.get('.v-list-item .name').should('contain', 'text')
    cy.get('.v-list-item .value').should('contain', '8')
  })

  it('renders multiple list groups for multiple store keys', () => {
    mountWithData({
      type: { heading: 1 },
      status: { published: 5 },
    })
    cy.get('.v-list-group').should('have.length', 2)
  })

  it('sorts group keys alphabetically', () => {
    mountWithData({
      zzz: { last: 1 },
      aaa: { first: 2 },
    })
    cy.get('.v-list-group').first().should('contain', 'aaa')
    cy.get('.v-list-group').last().should('contain', 'zzz')
  })

  it('toggles active state when a list item is clicked', () => {
    mountWithData({ type: { heading: 2 } })
    cy.get('.v-list-item').last().click({ force: true })
    cy.get('.v-list-item--active').should('exist')
  })

  it('renders nothing visible when the side store is empty', () => {
    mountWithData({})
    cy.get('.v-list-group').should('not.exist')
  })
})
