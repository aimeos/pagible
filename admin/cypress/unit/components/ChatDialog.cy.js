import ChatDialog from '../../../js/components/ChatDialog.vue'
import { useUserStore } from '../../../js/stores'

function mountDialog(props = {}, perms = {}) {
  return cy
    .mount(ChatDialog, {
      props: {
        modelValue: true,
        ...props,
      },
    })
    .then(() => {
      const user = useUserStore()
      user.me = { id: '42', permission: perms }
    })
}

describe('ChatDialog', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
    cy.viewport(1280, 900) // the 80vh chat dialog needs a realistic viewport to be actionable
  })

  it('renders the dialog when modelValue is true', () => {
    mountDialog()
    cy.get('.v-dialog').should('exist')
  })

  it('shows the AI Assistant title', () => {
    mountDialog()
    cy.contains('AI Assistant').should('exist')
  })

  it('emits update:modelValue when close is clicked', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(ChatDialog, {
      props: { modelValue: true, 'onUpdate:modelValue': onUpdate },
    })
    cy.get('button[aria-label="Close"]').click()
    cy.get('@update').should('have.been.calledWith', false)
  })

  it('renders the message input textarea', () => {
    mountDialog()
    cy.get('textarea').should('exist')
  })

  it('shows the empty state prompt', () => {
    mountDialog()
    cy.contains('.chat-empty', 'What shall I do for you?').should('exist')
  })

  it('uses blue for AI messages and green for user messages', () => {
    mountDialog()
    cy.then(() => {
      const vm = Cypress.vueWrapper.findComponent(ChatDialog).vm
      vm.messages = [
        { id: 1, role: 'assistant', content: 'How can I help?', blocks: ['How can I help?'] },
        { id: 2, role: 'user', content: 'Create a landing page' },
      ]
    })
    cy.get('.chat-row.assistant .chat-avatar').should('have.class', 'text-primary')
    cy.get('.chat-row.user .chat-avatar').should('have.class', 'text-success')
    cy.get('.chat-row.user').should('have.css', '--v-activated-opacity', '0.33')
  })

  it('disables the send button when the input is empty', () => {
    mountDialog()
    cy.get('button[aria-label="Send"]').should('be.disabled')
  })

  it('enables the send button once text is entered', () => {
    mountDialog()
    // force past Vuetify's .v-field overlay covering the textarea center (autofocused on open)
    cy.get('textarea').first().type('Create a page about cats', { force: true })
    cy.get('button[aria-label="Send"]').should('not.be.disabled')
  })

  it('navigates user input history with the up and down cursor keys', () => {
    mountDialog()
    cy.then(() => {
      const vm = Cypress.vueWrapper.findComponent(ChatDialog).vm
      vm.messages = [
        { id: 1, role: 'user', content: 'Create the first page' },
        { id: 2, role: 'assistant', content: 'Done', blocks: ['Done'] },
        { id: 3, role: 'user', content: 'Create the second page' },
      ]
    })
    cy.get('textarea')
      .first()
      .type('Keep this draft', { force: true })
      .type('{uparrow}', { force: true })
      .should('have.value', 'Create the second page')
      .type('{uparrow}', { force: true })
      .should('have.value', 'Create the first page')
      .type('{uparrow}', { force: true })
      .should('have.value', 'Create the first page')
      .type('{downarrow}', { force: true })
      .should('have.value', 'Create the second page')
      .type('{downarrow}', { force: true })
      .should('have.value', 'Keep this draft')
  })

  it('shows the dictate button with audio:transcribe permission', () => {
    mountDialog({}, { 'audio:transcribe': true })
    cy.get('button[aria-label="Dictate"]').should('exist')
  })

  it('hides the dictate button without audio:transcribe permission', () => {
    mountDialog()
    cy.get('button[aria-label="Dictate"]').should('not.exist')
  })

  it('renders the open streaming block as markdown, not raw text', () => {
    // A streamed answer with single newlines (no blank-line boundary) stays in the open trailing
    // block (m.pending) for the whole stream. It must render live as markdown rather than printing
    // raw markdown like "*   *Is ...*" until the block closes.
    mountDialog()
    cy.then(() => {
      const vm = Cypress.vueWrapper.findComponent(ChatDialog).vm
      vm.messages = [
        {
          id: 1,
          role: 'assistant',
          content: '',
          blocks: [],
          pending: 'Intro line\n*   *Is PagibleAI free to use?* (self-hosted).',
          streaming: true,
        },
      ]
    })
    cy.get('.chat-bubble ul li em').should('contain', 'Is PagibleAI free to use?')
    cy.get('.chat-bubble').should('not.contain', '*   ')
  })
})
