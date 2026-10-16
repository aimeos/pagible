import PageList from '../../../js/views/PageList.vue'
import { useChangeStore, useDrawerStore, useLanguageStore, useUserStore } from '../../../js/stores'

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
    function mountLangs(query, aside = true) {
      return cy.mount(PageList, {
        global: {
          stubs,
          mocks: { $apollo: { query } },
          plugins: [{
            install() {
              useDrawerStore().aside = aside
              useLanguageStore().available = ['en', 'de']
              const user = useUserStore()
              user.me = { permission: { 'page:view': true }, email: 'test@test.com', settings: { page: { lang: 'de' } } }
            }
          }],
        },
      })
    }

    it('shows the translation states with their counts of the current language', () => {
      const query = cy.stub().resolves({ data: { pageTranslationStates: [{ stale: 2, missing: 3, ai: 1 }] } })

      mountLangs(query).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageList).vm

        cy.wrap(null).should(() => {
          expect(query).to.have.been.calledWithMatch({ variables: { langs: ['de'] }, fetchPolicy: 'no-cache' })
          const group = vm.asideContent.find((group) => group.key === 'translation')
          expect(group.items.map((item) => item.value.translation)).to.deep.equal([null, 'stale', 'missing', 'ai'])
          expect(group.items.map((item) => item.count)).to.deep.equal([undefined, 2, 3, 1])
        })
      })
    })

    it('refetches the counts when the language changes', () => {
      const query = cy.stub().resolves({ data: { pageTranslationStates: [{ stale: 0, missing: 0, ai: 0 }] } })

      mountLangs(query).then(() => {
        useUserStore().saveData('page', 'lang', 'en')
        cy.wrap(null).should(() => {
          expect(query).to.have.been.calledWithMatch({ variables: { langs: ['en'] } })
        })
      })
    })

    it('fetches the counts only when the aside is opened', () => {
      const query = cy.stub().resolves({ data: { pageTranslationStates: [{ stale: 0, missing: 0, ai: 0 }] } })

      mountLangs(query, false).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageList).vm

        useUserStore().saveData('page', 'lang', 'en')
        cy.wait(600).then(() => {
          expect(query).not.to.have.been.called
          expect(vm.outdated).to.equal(true)
          useDrawerStore().aside = true
        })
        cy.wrap(null).should(() => {
          expect(query).to.have.been.calledOnce
          expect(query).to.have.been.calledWithMatch({ variables: { langs: ['en'] } })
        })
      })
    })

    it('collapses bursts of changes into one query', () => {
      const query = cy.stub().resolves({ data: { pageTranslationStates: [{ stale: 0, missing: 0, ai: 0 }] } })

      mountLangs(query).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageList).vm

        vm.changed()
        vm.changed()
        vm.changed()
        cy.wait(600).then(() => {
          expect(query).to.have.been.calledOnce
        })
      })
    })

    it('throttles the counts refreshed due to frequent changes', () => {
      const query = cy.stub().resolves({ data: { pageTranslationStates: [{ stale: 0, missing: 0, ai: 0 }] } })

      mountLangs(query).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageList).vm

        cy.wrap(null).should(() => expect(query).to.have.been.calledOnce)
        cy.then(() => {
          // the counts have just been fetched, so frequent changes wait
          vm.changed(true)
          vm.changed(true)
        })
        cy.wait(600).then(() => {
          expect(query).to.have.been.calledOnce
          // fetched long enough ago, the pending refresh runs soon using cached counts
          clearTimeout(vm.throttled)
          vm.throttled = null
          vm.fetchedAt = Date.now() - 20000
          vm.changed(true)
          vm.changed(true)
        })
        cy.wrap(null).should(() => {
          expect(query).to.have.been.calledTwice
          expect(query.secondCall).to.have.been.calledWithMatch({ variables: { langs: ['de'], cached: true } })
        })
      })
    })

    it('refreshes the counts at once after other changes while throttled', () => {
      const query = cy.stub().resolves({ data: { pageTranslationStates: [{ stale: 0, missing: 0, ai: 0 }] } })

      mountLangs(query).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageList).vm

        cy.wrap(null).should(() => expect(query).to.have.been.calledOnce)
        cy.then(() => {
          vm.changed(true)
          vm.changed()
          expect(vm.throttled).to.equal(null)
        })
        cy.wrap(null).should(() => {
          expect(query).to.have.been.calledTwice
          expect(query.secondCall).to.have.been.calledWithMatch({ variables: { cached: false } })
        })
      })
    })

    it('does not refetch the counts after returning without changed pages', () => {
      const query = cy.stub().resolves({ data: { pageTranslationStates: [{ stale: 0, missing: 0, ai: 0 }] } })

      mountLangs(query).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageList).vm

        cy.wrap(null).should(() => expect(query).to.have.been.calledOnce)
        cy.then(() => {
          PageList.deactivated.call(vm)
          PageList.activated.call(vm)
        })
        cy.wait(600).then(() => {
          expect(query).to.have.been.calledOnce
          expect(vm.outdated).to.equal(false)
        })
      })
    })

    it('refetches the counts after returning when pages changed while inactive', () => {
      const query = cy.stub().resolves({ data: { pageTranslationStates: [{ stale: 0, missing: 0, ai: 0 }] } })

      mountLangs(query).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageList).vm

        cy.wrap(null).should(() => expect(query).to.have.been.calledOnce)
        cy.then(() => {
          PageList.deactivated.call(vm)
          useChangeStore().notify('page', { id: 'page-1' })
        })
        cy.wait(600).then(() => {
          expect(query).to.have.been.calledOnce
          expect(vm.outdated).to.equal(true)
          PageList.activated.call(vm)
        })
        cy.wrap(null).should(() => expect(query).to.have.been.calledTwice)
      })
    })

    it('cancels a pending query when deactivated and repeats it after returning', () => {
      const query = cy.stub().resolves({ data: { pageTranslationStates: [{ stale: 0, missing: 0, ai: 0 }] } })

      mountLangs(query).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageList).vm

        cy.wrap(null).should(() => expect(query).to.have.been.calledOnce)
        cy.then(() => {
          vm.changed()
          PageList.deactivated.call(vm)
        })
        cy.wait(600).then(() => {
          expect(query).to.have.been.calledOnce
          expect(vm.outdated).to.equal(true)
          PageList.activated.call(vm)
        })
        cy.wrap(null).should(() => expect(query).to.have.been.calledTwice)
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
