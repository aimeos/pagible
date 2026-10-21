import ChatDialog from '../../../js/components/ChatDialog.vue'
import { useUserStore } from '../../../js/stores'
import '../../../js/assets/base.css'

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

  it('shows square buttons in the message input', () => {
    mountDialog({}, { 'audio:transcribe': true })
    cy.get('.chat-input .v-btn').should('have.length', 2).each(($btn) => {
      const { width, height } = $btn[0].getBoundingClientRect()
      expect(width, 'width').to.equal(height)
    })
  })

  it('renders the message input textarea', () => {
    mountDialog()
    cy.get('.chat-input textarea').first().should('have.attr', 'aria-label', 'Chat message')
  })

  it('shows the empty state prompt', () => {
    mountDialog()
    cy.contains('.chat-empty', 'What shall I do for you?').should('exist')
  })

  it('removes the dialog body padding', () => {
    mountDialog()
    cy.get('.dialog-body.chat-body').should('have.css', 'padding', '0px')
  })

  it('shows AI and user messages without avatars on opposite sides', () => {
    mountDialog()
    cy.then(() => {
      const vm = Cypress.vueWrapper.findComponent(ChatDialog).vm
      vm.messages = [
        { id: 1, role: 'assistant', content: 'How can I help?', blocks: ['How can I help?'] },
        { id: 2, role: 'user', content: 'Create a landing page' },
      ]
    })
    cy.get('.chat-avatar').should('not.exist')
    cy.get('.chat-row.user').should('have.css', 'justify-content', 'flex-end')
    cy.get('.chat-row.user .chat-bubble').should('have.css', 'background-image', 'none')
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

  it('moves the caret within multi-line input instead of recalling the history', () => {
    mountDialog()
    cy.then(() => {
      const vm = Cypress.vueWrapper.findComponent(ChatDialog).vm
      const event = (selectionStart, selectionEnd = selectionStart) => ({
        preventDefault: cy.spy(),
        target: { value: vm.input, selectionStart, selectionEnd },
      })

      vm.messages = [{ id: 1, role: 'user', content: 'Create the first page' }]
      vm.input = 'first line\nsecond line'

      // caret on the second line: up moves the caret
      let e = event(15)
      vm.recall(e, -1)
      expect(e.preventDefault).not.to.have.been.called
      expect(vm.input).to.equal('first line\nsecond line')

      // caret on the first line: up recalls the last message
      e = event(3)
      vm.recall(e, -1)
      expect(e.preventDefault).to.have.been.calledOnce
      expect(vm.input).to.equal('Create the first page')

      // caret on the last line: down returns to the draft
      e = event(vm.input.length)
      vm.recall(e, 1)
      expect(vm.input).to.equal('first line\nsecond line')

      // caret on the first line of the draft: down moves the caret
      vm.historyIndex = 0
      vm.input = 'first line\nsecond line'
      e = event(3)
      vm.recall(e, 1)
      expect(e.preventDefault).not.to.have.been.called
      expect(vm.input).to.equal('first line\nsecond line')
    })
  })

  it('labels the copy button of assistant messages', () => {
    mountDialog()
    cy.then(() => {
      Cypress.vueWrapper.findComponent(ChatDialog).vm.messages = [
        { id: 1, role: 'assistant', content: 'Done', blocks: ['Done'] },
      ]
    })
    cy.get('.chat-copy').should('have.attr', 'aria-label', 'Copy')
  })

  it('shows the dictate button with audio:transcribe permission', () => {
    mountDialog({}, { 'audio:transcribe': true })
    cy.get('button[aria-label="Dictate"]').should('exist')
  })

  it('hides the dictate button without audio:transcribe permission', () => {
    mountDialog()
    cy.get('button[aria-label="Dictate"]').should('not.exist')
  })

  it('stops the dictation when the dialog is closed', () => {
    mountDialog().then(({ wrapper }) => {
      const vm = wrapper.findComponent(ChatDialog).vm
      const stop = cy.stub(vm.dictation, 'stop')

      // the props of the wrapped component can't be changed, so the watcher is called directly
      vm.$options.watch.open.call(vm, false)
      expect(stop).to.have.been.calledOnce
    })
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

  it('shows the server error below the partial answer', () => {
    cy.intercept('POST', '**/cmsapi/chat', {
      headers: { 'content-type': 'text/plain' },
      body: 'Partial answer\u001EThe response took too long and was stopped.',
    }).as('chat')
    mountDialog({}, { 'page:chat': true })
    cy.then(() => Cypress.vueWrapper.findComponent(ChatDialog).vm.send('Create a page'))
    cy.wait('@chat')
    cy.get('.chat-bubble.error').should('contain', 'Partial answer')
    cy.get('.chat-bubble.error .chat-notice').should('have.text', 'The response took too long and was stopped.')
  })

  it('shows the interrupted notice if the end marker is missing', () => {
    cy.intercept('POST', '**/cmsapi/chat', {
      headers: { 'content-type': 'text/plain' },
      body: 'Partial answer',
    }).as('chat')
    mountDialog({}, { 'page:chat': true })
    cy.then(() => Cypress.vueWrapper.findComponent(ChatDialog).vm.send('Create a page'))
    cy.wait('@chat')
    cy.get('.chat-bubble.error .chat-notice').should('have.text', 'The response was interrupted and may be incomplete.')
  })

  it('shows no notice for a complete answer', () => {
    cy.intercept('POST', '**/cmsapi/chat', {
      headers: { 'content-type': 'text/plain' },
      body: 'Created the page\u001E',
    }).as('chat')
    mountDialog({}, { 'page:chat': true })
    cy.then(() => Cypress.vueWrapper.findComponent(ChatDialog).vm.send('Create a page'))
    cy.wait('@chat')
    cy.contains('.chat-bubble', 'Created the page').should('not.have.class', 'error')
    cy.get('.chat-notice').should('not.exist')
  })
})

