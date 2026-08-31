import ElementDetail from '../../../js/views/ElementDetail.vue'
import { useSchemaStore, useUserStore } from '../../../js/stores'
import '../../../js/assets/base.css'

const stubs = {
  AsideMeta: { template: '<div class="aside-meta-stub" />' },
  HistoryDialog: { template: '<div class="history-dialog-stub" />' },
  ElementDetailRefs: { template: '<div class="element-detail-refs-stub" />' },
  ElementDetailItem: { template: '<div class="element-detail-item-stub" />' },
}

const baseItem = {
  id: '1',
  name: 'Test Element',
  type: 'heading',
  lang: 'en',
  data: {},
  files: [],
  published: false,
}

let schemaLoad

function mountDetail(perms = {}, item = {}, apollo = {}) {
  return cy.mount(ElementDetail, {
    props: { item: { ...baseItem, ...item } },
    global: {
      stubs,
      mocks: {
        $apollo: {
          query: () => Promise.resolve({ data: {} }),
          mutate: () => Promise.resolve({ data: {} }),
          provider: { defaultClient: { cache: { evict() {}, gc() {} } } },
          ...apollo,
        },
      },
      provide: {
        closeView: () => {},
      },
      plugins: [{
        install() {
          schemaLoad = Cypress.sinon.stub().resolves()
          useSchemaStore().load = schemaLoad

          const user = useUserStore()
          user.me = { permission: perms }
        }
      }],
    },
  })
}

