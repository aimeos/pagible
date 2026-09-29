import ReloginDialog from '../../../js/components/ReloginDialog.vue'
import router from '../../../js/routes'
import { useUserStore } from '../../../js/stores'

describe('ReloginDialog', () => {
  let user

  beforeEach(() => {
    cy.viewport(1000, 700)
    cy.mount(ReloginDialog).then(() => {
      user = useUserStore()
      user.me = { email: 'editor@example.com', permission: {}, settings: {} }
      user.expired = true
    })
  })

  it('shows the current user when the session expired', () => {
    cy.contains('.v-dialog:visible .v-toolbar-title', 'Session expired').should('exist')
    cy.get('.v-dialog:visible input:not([type=hidden])')
      .first()
      .should('have.value', 'editor@example.com')
  })

  it('keeps the focus in the password field if a view below grabs it', () => {
    cy.get('.v-dialog:visible input[type=password]').should('have.focus')

    // the focus trap moves the focus from outside to the first button, which is "Logout"
    cy.document().then((doc) => doc.activeElement.blur())
    cy.get('.v-dialog:visible .v-toolbar .v-btn').focus()

    cy.get('.v-dialog:visible input[type=password]').should('have.focus')
  })

  it('signs in again with the entered password', () => {
    cy.then(() => {
      cy.stub(user, 'relogin')
        .callsFake(() => {
          user.expired = false
          return Promise.resolve()
        })
        .as('relogin')
    })

    cy.get('.v-dialog:visible input[type=password]').type('secret{enter}')
    cy.get('@relogin').should('have.been.calledOnceWith', 'secret')
    cy.get('.v-dialog:visible').should('not.exist')
  })

  it('shows the error if signing in fails', () => {
    cy.then(() => {
      cy.stub(user, 'relogin').rejects(new Error('Invalid credentials'))
    })

    cy.get('.v-dialog:visible input[type=password]').type('wrong{enter}')
    cy.contains('.v-dialog:visible .v-alert', 'Invalid credentials').should('exist')
  })

  it('logs out and redirects to the login page', () => {
    cy.then(() => {
      cy.stub(user, 'expire')
        .callsFake(() => {
          user.expired = false
          return Promise.resolve()
        })
        .as('expire')
      cy.stub(router, 'push').resolves().as('push')
    })

    cy.contains('.v-dialog:visible .v-card-actions .v-btn', 'Logout').click()
    cy.get('@expire').should('have.been.calledOnce')
    cy.get('@push').should('have.been.calledOnceWith', { name: 'login' })
  })

  describe('without password login', () => {
    beforeEach(() => {
      cy.then(() => {
        Cypress.vueWrapper.findComponent(ReloginDialog).vm.urllogin =
          'https://sso.example.com/login'
      })
    })

    it('opens the login page of the application', () => {
      cy.then(() => cy.stub(window, 'open').as('open'))

      cy.get('.v-dialog:visible input[type=password]').should('not.exist')
      cy.contains('.v-dialog:visible .relogin-open', 'Sign in').click()
      cy.get('@open').should('have.been.calledOnceWith', 'https://sso.example.com/login', '_blank')
    })

    it('passes the current URL to the login page', () => {
      cy.then(() => {
        Cypress.vueWrapper.findComponent(ReloginDialog).vm.urllogin =
          'https://sso.example.com/login?redirect=_url_'
        cy.stub(window, 'open').as('open')
      })

      cy.contains('.v-dialog:visible .relogin-open', 'Sign in').click()
      cy.get('@open').should(
        'have.been.calledOnceWith',
        'https://sso.example.com/login?redirect=' + encodeURIComponent(window.location.href)
      )
    })

    it('continues after the user signed in', () => {
      cy.then(() => {
        cy.stub(user, 'resume')
          .callsFake(() => {
            user.expired = false
            return Promise.resolve(true)
          })
          .as('resume')
      })

      cy.contains('.v-dialog:visible .v-card-actions .v-btn', 'Continue').click()
      cy.get('@resume').should('have.been.calledOnce')
      cy.get('.v-dialog:visible').should('not.exist')
    })

    it('tells the user if not signed in yet', () => {
      cy.then(() => cy.stub(user, 'resume').resolves(false))

      cy.contains('.v-dialog:visible .v-card-actions .v-btn', 'Continue').click()
      cy.contains('.v-dialog:visible .v-alert', 'You are not signed in yet').should('exist')
    })
  })
})
