import User from '../../../js/components/User.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
}

function mountUser(perms = {}) {
  return cy.mount(User, {
    global: { stubs },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('User', () => {
  it('renders the theme toggle button', () => {
    mountUser()
    cy.get('.theme-toggle').should('exist')
  })

  it('renders the language switch button', () => {
    mountUser()
    cy.get('.lang-switch').should('exist')
  })

  it('renders the theme toggle with sun or moon icon', () => {
    mountUser()
    cy.get('.theme-toggle .v-icon').should('exist')
  })
})