describe('ElementDetail', () => {
  it('loads the element schemas', () => {
    mountDetail().then(() => {
      expect(schemaLoad).to.have.been.calledOnce
    })
  })

  it('renders the app bar', () => {
    mountDetail()
    cy.get('.v-app-bar').should('exist')
  })

  it('shows "Element: <name>" in the title', () => {
    mountDetail({}, { name: 'My Heading' })
    cy.get('.v-app-bar-title').should('contain', 'Element').and('contain', 'My Heading')
  })

  it('renders the back button', () => {
    mountDetail()
    cy.get('.v-app-bar button').first().should('exist')
  })

  it('renders the Element and Used by tabs', () => {
    mountDetail()
    cy.contains('.v-tab', 'Element').should('exist')
    cy.contains('.v-tab', 'Used by').should('exist')
  })

  it('shows the Element tab as active by default', () => {
    mountDetail()
    cy.contains('.detail-tabs .v-tab', 'Element')
      .should('have.class', 'v-tab--selected')
      .and('have.css', 'background-color', 'rgba(0, 0, 0, 0.08)')
    cy.get('.detail-tabs .v-tab__slider').should('not.exist')
  })

  it('renders the ElementDetailItem stub in the Element tab', () => {
    mountDetail()
    cy.get('.element-detail-item-stub').should('exist')
  })

  it('disables save button without element:save permission', () => {
    mountDetail({})
    cy.get('.menu-save').should('be.disabled')
  })

  it('disables save button when nothing changed even with permission', () => {
    mountDetail({ 'element:save': true })
    cy.get('.menu-save').should('be.disabled')
  })

  it('disables publish button without element:publish permission', () => {
    mountDetail({})
    cy.get('.menu-publish').first().should('be.disabled')
  })

  it('renders the history button', () => {
    mountDetail()
    cy.get('button.btn-history').should('exist')
  })

  it('invalidates element lists', () => {
    const evict = cy.stub()
    const gc = cy.stub()

    mountDetail({}, {}, {
      provider: { defaultClient: { cache: { evict, gc } } },
    }).then(() => {
      Cypress.vueWrapper.findComponent(ElementDetail).vm.invalidate()

      expect(evict).to.have.been.calledWith({ id: 'ROOT_QUERY', fieldName: 'elements' })
      expect(gc).to.have.been.calledOnce
    })
  })

  it('renders the aside toggle button', () => {
    mountDetail()
    cy.get('button.btn-sidemenu').should('exist')
  })

  it('renders the AsideMeta stub', () => {
    mountDetail()
    cy.get('.aside-meta-stub').should('exist')
  })

  it('uses latest attached file data with published fields as fallback', () => {
    mountDetail().then(() => {
      const vm = Cypress.vueWrapper.findComponent(ElementDetail).vm
      const result = vm.files([{
        disk: 'private',
        id: 'file-1',
        name: 'published.jpg',
        path: 'published.jpg',
        previews: '{"500":"published-500.webp"}',
        latest: {
          data: '{"name":"draft.jpg","path":"draft.jpg","previews":{"500":"draft-500.webp"}}',
          aux: '{}',
        },
      }])

      expect(result['file-1'].disk).to.equal('private')
      expect(result['file-1'].path).to.equal('draft.jpg')
      expect(result['file-1'].previews).to.deep.equal({ 500: 'draft-500.webp' })
    })
  })

  describe('publish menu', () => {
    it('renders one publish menu with both actions', () => {
      mountDetail({ 'element:publish': true })
      cy.get('.menu-publish').should('have.length', 1).click()
      cy.get('.menu-publish-now').should('contain', 'Publish')
      cy.get('.menu-publish-at').should('contain', 'Schedule')
    })

    it('opens menu with date and time pickers', () => {
      mountDetail({ 'element:publish': true })
      cy.get('.menu-publish').click()
      cy.get('.v-date-picker').should('exist')
      cy.get('.v-time-picker').should('exist')
    })

    it('disables publish-at action when no date selected', () => {
      mountDetail({ 'element:publish': true })
      cy.get('.menu-publish').click()
      cy.get('.menu-publish-at').should('be.disabled')
    })

    it('published() combines date and time', () => {
      mountDetail({ 'element:publish': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ElementDetail).vm
        vm.publishAt = new Date(2026, 5, 15)
        vm.publishTime = '14:30'
        cy.spy(vm, 'publish').as('publishSpy')
        vm.published()
        cy.get('@publishSpy').should('have.been.calledOnce').then(() => {
          const arg = vm.publish.args[0][0]
          expect(arg.getFullYear()).to.equal(2026)
          expect(arg.getMonth()).to.equal(5)
          expect(arg.getDate()).to.equal(15)
          expect(arg.getHours()).to.equal(14)
          expect(arg.getMinutes()).to.equal(30)
        })
      })
    })

    it('published() uses midnight when no time selected', () => {
      mountDetail({ 'element:publish': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ElementDetail).vm
        vm.publishAt = new Date(2026, 5, 15)
        vm.publishTime = null
        cy.spy(vm, 'publish').as('publishSpy')
        vm.published()
        cy.get('@publishSpy').should('have.been.calledOnce').then(() => {
          const arg = vm.publish.args[0][0]
          expect(arg.getHours()).to.equal(0)
          expect(arg.getMinutes()).to.equal(0)
        })
      })
    })
  })

  describe('conflict UI', () => {
    it('hides changes button when changed is null', () => {
      mountDetail()
      cy.get('.menu-changed').should('not.exist')
    })

    it('shows changes button when changed is set', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(ElementDetail).vm
        vm.changed = { editor: 'x', data: { name: { previous: 'a', current: 'b' } } }
        cy.get('.menu-changed').should('exist')
      })
    })

    it('shows changes button even when all conflicts are resolved', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(ElementDetail).vm
        vm.changed = { editor: 'x', data: { name: { previous: 'a', current: 'b', overwritten: 'c', resolved: 'c' } } }
        cy.get('.menu-changed').should('exist')
      })
    })

    it('uses warning class on save button when hasConflict is true', () => {
      mountDetail({ 'element:save': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ElementDetail).vm
        vm.changed = { editor: 'x', data: { name: { previous: 'a', current: 'b', overwritten: 'c' } } }
        vm.dirty = true
        cy.get('.menu-save').should('have.class', 'warning')
      })
    })

    it('hasConflict is false when all conflicts are resolved', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(ElementDetail).vm
        vm.changed = { editor: 'x', data: { name: { previous: 'a', current: 'b', overwritten: 'c', resolved: 'c' } } }
        expect(vm.hasConflict).to.be.false
      })
    })

    it('hasConflict is true when overwritten exists without resolved', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(ElementDetail).vm
        vm.changed = { editor: 'x', data: { name: { previous: 'a', current: 'b', overwritten: 'c' } } }
        expect(vm.hasConflict).to.be.true
      })
    })
  })
})
