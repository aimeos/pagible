import FileField from '../../../js/fields/File.vue'
import { useUserStore } from '../../../js/stores'

const fileAsset = {
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

function mountFile(props = {}, perms = {}) {
  return cy.mount(FileField, {
    props: { config: {}, assets: {}, ...props },
    global: { stubs },
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
    cy.get('button[title="Upload file"]').should('exist')
    cy.get('button[title="Add file from URL"]').should('exist')
  })

  it('shows "Add file" button when user has file:view permission', () => {
    mountFile({}, { 'file:view': true })
    cy.get('button[title="Add file"]').should('exist')
  })

  it('hides "Add file" button without file:view permission', () => {
    mountFile()
    cy.get('button[title="Add file"]').should('not.exist')
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
    cy.get('button[title="Upload file"]').should('not.exist')
    cy.get('button[title="Add file from URL"]').should('not.exist')
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
