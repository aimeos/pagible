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

  it('shows suggestion chips in the empty state', () => {
    mountDialog()
    cy.contains('.v-chip', 'Create a landing page for a new product').should('exist')
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

  it('shows the dictate button with audio:transcribe permission', () => {
    mountDialog({}, { 'audio:transcribe': true })
    cy.get('button[aria-label="Dictate"]').should('exist')
  })

  it('hides the dictate button without audio:transcribe permission', () => {
    mountDialog()
    cy.get('button[aria-label="Dictate"]').should('not.exist')
  })
})
