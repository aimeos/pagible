import PageDetail from '../../../js/views/PageDetail.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
  AsideMeta: { template: '<div class="aside-meta-stub" />' },
  AsideCount: { template: '<div class="aside-count-stub" />' },
  HistoryDialog: { template: '<div class="history-dialog-stub" />' },
  PageDetailItem: { template: '<div class="page-detail-item-stub" />' },
  PageDetailEditor: { template: '<div class="page-detail-editor-stub" />' },
  PageDetailContent: { template: '<div class="page-detail-content-stub" />' },
  PageDetailMetrics: { template: '<div class="page-detail-metrics-stub" />' },
}

const baseItem = {
  id: '1',
  name: 'Test Page',
  title: 'Test Title',
  path: '/test',
  lang: 'en',
  status: 1,
  cache: 5,
  domain: '',
  tag: '',
  to: '',
  type: '',
  theme: '',
  content: [],
  config: {},
  meta: {},
  published: false,
}

function mountDetail(perms = {}, item = {}) {
  return cy.mount(PageDetail, {
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

describe('PageDetail', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the app bar', () => {
    mountDetail()
    cy.get('.v-app-bar').should('exist')
  })

  it('shows "Page: <name>" in the title', () => {
    mountDetail({}, { name: 'About Us' })
    cy.get('.v-app-bar-title').should('contain', 'Page').and('contain', 'About Us')
  })

  it('renders the back button', () => {
    mountDetail()
    cy.get('button[title="Back to list view"]').should('exist')
  })

  it('renders Editor, Content, and Page tabs', () => {
    mountDetail()
    cy.contains('.v-tab', 'Editor').should('exist')
    cy.contains('.v-tab', 'Content').should('exist')
    cy.contains('.v-tab', 'Page').should('exist')
  })

  it('shows Metrics tab when page:metrics permission is granted', () => {
    mountDetail({ 'page:metrics': true })
    cy.contains('.v-tab', 'Metrics').should('exist')
  })

  it('hides Metrics tab without page:metrics permission', () => {
    mountDetail({})
    cy.contains('.v-tab', 'Metrics').should('not.exist')
  })

  it('disables save button without page:save permission', () => {
    mountDetail({})
    cy.get('.menu-save').should('be.disabled')
  })

  it('disables save button when nothing has changed', () => {
    mountDetail({ 'page:save': true })
    cy.get('.menu-save').should('be.disabled')
  })

  it('disables publish button without page:publish permission', () => {
    mountDetail({})
    cy.get('.menu-publish').first().should('be.disabled')
  })

  it('shows translate button with text:translate permission', () => {
    mountDetail({ 'text:translate': true })
    cy.get('button[title="Translate page"]').should('exist')
  })

  it('hides translate button without text:translate permission', () => {
    mountDetail({})
    cy.get('button[title="Translate page"]').should('not.exist')
  })

  it('renders the history button', () => {
    mountDetail()
    cy.get('button[title="View history"]').should('exist')
  })

  describe('computed properties', () => {
    it('hasChanged is false by default', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        expect(vm.hasChanged).to.be.false
      })
    })

    it('hasError is false by default', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        expect(vm.hasError).to.be.false
      })
    })

    it('hasChanged is true when changed has a truthy entry', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.dirty = { content: true }
        expect(vm.hasChanged).to.be.true
      })
    })

    it('hasError is true when errors has a truthy entry', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.errors = { page: true }
        expect(vm.hasError).to.be.true
      })
    })
  })

  describe('update()', () => {
    it('sets changed flag for the given key', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.update('content', [])
        expect(vm.dirty.content).to.be.true
      })
    })

    it('assigns value to item when key is "page"', () => {
      mountDetail({}, { name: 'Old' }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.update('page', { name: 'New' })
        expect(vm.item.name).to.equal('New')
        expect(vm.dirty.page).to.be.true
      })
    })
  })

  describe('fileIds()', () => {
    it('returns empty array when content/meta/config have no files', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        expect(vm.fileIds()).to.deep.equal([])
      })
    })

    it('collects file IDs from content, meta, and config', () => {
      const item = {
        content: [
          { files: ['f1', 'f2'] },
          { files: ['f3'] },
        ],
        meta: { seo: { files: ['f4'] } },
        config: { theme: { files: ['f5'] } },
      }
      mountDetail({}, item).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        const ids = vm.fileIds()
        expect(ids).to.include('f1')
        expect(ids).to.include('f4')
        expect(ids).to.include('f5')
      })
    })

    it('deduplicates file IDs', () => {
      const item = {
        content: [{ files: ['f1', 'f2'] }, { files: ['f1'] }],
      }
      mountDetail({}, item).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        const ids = vm.fileIds()
        expect(ids.filter(id => id === 'f1')).to.have.length(1)
      })
    })
  })

  describe('files()', () => {
    it('returns an object with parsed previews/description/transcription', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        const result = vm.files([
          { id: 'f1', previews: '{"100":"thumb.jpg"}', description: '{}', transcription: '{}' },
        ])
        expect(result.f1.previews).to.deep.equal({ 100: 'thumb.jpg' })
      })
    })
  })

  describe('obsolete()', () => {
    it('removes file IDs not present in assets', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.assets = { f1: {} }
        const content = [{ files: ['f1', 'f2', 'f3'] }]
        const result = vm.obsolete(content)
        expect(result[0].files).to.deep.equal(['f1'])
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
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.changed = { editor: 'x', data: { title: { previous: 'a', current: 'b' } } }
        cy.get('.menu-changed').should('exist')
      })
    })

    it('shows changes button even when all conflicts are resolved', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.changed = { editor: 'x', data: { title: { previous: 'a', current: 'b', overwritten: 'c', resolved: 'c' } } }
        cy.get('.menu-changed').should('exist')
      })
    })

    it('adds warning class to save button when hasConflict is true', () => {
      mountDetail({ 'page:save': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.changed = { editor: 'x', data: { title: { previous: 'a', current: 'b', overwritten: 'c' } } }
        vm.dirty = { page: true }
        cy.get('.menu-save').should('have.class', 'warning')
      })
    })

    it('does not add warning class when no conflicts', () => {
      mountDetail({ 'page:save': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.dirty = { page: true }
        cy.get('.menu-save').should('not.have.class', 'warning')
      })
    })

    it('hasConflict is false when all conflicts are resolved', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.changed = { editor: 'x', data: { title: { previous: 'a', current: 'b', overwritten: 'c', resolved: 'c' } } }
        expect(vm.hasConflict).to.be.false
      })
    })

    it('hasConflict is true when overwritten exists without resolved', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(PageDetail).vm
        vm.changed = { editor: 'x', data: { title: { previous: 'a', current: 'b', overwritten: 'c' } } }
        expect(vm.hasConflict).to.be.true
      })
    })
  })
})
