import Login from '../../src/views/Login.vue'
import { useUserStore } from '../../src/stores'

describe('Login', () => {
  function mountLogin(opts = {}) {
    return cy.mount(Login, {
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
})
