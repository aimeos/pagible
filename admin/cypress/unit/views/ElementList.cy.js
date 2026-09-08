import ElementList from '../../../js/views/ElementList.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
  ElementListItems: {
    template: '<div class="element-list-items-stub" />',
    methods: { reload() {} },
  },
  ElementDetail: { template: '<div class="element-detail-stub" />' },
  Navigation: { template: '<div class="navigation-stub" />' },
  AsideList: { template: '<div class="aside-list-stub" />' },
  User: { template: '<div class="user-stub" />' },
}

function mountElementList(perms = {}, filter = {}) {
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
          user.me = { permission: perms, email: 'test@test.com', settings: { element: { filter } } }
        }
      }],
    },
  })
}

describe('ElementList', () => {
  beforeEach(() => {
    cy.viewport(1280, 900)
  })

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

  it('shows the prompt with element:chat and hides it with only page or file chat permissions', () => {
    mountElementList({ 'element:chat': true })
    cy.get('.element-list .prompt').should('be.visible')
    cy.then(() => {
      useUserStore().me.permission = { 'page:chat': true, 'file:chat': true }
    })
    cy.get('.element-list .prompt').should('not.exist')
  })

  it('submits element chat turns and refreshes once on close while preserving filters', () => {
    cy.intercept('POST', '**/cmsapi/chat', {
      headers: { 'content-type': 'text/plain' },
      body: 'Updated the shared element content',
    }).as('chat')

    mountElementList({ 'element:chat': true }, { publish: 'DRAFT', lang: 'de' }).then(() => {
      const vm = Cypress.vueWrapper.findComponent(ElementList).vm
      cy.spy(vm.$refs.elementlist, 'reload').as('reload')
    })

    cy.get('.prompt textarea').first().type('  Update the footer  {enter}')
    cy.wait('@chat').its('request.body').should((body) => {
      expect(body.prompt).to.equal('Update the footer')
      expect(body.messages).to.deep.equal([])
      expect(body.context).to.contain('shared element list')
    })
    cy.contains('.chat-bubble', 'Updated the shared element content').should('be.visible')
    cy.get('.chat-cursor').should('not.exist')
    cy.get('.prompt textarea').first().should('have.value', '')
    cy.get('@reload').should('not.have.been.called')

    // Vuetify's field overlay covers the autofocused textarea center in component tests.
    cy.get('.chat-input textarea').first().type('Translate it to German{enter}', { force: true })
    cy.wait('@chat').its('request.body').should((body) => {
      expect(body.prompt).to.equal('Translate it to German')
      expect(body.messages).to.deep.equal([
        { role: 'user', content: 'Update the footer' },
        { role: 'assistant', content: 'Updated the shared element content' },
      ])
    })
    cy.get('.chat-bubble').should('have.length', 4)
    cy.get('.chat-cursor').should('not.exist')
    cy.get('button[aria-label="Close"]').click()
    cy.get('@reload').should('have.been.calledOnce')
    cy.then(() => {
      const vm = Cypress.vueWrapper.findComponent(ElementList).vm
      expect(vm.filter.publish).to.equal('DRAFT')
      expect(vm.filter.lang).to.equal('de')
      expect(useUserStore().getData('element', 'filter')).to.include({ publish: 'DRAFT', lang: 'de' })
    })
  })

  it('keeps Shift+Enter and IME composition in the input and submits with the arrow', () => {
    cy.intercept('POST', '**/cmsapi/chat', {
      headers: { 'content-type': 'text/plain' },
      body: 'Found the footer',
    }).as('chat')
    mountElementList({ 'element:chat': true })
    cy.get('.prompt textarea').first()
      .type('Find elements{shift+enter}with footers')
      .trigger('keydown', { key: 'Enter', isComposing: true })
      .should('have.value', 'Find elements\nwith footers')
    cy.get('.v-dialog').should('not.exist')
    cy.get('.prompt button[title="Send"]').click()
    cy.wait('@chat').its('request.body.prompt').should('equal', 'Find elements\nwith footers')
    cy.contains('.chat-bubble', 'Found the footer').should('be.visible')
  })

  it('opens an empty chat after clearing the prompt without reloading on close', () => {
    mountElementList({ 'element:chat': true }).then(() => {
      cy.spy(Cypress.vueWrapper.findComponent(ElementList).vm.$refs.elementlist, 'reload').as('reload')
    })
    cy.get('.prompt textarea').first().type('Find footers')
    cy.get('.prompt .v-field__clearable').click()
    cy.get('.prompt textarea').first().type('{enter}')
    cy.get('.chat-empty').should('be.visible')
    cy.get('button[aria-label="Close"]').click()
    cy.get('@reload').should('not.have.been.called')
  })

  it('refreshes when a stopped turn completes after closing the dialog', () => {
    mountElementList({ 'element:chat': true }).then(() => {
      cy.spy(Cypress.vueWrapper.findComponent(ElementList).vm.$refs.elementlist, 'reload').as('reload')
    })
    cy.get('.prompt textarea').first().type('{enter}')
    cy.get('button[aria-label="Close"]').click()
    cy.get('@reload').should('not.have.been.called')
    cy.then(() => {
      Cypress.vueWrapper.findComponent(ElementList).vm.$refs.chat.$emit('done')
    })
    cy.get('@reload').should('have.been.calledOnce')
  })

  it('shows element help and gates dictation by audio:transcribe', () => {
    mountElementList({ 'element:chat': true })
    cy.get('.prompt button[title="Dictate"]').should('not.exist')
    cy.get('.prompt button[title="Show help"]').click()
    cy.get('#element-help').should('be.visible').and('contain', 'manage shared elements')
    cy.get('.prompt button[title="Hide help"]').click()
    cy.get('#element-help').should('not.exist')
    cy.then(() => {
      useUserStore().me.permission['audio:transcribe'] = true
    })
    cy.get('.prompt button[title="Dictate"]').should('be.visible')
  })
})
