import ElementList from '../../src/views/ElementList.vue'
import { useAuthStore } from '../../src/stores'

const stubs = {
  ElementListItems: { template: '<div class="element-list-items-stub" />' },
  ElementDetail: { template: '<div class="element-detail-stub" />' },
  Navigation: { template: '<div class="navigation-stub" />' },
  AsideList: { template: '<div class="aside-list-stub" />' },
  User: { template: '<div class="user-stub" />' },
}

function mountElementList(perms = {}) {
  return cy.mount(ElementList, {
    global: {
      stubs,
      provide: {
        locales: () => [
          { value: 'en', title: 'English (EN)' },
        ],
      },
      plugins: [{
        install() {
          const auth = useAuthStore()
          auth.me = { permission: perms, email: 'test@test.com' }
        }
      }],
    },
  })
}

describe('ElementList', () => {
  it('renders the element list view', () => {
    mountElementList()
    cy.get('.v-app-bar').should('exist')
  })

  it('shows "Shared elements" in the app bar title', () => {
    mountElementList()
    cy.get('.v-app-bar-title').should('contain', 'Shared elements')
  })

  it('renders the navigation toggle button', () => {
    mountElementList()
    cy.get('.v-app-bar button').first().should('exist')
  })

  it('renders the User stub', () => {
    mountElementList()
    cy.get('.user-stub').should('exist')
  })

  it('renders the Navigation stub', () => {
    mountElementList()
    cy.get('.navigation-stub').should('exist')
  })

  it('renders the ElementListItems stub', () => {
    mountElementList()
    cy.get('.element-list-items-stub').should('exist')
  })

  it('renders the AsideList stub', () => {
    mountElementList()
    cy.get('.aside-list-stub').should('exist')
  })
})
