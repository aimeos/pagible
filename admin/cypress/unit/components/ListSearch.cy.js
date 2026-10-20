import { h } from 'vue'
import ListSearch from '../../../js/components/ListSearch.vue'

describe('ListSearch', () => {
  it('emits the entered search term', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(ListSearch, { props: { modelValue: '', 'onUpdate:modelValue': onUpdate } })

    cy.get('.search input').type('a')
    cy.get('@update').should('have.been.calledWith', 'a')
  })

  it('focuses the search field', () => {
    cy.mount(ListSearch, { props: { modelValue: '' } }).then(({ wrapper }) => {
      wrapper.findComponent(ListSearch).vm.focus()
    })

    cy.get('.search input').should('have.focus')
  })

  it('renders the slot content before the field', () => {
    // cy.mount() doesn't pass slots, so the search is rendered by a host component
    cy.mount({ render: () => h(ListSearch, { modelValue: '' }, { default: () => h('span', { class: 'extra' }, 'x') }) })
    cy.get('.search > .extra').should('exist')
  })
})
