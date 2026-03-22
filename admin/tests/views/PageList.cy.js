import PageList from '../../src/views/PageList.vue'
import { useUserStore } from '../../src/stores'

const stubs = {
  PageListItems: { template: '<div class="page-list-items-stub" />' },
  PageDetail: { template: '<div class="page-detail-stub" />' },
  Navigation: { template: '<div class="navigation-stub" />' },
  AsideList: { template: '<div class="aside-list-stub" />' },
  User: { template: '<div class="user-stub" />' },
}

function mountPageList(perms = {}) {
  return cy.mount(PageList, {
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

describe('PageList', () => {
  it('renders the page list view', () => {
    mountPageList()
    cy.get('.v-app-bar').should('exist')
  })

  it('shows "Pages" in the app bar title', () => {
    mountPageList()
    cy.get('.v-app-bar-title').should('contain', 'Pages')
  })

  it('renders the navigation toggle button', () => {
    mountPageList()
    cy.get('.v-app-bar button').first().should('exist')
  })

  it('renders the User stub', () => {
    mountPageList()
    cy.get('.user-stub').should('exist')
  })

  it('renders the PageListItems stub', () => {
    mountPageList()
    cy.get('.page-list-items-stub').should('exist')
  })

  it('shows AI prompt textarea when page:synthesize permission is granted', () => {
    mountPageList({ 'page:synthesize': true })
    cy.get('.prompt').should('exist')
  })

  it('hides AI prompt textarea without page:synthesize permission', () => {
    mountPageList({})
    cy.get('.prompt').should('not.exist')
  })

  it('same() returns false for null inputs', () => {
    mountPageList().then(() => {
      const vm = Cypress.vueWrapper.findComponent(PageList).vm
      expect(vm.same(null, {})).to.be.false
      expect(vm.same({}, null)).to.be.false
    })
  })

  it('same() returns true for equal objects', () => {
    mountPageList().then(() => {
      const vm = Cypress.vueWrapper.findComponent(PageList).vm
      expect(vm.same({ a: 1, b: 2 }, { a: 1, b: 2 })).to.be.true
    })
  })

  it('same() returns false for different objects', () => {
    mountPageList().then(() => {
      const vm = Cypress.vueWrapper.findComponent(PageList).vm
      expect(vm.same({ a: 1 }, { a: 2 })).to.be.false
      expect(vm.same({ a: 1 }, { a: 1, b: 2 })).to.be.false
    })
  })

  it('initializes filter from settings', () => {
    cy.mount(PageList, {
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
              settings: { page: { filter: { view: 'list', publish: 'DRAFT' } } }
            }
          }
        }],
      },
    }).then(() => {
      const vm = Cypress.vueWrapper.findComponent(PageList).vm
      expect(vm.filter.view).to.equal('list')
      expect(vm.filter.publish).to.equal('DRAFT')
      expect(vm.filter.trashed).to.equal('WITHOUT')
    })
  })

  it('uses default filter when settings is null', () => {
    mountPageList().then(() => {
      const vm = Cypress.vueWrapper.findComponent(PageList).vm
      expect(vm.filter.view).to.equal('tree')
      expect(vm.filter.trashed).to.equal('WITHOUT')
      expect(vm.filter.publish).to.be.null
    })
  })
})
