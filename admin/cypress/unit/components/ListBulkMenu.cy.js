import { h } from 'vue'
import ListBulkMenu from '../../../js/components/ListBulkMenu.vue'
import { useUserStore } from '../../../js/stores'

function mountMenu(props, perms, slots = {}) {
  // cy.mount() doesn't pass slots to the component, a host component renders them
  const Host = { render: () => h(ListBulkMenu, { type: 'file', selected: true, ...props }, slots) }

  return cy.mount(Host, {
    global: {
      plugins: [{ install() { useUserStore().me = { permission: perms } } }],
    },
  })
}

describe('ListBulkMenu', () => {
  it('shows the permitted actions with their counts and emits them', () => {
    const perms = { 'file:publish': true, 'file:save': true, 'file:drop': true, 'file:keep': true, 'file:purge': true }
    mountMenu({ counts: { all: 3, draft: 1, live: 2, trashed: 1 } }, perms)

    cy.get('.btn-actions button').click()
    cy.get('.v-overlay--active .action-menu-list .v-btn:visible').should('have.length', 5)
    cy.contains('.v-overlay--active .action-menu-list .v-btn', 'Publish (1)')
    cy.contains('.v-overlay--active .action-menu-list .v-btn', 'Edit properties (3)')
    cy.contains('.v-overlay--active .action-menu-list .v-btn', 'Delete (2)')
    cy.contains('.v-overlay--active .action-menu-list .v-btn', 'Restore (1)')
    cy.contains('.v-overlay--active .action-menu-list .v-btn', 'Purge (3)').click()

    cy.then(() => {
      expect(Cypress.vueWrapper.findComponent(ListBulkMenu).emitted('purge')).to.have.length(1)
    })
  })

  it('checks the permissions of its own type', () => {
    mountMenu({ type: 'element', counts: { all: 1, draft: 1, live: 1 } }, { 'file:publish': true, 'element:drop': true })

    cy.get('.btn-actions button').click()
    cy.contains('.v-overlay--active .action-menu-list .v-btn', 'Publish').should('not.exist')
    cy.contains('.v-overlay--active .action-menu-list .v-btn', 'Delete (1)').should('exist')
  })

  it('renders additional actions after editing', () => {
    mountMenu({ counts: { all: 1, live: 1 } }, { 'file:save': true, 'file:drop': true }, {
      default: () => h('div', { class: 'extra-action' }, 'Extra'),
    })

    cy.get('.btn-actions button').click()
    cy.get('.v-overlay--active .action-menu-list > *').eq(1).should('have.class', 'extra-action')
  })

  it('disables the menu button', () => {
    mountMenu({ disabled: true, counts: {} }, {})
    cy.get('.btn-actions button').should('be.disabled')
  })

  it('disables the menu button without permission for any action', () => {
    mountMenu({ counts: { all: 1, draft: 1, live: 1 } }, { 'file:view': true, 'element:drop': true })
    cy.get('.btn-actions button').should('be.disabled')
  })
})
