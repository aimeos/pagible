import ElementList from '../../js/views/ElementList.vue'
import { useUserStore } from '../../js/stores'

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
          const user = useUserStore()
          user.me = { permission: perms, email: 'test@test.com' }
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

  it('initializes filter from settings', () => {
    cy.mount(ElementList, {
      global: {
        stubs,
        provide: {
          locales: () => [{ value: 'en', title: 'English (EN)' }],
        },
        plugins: [{
          install() {
            const user = useUserStore()
            user.me = {
              permission: {},
              email: 'test@test.com',
              settings: { element: { filter: { publish: 'PUBLISHED', lang: 'de' } } }
            }
          }
        }],
      },
    }).then(() => {
      const vm = Cypress.vueWrapper.findComponent(ElementList).vm
      expect(vm.filter.publish).to.equal('PUBLISHED')
      expect(vm.filter.lang).to.equal('de')
      expect(vm.filter.trashed).to.equal('WITHOUT')
    })
  })

  it('uses default filter when settings is null', () => {
    mountElementList().then(() => {
      const vm = Cypress.vueWrapper.findComponent(ElementList).vm
      expect(vm.filter.trashed).to.equal('WITHOUT')
      expect(vm.filter.publish).to.be.null
    })
  })
})
