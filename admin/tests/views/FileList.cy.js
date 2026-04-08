import FileList from '../../src/views/FileList.vue'
import { useUserStore, useDrawerStore } from '../../src/stores'

const stubs = {
  FileListItems: { template: '<div class="file-list-items-stub" />' },
  FileDetail: { template: '<div class="file-detail-stub" />' },
  Navigation: { template: '<div class="navigation-stub" />' },
  AsideList: { template: '<div class="aside-list-stub" />' },
  User: { template: '<div class="user-stub" />' },
}

function mountFileList(perms = {}) {
  return cy.mount(FileList, {
    global: {
      stubs,
      provide: {
        locales: () => [
          { value: 'en', title: 'English (EN)' },
          { value: 'de', title: 'Deutsch (DE)' },
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

describe('FileList', () => {
  it('renders the file list view', () => {
    mountFileList()
    cy.get('.v-app-bar').should('exist')
  })

  it('shows "Files" in the app bar title', () => {
    mountFileList()
    cy.get('.v-app-bar-title').should('contain', 'Files')
  })

  it('renders the navigation toggle button', () => {
    mountFileList()
    cy.get('.v-app-bar button').first().should('exist')
  })

  it('renders the aside toggle button', () => {
    mountFileList()
    cy.get('button[title="Toggle side menu"]').should('exist')
  })

  it('renders the User stub', () => {
    mountFileList()
    cy.get('.user-stub').should('exist')
  })

  it('renders the Navigation stub', () => {
    mountFileList()
    cy.get('.navigation-stub').should('exist')
  })

  it('renders the FileListItems stub', () => {
    mountFileList()
    cy.get('.file-list-items-stub').should('exist')
  })

  it('initializes filter from settings', () => {
    cy.mount(FileList, {
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
              settings: { file: { filter: { publish: 'DRAFT', editor: 'me@test.com' } } }
            }
          }
        }],
      },
    }).then(() => {
      const vm = Cypress.vueWrapper.findComponent(FileList).vm
      expect(vm.filter.publish).to.equal('DRAFT')
      expect(vm.filter.editor).to.equal('me@test.com')
      expect(vm.filter.trashed).to.equal('WITHOUT')
    })
  })

  it('uses default filter when settings is null', () => {
    mountFileList().then(() => {
      const vm = Cypress.vueWrapper.findComponent(FileList).vm
      expect(vm.filter.trashed).to.equal('WITHOUT')
      expect(vm.filter.publish).to.be.null
    })
  })
})
