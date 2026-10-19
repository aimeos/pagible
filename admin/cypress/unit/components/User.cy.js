import User from '../../../js/components/User.vue'
import { useMessageStore, useSessionStore, useUserStore } from '../../../js/stores'

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
    cy.get('button.btn-darkmode').should('exist')
  })

  it('renders the language switch button', () => {
    mountUser()
    cy.get('.btn-language button').should('exist')
  })

  it('renders the theme toggle with sun or moon icon', () => {
    mountUser()
    cy.get('button.btn-darkmode .v-icon').should('exist')
  })

  it('shows the user menu of the current user in the user store', () => {
    mountUser().then(() => {
      useUserStore().me = { name: 'Editor', permission: {} }
    })
    cy.get('button.icon').should('exist')

    cy.then(() => {
      useUserStore().me = null
    })
    cy.get('button.icon').should('not.exist')
  })

  it('shows an error and opens the login page if logging out fails', () => {
    const push = cy.stub()

    cy.mount(User, {
      global: { stubs, mocks: { $router: { push } } },
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(User).vm
      const error = cy.stub(useMessageStore(), 'error')

      useSessionStore().logout = () => Promise.reject(new Error('offline'))

      return vm.logout().then(() => {
        expect(error).to.have.been.calledWith('Logout failed')
        expect(push).to.have.been.calledWith({ name: 'login' })
      })
    })
  })
})
