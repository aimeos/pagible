import ImageField from '../../../js/fields/Image.vue'
import { useUserStore } from '../../../js/stores'

const imageAsset = {
  id: '1',
  name: 'photo.jpg',
  path: 'files/photo.jpg',
  mime: 'image/jpeg',
  editor: 'admin',
  updated_at: '2024-01-01T00:00:00Z',
  description: { en: 'Test photo' },
  previews: { '500': 'files/photo-500.jpg' },
}

const stubs = {
  FileDialog: { template: '<div />' },
  FileUrlDialog: { template: '<div />' },
  FileAiDialog: { template: '<div />' },
  FileListItems: { template: '<div />' },
  FileDetail: { template: '<div />' },
}

function mountImage(props = {}, perms = {}) {
  return cy.mount(ImageField, {
    props: { config: {}, assets: {}, ...props },
    global: { stubs },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('Image', () => {
  it('renders the file container', () => {
    mountImage()
    cy.get('.files').should('exist')
  })

  it('shows upload and URL buttons when no file is loaded', () => {
    mountImage()
    cy.get('button[title="Upload file"]').should('exist')
    cy.get('button[title="Add file from URL"]').should('exist')
  })

  it('shows "Add file" button with file:view permission', () => {
    mountImage({}, { 'file:view': true })
    cy.get('button[title="Add file"]').should('exist')
  })

  it('shows "Create file" button with image:imagine permission', () => {
    mountImage({}, { 'image:imagine': true })
    cy.get('button[title="Create file"]').should('exist')
  })

  it('hides "Create file" button without image:imagine permission', () => {
    mountImage()
    cy.get('button[title="Create file"]').should('not.exist')
  })

  it('renders v-img when image file is loaded', () => {
    mountImage({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': imageAsset },
    })
    cy.get('.v-img').should('exist')
  })

  it('sets image src via url() inject', () => {
    mountImage({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': imageAsset },
    })
    cy.get('.v-img img').should('have.attr', 'src', '/storage/files/photo.jpg')
  })

  it('shows metadata when file is present', () => {
    mountImage({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': imageAsset },
    })
    cy.get('.meta').should('exist')
    cy.contains('photo.jpg').should('exist')
    cy.contains('image/jpeg').should('exist')
  })

  it('hides action buttons in readonly mode', () => {
    mountImage({ readonly: true })
    cy.get('button[title="Upload file"]').should('not.exist')
    cy.get('button[title="Add file from URL"]').should('not.exist')
    cy.get('button[title="Create file"]').should('not.exist')
  })

  it('hides overlay menu in readonly mode with file present', () => {
    mountImage({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': imageAsset },
      readonly: true,
    })
    cy.get('.btn-overlay').should('not.exist')
  })

  it('emits error:true when required and no file', () => {
    const onError = cy.spy().as('error')
    mountImage({ config: { required: true }, onError })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when required and file is present', () => {
    const onError = cy.spy().as('error')
    mountImage({
      config: { required: true },
      modelValue: { id: '1', type: 'file' },
      assets: { '1': imageAsset },
      onError,
    })
    cy.get('@error').should('have.been.calledWith', false)
  })
})
