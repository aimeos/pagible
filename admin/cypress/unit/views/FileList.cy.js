import FileList from '../../../js/views/FileList.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
  FileListItems: {
    template: '<div class="file-list-items-stub" />',
    methods: { reload() {} },
  },
  FileDetail: { template: '<div class="file-detail-stub" />' },
  Navigation: { template: '<div class="navigation-stub" />' },
  AsideList: { template: '<div class="aside-list-stub" />' },
  User: { template: '<div class="user-stub" />' },
}

function mountFileList(perms = {}, filter = {}) {
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
          user.me = { permission: perms, email: 'test@test.com', settings: { file: { filter } } }
        }
      }],
    },
  })
}

describe('FileList', () => {
  beforeEach(() => {
    cy.viewport(1280, 900)
  })

  it('renders the file list view', () => {
    mountFileList()
    cy.get('.v-app-bar').should('exist')
  })

  it('shows "Media" in the app bar title', () => {
    mountFileList()
    cy.get('.v-app-bar-title').should('contain', 'Media')
  })

  it('renders the navigation toggle button', () => {
    mountFileList()
    cy.get('.v-app-bar button').first().should('exist')
  })

  it('renders the aside toggle button', () => {
    mountFileList()
    cy.get('button.btn-sidemenu').should('exist')
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

  it('shows the prompt with file:chat and hides it with only page:chat', () => {
    mountFileList({ 'file:chat': true })
    cy.get('.file-list .prompt').should('be.visible')
    cy.then(() => {
      useUserStore().me.permission = { 'page:chat': true }
    })
    cy.get('.file-list .prompt').should('not.exist')
  })

  it('submits file chat turns and refreshes once on close while preserving filters', () => {
    cy.intercept('POST', '**/cmsapi/chat', {
      headers: { 'content-type': 'text/plain' },
      body: 'Updated the file description',
    }).as('chat')

    mountFileList({ 'file:chat': true }, { publish: 'DRAFT', lang: 'de' }).then(() => {
      const vm = Cypress.vueWrapper.findComponent(FileList).vm
      cy.spy(vm.$refs.filelist, 'reload').as('reload')
    })

    cy.get('.prompt textarea').first().type('  Describe the banner  {enter}')
    cy.wait('@chat').its('request.body').should((body) => {
      expect(body.prompt).to.equal('Describe the banner')
      expect(body.messages).to.deep.equal([])
      expect(body.context).to.contain('media file list')
    })
    cy.contains('.chat-bubble', 'Updated the file description').should('be.visible')
    cy.get('.chat-cursor').should('not.exist')
    cy.get('.prompt textarea').first().should('have.value', '')
    cy.get('@reload').should('not.have.been.called')

    // Vuetify's field overlay covers the autofocused textarea center in component tests.
    cy.get('.chat-input textarea').first().type('Translate it to German{enter}', { force: true })
    cy.wait('@chat').its('request.body').should((body) => {
      expect(body.prompt).to.equal('Translate it to German')
      expect(body.messages).to.deep.equal([
        { role: 'user', content: 'Describe the banner' },
        { role: 'assistant', content: 'Updated the file description' },
      ])
    })
    cy.get('.chat-bubble').should('have.length', 4)
    cy.get('.chat-cursor').should('not.exist')
    cy.get('button[aria-label="Close"]').click()
    cy.get('@reload').should('have.been.calledOnce')
    cy.then(() => {
      const vm = Cypress.vueWrapper.findComponent(FileList).vm
      expect(vm.filter.publish).to.equal('DRAFT')
      expect(vm.filter.lang).to.equal('de')
      expect(useUserStore().getData('file', 'filter')).to.include({ publish: 'DRAFT', lang: 'de' })
    })
  })

  it('keeps Shift+Enter and IME composition in the input and submits with the arrow', () => {
    cy.intercept('POST', '**/cmsapi/chat', {
      headers: { 'content-type': 'text/plain' },
      body: 'Found the banner',
    }).as('chat')
    mountFileList({ 'file:chat': true })
    cy.get('.prompt textarea').first()
      .type('Find files{shift+enter}with banners')
      .trigger('keydown', { key: 'Enter', isComposing: true })
      .should('have.value', 'Find files\nwith banners')
    cy.get('.v-dialog').should('not.exist')
    cy.get('.prompt button[title="Send"]').click()
    cy.wait('@chat').its('request.body.prompt').should('equal', 'Find files\nwith banners')
    cy.contains('.chat-bubble', 'Found the banner').should('be.visible')
  })

  it('opens an empty chat after clearing the prompt without reloading on close', () => {
    mountFileList({ 'file:chat': true }).then(() => {
      cy.spy(Cypress.vueWrapper.findComponent(FileList).vm.$refs.filelist, 'reload').as('reload')
    })
    cy.get('.prompt textarea').first().type('Find banners')
    cy.get('.prompt .v-field__clearable').click()
    cy.get('.prompt textarea').first().type('{enter}')
    cy.get('.chat-empty').should('be.visible')
    cy.get('button[aria-label="Close"]').click()
    cy.get('@reload').should('not.have.been.called')
  })

  it('refreshes when a stopped turn completes after closing the dialog', () => {
    mountFileList({ 'file:chat': true }).then(() => {
      cy.spy(Cypress.vueWrapper.findComponent(FileList).vm.$refs.filelist, 'reload').as('reload')
    })
    cy.get('.prompt textarea').first().type('{enter}')
    cy.get('button[aria-label="Close"]').click()
    cy.get('@reload').should('not.have.been.called')
    cy.then(() => {
      Cypress.vueWrapper.findComponent(FileList).vm.$refs.chat.$emit('done')
    })
    cy.get('@reload').should('have.been.calledOnce')
  })

  it('shows media help and gates dictation by audio:transcribe', () => {
    mountFileList({ 'file:chat': true })
    cy.get('.prompt button[title="Dictate"]').should('not.exist')
    cy.get('.prompt button[title="Show help"]').click()
    cy.get('#file-help').should('be.visible').and('contain', 'manage media files')
    cy.get('.prompt button[title="Hide help"]').click()
    cy.get('#file-help').should('not.exist')
    cy.then(() => {
      useUserStore().me.permission['audio:transcribe'] = true
    })
    cy.get('.prompt button[title="Dictate"]').should('be.visible')
  })
})
