import Login from '../../../js/views/Login.vue'
import { useUserStore } from '../../../js/stores'
import { browser } from '../../../js/utils'

describe('Login', () => {
  function mountLogin(opts = {}, props = {}) {
    return cy.mount(Login, {
      props,
      global: {
        plugins: [{
          install() {
            const user = useUserStore()
            user.me = false // prevent real Apollo call in created()
          }
        }],
        ...opts,
      },
    })
  }

  it('renders the login form', () => {
    mountLogin()
    cy.get('.login').should('exist')
  })

  it('shows the card title "PagibleAI CMS"', () => {
    mountLogin()
    cy.contains('PagibleAI CMS').should('exist')
  })

  it('renders an email input field', () => {
    mountLogin()
    cy.get('input[autocomplete="username"]').should('exist')
  })

  it('renders a password input field', () => {
    mountLogin()
    cy.get('input[autocomplete="current-password"]').should('exist')
  })

  it('password field starts as type "password"', () => {
    mountLogin()
    cy.get('input[autocomplete="current-password"]').should('have.attr', 'type', 'password')
  })

  it('toggles password visibility on eye icon click', () => {
    mountLogin()
    cy.get('input[autocomplete="current-password"]').should('have.attr', 'type', 'password')
    cy.get('.v-field__append-inner .v-icon').click()
    cy.get('input[autocomplete="current-password"]').should('have.attr', 'type', 'text')
  })

  it('renders a login button', () => {
    mountLogin()
    cy.contains('button', 'Login').should('exist')
  })

  it('does not show error alert initially', () => {
    mountLogin()
    cy.get('.v-alert').should('not.be.visible')
  })

  describe('with single sign-on', () => {
    const urllogin = 'https://sso.example.com/login?redirect=_url_'

    beforeEach(() => {
      sessionStorage.removeItem('cms-sso')
      cy.stub(browser, 'assign').as('assign')
    })

    it('redirects to the login page of the application', () => {
      mountLogin({}, { urllogin })

      cy.get('@assign').should('have.been.calledOnce')
      cy.get('@assign')
        .its('firstCall.args.0')
        .should((url) => {
          const back = new URL(url).searchParams.get('redirect')
          expect(url).to.match(/^https:\/\/sso\.example\.com\/login\?redirect=/)
          expect(new URL(back).origin).to.equal(window.location.origin)
        })
      cy.get('input[autocomplete="current-password"]').should('not.exist')
    })

    it('stops redirecting if the last redirect did not sign in the user', () => {
      sessionStorage.setItem('cms-sso', String(Date.now()))
      mountLogin({}, { urllogin })

      cy.get('.login.show').should('exist')
      cy.contains('.v-alert', 'Login failed').should('be.visible')
      cy.get('@assign').should('not.have.been.called')

      cy.contains('button', 'Sign in').click()
      cy.get('@assign').should('have.been.calledOnce')
    })

    it('does not redirect after the user logged out', () => {
      cy.stub(window.history, 'state').value({ back: '/pages' })
      mountLogin({}, { urllogin })

      cy.get('.login.show').should('exist')
      cy.get('.v-alert').should('not.be.visible')
      cy.get('@assign').should('not.have.been.called')
    })
  })
})
