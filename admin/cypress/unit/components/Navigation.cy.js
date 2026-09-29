import Navigation from '../../../js/components/Navigation.vue'
import { useUserStore } from '../../../js/stores'
import { shortcuts } from '../../../js/shortcuts'

describe('Navigation', () => {
  function mountWithPerms(perms = {}) {
    return cy.mount(Navigation, {
      global: {
        plugins: [{
          install() {
            const user = useUserStore()
            user.me = { permission: perms }
          }
        }],
      },
    })
  }

  it('renders a navigation drawer', () => {
    mountWithPerms()
    cy.get('.v-navigation-drawer').should('exist')
  })

  it('shows the Pages link when the user has page:view', () => {
    mountWithPerms({ 'page:view': true })
    cy.contains('Pages').should('exist')
  })

  it('hides the Pages link when page:view is not granted', () => {
    mountWithPerms({})
    cy.contains('Pages').should('not.exist')
  })

  it('shows the Shared elements link when the user has element:view', () => {
    mountWithPerms({ 'element:view': true })
    cy.contains('Shared elements').should('exist')
  })

  it('hides the Shared elements link when element:view is not granted', () => {
    mountWithPerms({})
    cy.contains('Shared elements').should('not.exist')
  })

  it('shows the Media link when the user has file:view', () => {
    mountWithPerms({ 'file:view': true })
    cy.contains('Media').should('exist')
  })

  it('hides the Media link when file:view is not granted', () => {
    mountWithPerms({})
    cy.contains('Media').should('not.exist')
  })

  it('shows all three links when the user has all permissions', () => {
    mountWithPerms({ 'page:view': true, 'element:view': true, 'file:view': true })
    cy.contains('Pages').should('exist')
    cy.contains('Shared elements').should('exist')
    cy.contains('Media').should('exist')
  })

  it('shows no links when the user has no permissions', () => {
    mountWithPerms({})
    cy.get('.v-list-item a').should('not.exist')
  })

  it('opens the keyboard shortcut sheet', () => {
    cy.viewport(1400, 800)
    mountWithPerms({})
    cy.then(() => (shortcuts.sheet = false))
    cy.get('.btn-shortcuts').click()
    cy.then(() => expect(shortcuts.sheet).to.equal(true))
  })
})
