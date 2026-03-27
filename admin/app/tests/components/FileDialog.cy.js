import FileDialog from '../../src/components/FileDialog.vue'

const stubs = {
  FileListItems: { template: '<div class="file-list-stub" />' },
}

function mountDialog(props = {}) {
  return cy.mount(FileDialog, {
    props: {
      modelValue: true,
      ...props,
    },
    global: { stubs },
  })
}

describe('FileDialog', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the dialog when modelValue is true', () => {
    mountDialog()
    cy.get('.v-dialog').should('exist')
  })

  it('shows "Files" as the title', () => {
    mountDialog()
    cy.contains('Files').should('exist')
  })

  it('renders a close button', () => {
    mountDialog()
    cy.get('button[title="Close"]').should('exist')
  })

  it('emits update:modelValue when close is clicked', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(FileDialog, {
      props: { modelValue: true, 'onUpdate:modelValue': onUpdate },
      global: { stubs },
    })
    cy.get('button[title="Close"]').click()
    cy.get('@update').should('have.been.calledWith', false)
  })

  it('renders the FileListItems stub', () => {
    mountDialog()
    cy.get('.file-list-stub').should('exist')
  })
})
