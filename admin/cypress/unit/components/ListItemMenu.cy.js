import { h } from 'vue'
import ListItemMenu from '../../../js/components/ListItemMenu.vue'
import { useUserStore } from '../../../js/stores'

const all = { 'element:publish': true, 'element:save': true, 'element:drop': true, 'element:keep': true, 'element:purge': true }

function mountMenu(props, perms = all, slots = {}) {
  // cy.mount() doesn't pass slots to the component, a host component renders them
  const Host = { render: () => h(ListItemMenu, { type: 'element', activatorClass: 'item-menu', ...props }, slots) }

  return cy.mount(Host, {
    global: {
      plugins: [{ install() { useUserStore().me = { permission: perms } } }],
    },
  })
}

describe('ListItemMenu', () => {
  it('shows the actions for an unpublished item and emits them', () => {
    mountMenu({ item: { id: '1', published: false } })

    cy.get('.item-menu').click()
    cy.get('.v-overlay--active .action-menu-list .v-btn').then(($btns) => {
      expect([...$btns].map((el) => el.textContent.trim())).to.deep.equal(['Publish', 'Edit properties', 'Delete', 'Purge'])
    })
    cy.get('.v-overlay--active .v-divider').should('have.length', 2)
    cy.contains('.v-overlay--active .action-menu-list .v-btn', 'Publish').click()

    cy.then(() => {
      expect(Cypress.vueWrapper.findComponent(ListItemMenu).emitted('publish')).to.have.length(1)
    })
  })

  it('offers restoring instead of publishing and deleting for trashed items', () => {
    mountMenu({ item: { id: '1', published: false, deleted_at: '2026-01-01' } })

    cy.get('.item-menu').click()
    cy.get('.v-overlay--active .action-menu-list .v-btn').then(($btns) => {
      expect([...$btns].map((el) => el.textContent.trim())).to.deep.equal(['Edit properties', 'Restore', 'Purge'])
    })
  })

  it('checks the permissions of its own type', () => {
    mountMenu({ item: { id: '1', published: false } }, { 'file:publish': true, 'element:drop': true })

    cy.get('.item-menu').click()
    cy.get('.v-overlay--active .action-menu-list .v-btn').should('have.length', 1).and('contain', 'Delete')
    cy.get('.v-overlay--active .v-divider').should('not.exist')
  })

  it('renders additional actions after editing', () => {
    mountMenu({ item: { id: '1', published: true } }, all, {
      default: () => h('div', { class: 'extra-action' }, 'Extra'),
    })

    cy.get('.item-menu').click()
    cy.contains('.v-overlay--active .action-menu-list .v-btn', 'Edit properties')
      .closest('.v-list-item')
      .next()
      .should('have.class', 'extra-action')
  })
})
