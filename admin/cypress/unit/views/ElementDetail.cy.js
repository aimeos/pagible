import ElementDetail from '../../../js/views/ElementDetail.vue'
import { useUserStore } from '../../../js/stores'

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

function mountDetail(perms = {}, item = {}) {
  return cy.mount(ElementDetail, {
    props: { item: { ...baseItem, ...item } },
    global: {
      stubs,
      provide: {
        closeView: () => {},
      },
      plugins: [{
        install() {
          const user = useUserStore()
          user.me = { permission: perms }
        }
      }],
    },
  })
}

describe('ElementDetail', () => {
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
    cy.contains('.v-tab', 'Element').should('have.class', 'v-tab--selected')
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
    cy.get('button[title="View history"]').should('exist')
  })

  it('renders the aside toggle button', () => {
    mountDetail()
    cy.get('button[title="Toggle side menu"]').should('exist')
  })

  it('renders the AsideMeta stub', () => {
    mountDetail()
    cy.get('.aside-meta-stub').should('exist')
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

    it('uses warning color on save button when hasConflict is true', () => {
      mountDetail({ 'element:save': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(ElementDetail).vm
        vm.changed = { editor: 'x', data: { name: { previous: 'a', current: 'b', overwritten: 'c' } } }
        vm.dirty = true
        cy.get('.menu-save').should('have.class', 'bg-warning')
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
