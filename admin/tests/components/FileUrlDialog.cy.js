import FileUrlDialog from '../../js/components/FileUrlDialog.vue'

const stubs = {
}

function mountDialog(props = {}) {
  return cy.mount(FileUrlDialog, {
    props: {
      modelValue: true,
      ...props,
    },
    global: { stubs },
  })
}

describe('FileUrlDialog', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the dialog when modelValue is true', () => {
    mountDialog()
    cy.get('.v-dialog').should('exist')
  })

  it('shows "Add files from URLs" as the title', () => {
    mountDialog()
    cy.contains('Add files from URLs').should('exist')
  })

  it('renders a close button', () => {
    mountDialog()
    cy.get('button[title="Close"]').should('exist')
  })

  it('emits update:modelValue when close is clicked', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(FileUrlDialog, {
      props: { modelValue: true, 'onUpdate:modelValue': onUpdate },
      global: { stubs },
    })
    cy.get('button[title="Close"]').click({ force: true })
    cy.get('@update').should('have.been.calledWith', false)
  })

  it('renders a text field for single URL input by default', () => {
    mountDialog()
    cy.get('.v-text-field').should('exist')
    cy.get('.v-textarea').should('not.exist')
  })

  it('renders a textarea for multiple URL input', () => {
    mountDialog({ multiple: true })
    cy.get('textarea').should('exist')
  })

  it('shows placeholder text for single mode', () => {
    mountDialog()
    cy.get('input').should('have.attr', 'placeholder', 'Enter URL')
  })

  it('shows placeholder text for multiple mode', () => {
    mountDialog({ multiple: true })
    cy.get('textarea').should('have.attr', 'placeholder', 'Enter one URL per line')
  })

  it('does not show add button when no items are validated', () => {
    mountDialog()
    cy.contains('.v-btn', 'Add file').should('not.exist')
  })
})
