import FileField from '../../../js/fields/File.vue'
import { useUserStore } from '../../../js/stores'

const fileAsset = {
  disk: 'public',
  id: '1',
  name: 'document.pdf',
  path: '/files/document.pdf',
  mime: 'application/pdf',
  editor: 'admin',
  updated_at: '2024-01-01T00:00:00Z',
  description: { en: 'Test document' },
  previews: {},
}

const stubs = {
  FileDialog: { template: '<div />' },
  FileUrlDialog: { template: '<div />' },
  FileListItems: { template: '<div />' },
  FileDetail: { template: '<div />' },
}

function mountFile(props = {}, perms = {}, apollo = {}) {
  return cy.mount(FileField, {
    props: { config: {}, assets: {}, ...props },
    global: {
      stubs,
      mocks: {
        $apollo: {
          query: () => Promise.resolve({ data: {} }),
          mutate: () => Promise.resolve({ data: {} }),
          ...apollo,
        },
      },
    },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('File', () => {
  it('renders the file container', () => {
    mountFile()
    cy.get('.files').should('exist')
  })

  it('shows upload and URL buttons when no file is loaded', () => {
    mountFile()
    cy.get('button.btn-upload').should('exist')
    cy.get('button.btn-add-url').should('exist')
  })

  it('offers public-by-default page access protection', () => {
    mountFile({}, { 'file:relocate': true }).then(({ wrapper }) => {
      expect(wrapper.findComponent(FileField).vm.protect).to.equal(false)
    })
    cy.contains('Protect access').should('exist')
  })

  it('hides page access protection without file:relocate permission', () => {
    mountFile({ label: 'Download' })

    cy.get('.field-name').should('contain', 'Download')
    cy.get('.protect').should('not.exist')
    cy.contains('Protect access').should('not.exist')
  })

  it('places protection after the field name in the field label', () => {
    mountFile({ label: 'Download' }, { 'file:relocate': true })

    cy.get('.field-protect.label').within(() => {
      cy.get('.field-name').should('contain', 'Download')
      cy.get('.protect').should('contain', 'Protect access')
    })
    cy.get('.field-name').should('have.css', 'flex-basis', '50%')
    cy.get('.protect').should('have.css', 'justify-content', 'flex-end')
    cy.get('.protect > .protect-label + .v-switch').should('exist')
  })

  it('shows a lock before the label for protected files', () => {
    mountFile({
      label: 'Download',
      modelValue: { id: '1', type: 'file' },
      assets: { '1': { ...fileAsset, disk: 'private' } },
    })

    cy.get('.field-label > .field-lock + span').should('contain', 'Download')
  })

  it('replaces the protection switch with a spinner while loading', () => {
    let width

    mountFile({}, { 'file:relocate': true }).then(({ wrapper }) => {
      cy.get('.protect .v-switch').then(($switch) => {
        width = $switch[0].getBoundingClientRect().width
        wrapper.findComponent(FileField).vm.protecting = true
      })
    })

    cy.get('.protect').should('contain', 'Protect access')
    cy.get('.protect .v-progress-circular').should(($spinner) => {
      expect($spinner[0].getBoundingClientRect().width).to.equal(width)
    })
    cy.get('.protect .v-switch').should('not.exist')
  })

  it('relocates an existing file when protection is enabled', () => {
    const mutate = cy.stub().resolves({
      data: {
        relocateFile: [{
          id: fileAsset.id,
          disk: 'private',
          editor: 'admin',
          updated_at: '2024-01-02T00:00:00Z',
        }],
      },
    })

    mountFile(
      {
        modelValue: { id: '1', type: 'file' },
        assets: { '1': fileAsset },
      },
      { 'file:relocate': true },
      { mutate },
    ).then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileField).vm

      return vm.setProtect(true).then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.equal({
          id: ['1'],
          disk: 'private',
        })
        expect(vm.file.disk).to.equal('private')
        expect(vm.file.path).to.equal(fileAsset.path)
        expect(vm.file.previews).to.deep.equal(fileAsset.previews)
      })
    })
  })

  it('shows "Add file" button when user has file:view permission', () => {
    mountFile({}, { 'file:view': true })
    cy.get('button.btn-add').should('exist')
  })

  it('hides "Add file" button without file:view permission', () => {
    mountFile()
    cy.get('button.btn-add').should('not.exist')
  })

  it('shows file name when loaded via assets', () => {
    mountFile({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': fileAsset },
    })
    cy.contains('document.pdf').should('exist')
  })

  it('shows file metadata when file is present', () => {
    mountFile({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': fileAsset },
    })
    cy.get('.meta').should('exist')
    cy.contains('application/pdf').should('exist')
    cy.contains('admin').should('exist')
  })

  it('shows file description from first locale', () => {
    mountFile({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': fileAsset },
    })
    cy.contains('Test document').should('exist')
  })

  it('hides action buttons in readonly mode when no file', () => {
    mountFile({ readonly: true })
    cy.get('button.btn-upload').should('not.exist')
    cy.get('button.btn-add-url').should('not.exist')
  })

  it('adds readonly class in readonly mode', () => {
    mountFile({ readonly: true })
    cy.get('.files').should('have.class', 'readonly')
  })

  it('hides overlay menu in readonly mode when file is present', () => {
    mountFile({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': fileAsset },
      readonly: true,
    })
    cy.get('.btn-overlay').should('not.exist')
  })

  it('emits error:true when config.required and no file', () => {
    const onError = cy.spy().as('error')
    mountFile({ config: { required: true }, onError })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when config.required and file is present', () => {
    const onError = cy.spy().as('error')
    mountFile({
      config: { required: true },
      modelValue: { id: '1', type: 'file' },
      assets: { '1': fileAsset },
      onError,
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:false when not required and no file', () => {
    const onError = cy.spy().as('error')
    mountFile({ config: {}, onError })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('shows the file icon SVG when file is loaded', () => {
    mountFile({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': fileAsset },
    })
    cy.get('.file svg').should('exist')
  })
})
