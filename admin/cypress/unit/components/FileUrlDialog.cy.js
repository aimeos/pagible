import FileUrlDialog from '../../../js/components/FileUrlDialog.vue'

const stubs = {
}

function mountDialog(props = {}, apollo = {}) {
  return cy.mount(FileUrlDialog, {
    props: {
      modelValue: true,
      ...props,
    },
    global: {
      stubs,
      mocks: {
        $apollo: {
          mutate: () => Promise.resolve({ data: {} }),
          ...apollo,
        },
      },
    },
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
    cy.get('button[aria-label="Close"]').should('exist')
  })

  it('emits update:modelValue when close is clicked', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(FileUrlDialog, {
      props: { modelValue: true, 'onUpdate:modelValue': onUpdate },
      global: { stubs },
    })
    cy.get('button[aria-label="Close"]').click({ force: true })
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

  it('creates URL files directly on the selected disk', () => {
    const mutate = cy.stub().resolves({
      data: {
        addFile: {
          id: '1',
          disk: 'private',
          name: 'document.pdf',
          path: 'cms/test/file/document.pdf',
          previews: '{}',
        },
      },
    })

    mountDialog({ disk: 'private' }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileUrlDialog).vm
      vm.items = {
        file: {
          name: 'document.pdf',
          path: 'https://example.com/document.pdf',
        },
      }
      vm.add()
    })

    cy.wrap(mutate).should('have.been.calledOnce')
    cy.wrap(mutate).should((stub) => {
      expect(stub.firstCall.args[0].variables).to.deep.equal({
        disk: 'private',
        input: {
          name: 'document.pdf',
          path: 'https://example.com/document.pdf',
        },
      })
    })
  })
})
