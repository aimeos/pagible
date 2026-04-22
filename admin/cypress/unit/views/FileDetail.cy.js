import FileDetail from '../../../js/views/FileDetail.vue'
import { useUserStore, useMessageStore } from '../../../js/stores'

const stubs = {
  AsideMeta: { template: '<div class="aside-meta-stub" />' },
  HistoryDialog: { template: '<div class="history-dialog-stub" />' },
  FileDetailItem: { template: '<div class="file-detail-item-stub" />' },
  FileDetailRefs: { template: '<div class="file-detail-refs-stub" />' },
}

const baseItem = {
  id: '1',
  name: 'photo.jpg',
  path: 'images/photo.jpg',
  mime: 'image/jpeg',
  lang: 'en',
  previews: {},
  description: {},
  transcription: {},
  published: false,
}

function mountDetail(perms = {}, item = {}) {
  return cy.mount(FileDetail, {
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

describe('FileDetail', () => {
  it('renders the app bar', () => {
    mountDetail()
    cy.get('.v-app-bar').should('exist')
  })

  it('shows "File: <name>" in the title', () => {
    mountDetail({}, { name: 'logo.png' })
    cy.get('.v-app-bar-title').should('contain', 'File').and('contain', 'logo.png')
  })

  it('renders the back button', () => {
    mountDetail()
    cy.get('button[title="Back to list view"]').should('exist')
  })

  it('renders the File and Used by tabs', () => {
    mountDetail()
    cy.contains('.v-tab', 'File').should('exist')
    cy.contains('.v-tab', 'Used by').should('exist')
  })

  it('shows the File tab as active by default', () => {
    mountDetail()
    cy.contains('.v-tab', 'File').should('have.class', 'v-tab--selected')
  })

  it('renders the FileDetailItem stub', () => {
    mountDetail()
    cy.get('.file-detail-item-stub').should('exist')
  })

  it('renders the AsideMeta stub', () => {
    mountDetail()
    cy.get('.aside-meta-stub').should('exist')
  })

  it('disables save button without file:save permission', () => {
    mountDetail({})
    cy.get('.menu-save').should('be.disabled')
  })

  it('disables save button when nothing has changed', () => {
    mountDetail({ 'file:save': true })
    cy.get('.menu-save').should('be.disabled')
  })

  it('disables publish button without file:publish permission', () => {
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

  describe('reset()', () => {
    it('clears changed and error flags', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.dirty = true
        vm.error = true
        vm.reset()
        expect(vm.dirty).to.be.false
        expect(vm.error).to.be.false
      })
    })
  })

  describe('use()', () => {
    it('assigns version data to item and sets changed to true', () => {
      mountDetail().then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.use({ data: { name: 'updated.jpg', path: 'new/path.jpg' } })
        expect(vm.item.name).to.equal('updated.jpg')
        expect(vm.dirty).to.be.true
        expect(vm.vhistory).to.be.false
      })
    })
  })

  describe('save()', () => {
    it('returns false when permission is denied', () => {
      mountDetail({}).then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.save().then(result => {
          expect(result).to.be.false
        })
      })
    })

    it('returns false when there are errors', () => {
      mountDetail({ 'file:save': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.error = true
        vm.dirty = true
        vm.save().then(result => {
          expect(result).to.be.false
        })
      })
    })

    it('returns true when nothing has changed', () => {
      mountDetail({ 'file:save': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.save().then(result => {
          expect(result).to.be.true
        })
      })
    })
  })

  describe('versions()', () => {
    it('returns empty array without file:view permission', () => {
      mountDetail({}).then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.versions('1').then(result => {
          expect(result).to.deep.equal([])
        })
      })
    })

    it('returns empty array when id is falsy', () => {
      mountDetail({ 'file:view': true }).then(() => {
        const vm = Cypress.vueWrapper.findComponent(FileDetail).vm
        vm.versions('').then(result => {
          expect(result).to.deep.equal([])
        })
      })
    })
  })
})
