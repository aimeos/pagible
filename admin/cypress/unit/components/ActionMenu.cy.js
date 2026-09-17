import { h } from 'vue'
import ActionMenu from '../../../js/components/ActionMenu.vue'

const Harness = {
  render() {
    return h(ActionMenu, {
      title: 'Choose action',
      listProps: { role: 'listbox' }
    }, {
      activator: ({ props, label }) => h('button', { ...props, class: 'activator' }, label),
      default: () => h('button', { class: 'entry' }, 'Entry')
    })
  }
}

const LocatedHarness = {
  render() {
    return h(ActionMenu, {
      title: 'Choose action',
      location: 'bottom end'
    }, {
      activator: ({ props, label }) => h('button', { ...props, class: 'activator' }, label),
      default: () => h('button', { class: 'entry' }, 'Entry')
    })
  }
}

describe('ActionMenu', () => {
  it('forwards the title and list attributes and closes after an action', () => {
    cy.mount(Harness)
    cy.get('.activator').should('have.text', 'Choose action').click()
    cy.get('.v-overlay--active [role="listbox"]').should('exist')
    cy.get('.entry').click({ force: true })
    cy.get('.v-overlay--active').should('not.exist')
  })

  it('uses the same card shell on mobile', () => {
    cy.viewport(390, 844)
    cy.mount(LocatedHarness)
    cy.get('.activator').click()
    cy.get('.v-dialog .v-card').should('be.visible')
    cy.get('.v-dialog .v-toolbar-title').should('have.text', 'Choose action')
    cy.get('.v-dialog button[aria-label="Close"]').click({ force: true })
    cy.get('.v-overlay--active').should('not.exist')
  })
})
