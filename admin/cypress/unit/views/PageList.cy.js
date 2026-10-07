import PageList from '../../../js/views/PageList.vue'
import { useLanguageStore, useUserStore } from '../../../js/stores'

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

  it('shows AI prompt textarea when page:chat permission is granted', () => {
    mountPageList({ 'page:chat': true })
    cy.get('.prompt').should('exist')
  })

  it('hides AI prompt textarea without page:chat permission', () => {
    mountPageList({})
    cy.get('.prompt').should('not.exist')
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

  describe('translation filter', () => {
    function mountLangs(query) {
      return cy.mount(PageList, {
        global: {
          stubs,
          mocks: { $apollo: { query } },
          plugins: [{
            install() {
              useLanguageStore().available = ['en', 'de']
              const user = useUserStore()
              user.me = { permission: { 'page:view': true }, email: 'test@test.com', settings: { page: { lang: 'de' } } }
            }
          }],
        },
      })
    }

    it('shows the translation states with their counts of the current language', () => {
      const query = cy.stub().resolves({ data: { pageTranslations: { stale: 2, missing: 3, ai: 1 } } })

      mountLangs(query).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageList).vm

        cy.wrap(null).should(() => {
          expect(query).to.have.been.calledWithMatch({ variables: { lang: 'de' }, fetchPolicy: 'no-cache' })
          const group = vm.asideContent.find((group) => group.key === 'translation')
          expect(group.items.map((item) => item.value.translation)).to.deep.equal([null, 'stale', 'missing', 'ai'])
          expect(group.items.map((item) => item.count)).to.deep.equal([undefined, 2, 3, 1])
        })
      })
    })

    it('refetches the counts when the language changes', () => {
      const query = cy.stub().resolves({ data: { pageTranslations: { stale: 0, missing: 0, ai: 0 } } })

      mountLangs(query).then(() => {
        useUserStore().saveData('page', 'lang', 'en')
        cy.wrap(null).should(() => {
          expect(query).to.have.been.calledWithMatch({ variables: { lang: 'en' } })
        })
      })
    })

    it('hides the translation filter with one language', () => {
      mountPageList().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageList).vm
        expect(vm.asideContent.find((group) => group.key === 'translation')).to.equal(undefined)
        expect(vm.defaults.translation).to.equal(null)
      })
    })
  })
})
