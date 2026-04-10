import FileAiDialog from '../../../js/components/FileAiDialog.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
  FileListItems: { template: '<div class="file-list-stub" />' },
}

function mountDialog(props = {}, perms = {}) {
  return cy.mount(FileAiDialog, {
    props: {
      modelValue: true,
      ...props,
    },
    global: { stubs },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('FileAiDialog', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the dialog when modelValue is true', () => {
    mountDialog()
    cy.get('.v-dialog').should('exist')
  })

  it('shows "Create image" as the title', () => {
    mountDialog()
    cy.contains('Create image').should('exist')
  })

  it('renders a close button', () => {
    mountDialog()
    cy.get('button[title="Close"]').should('exist')
  })

  it('emits update:modelValue when close is clicked', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(FileAiDialog, {
      props: { modelValue: true, 'onUpdate:modelValue': onUpdate },
      global: { stubs },
    })
    cy.get('button[title="Close"]').click()
    cy.get('@update').should('have.been.calledWith', false)
  })

  it('renders a textarea for image description', () => {
    mountDialog()
    cy.get('textarea').should('exist')
  })

  it('renders the "New image" button', () => {
    mountDialog()
    cy.contains('.v-btn', 'New image').should('exist')
  })

  it('disables the "New image" button when textarea is empty', () => {
    mountDialog()
    cy.contains('.v-btn', 'New image').should('be.disabled')
  })

  it('shows dictate button with audio:transcribe permission', () => {
    mountDialog({}, { 'audio:transcribe': true })
    cy.get('button[title="Dictate"]').should('exist')
  })

  it('hides dictate button without audio:transcribe permission', () => {
    mountDialog()
    cy.get('button[title="Dictate"]').should('not.exist')
  })

  it('renders the FileListItems stub for selecting images', () => {
    mountDialog()
    cy.get('.file-list-stub').should('exist')
  })

  it('shows "Select images" tab', () => {
    mountDialog()
    cy.contains('Select images').should('exist')
  })
})
