import ImagesField from '../../src/fields/Images.vue'
import { useUserStore } from '../../src/stores'

const imageAssets = {
  '1': { id: '1', name: 'a.jpg', path: '/files/a.jpg', mime: 'image/jpeg', previews: { '500': '/files/a-500.jpg' } },
  '2': { id: '2', name: 'b.jpg', path: '/files/b.jpg', mime: 'image/jpeg', previews: { '500': '/files/b-500.jpg' } },
}

const stubs = {
  FileDialog: { template: '<div />' },
  FileUrlDialog: { template: '<div />' },
  FileAiDialog: { template: '<div />' },
  FileListItems: { template: '<div />' },
  FileDetail: { template: '<div />' },
}

function mountImages(props = {}, perms = {}) {
  return cy.mount(ImagesField, {
    props: { config: {}, assets: {}, ...props },
    global: { stubs },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('Images', () => {
  it('renders the images container', () => {
    mountImages()
    cy.get('.images').should('exist')
  })

  it('shows add buttons area when no images and not readonly', () => {
    mountImages()
    cy.get('.add').should('exist')
  })

  it('shows upload button', () => {
    mountImages()
    cy.get('.add button[title="Add files"]').should('exist')
  })

  it('shows "Add files from URLs" button', () => {
    mountImages()
    cy.get('button[title="Add files from URLs"]').should('exist')
  })

  it('shows "Add files" select button with file:view permission', () => {
    mountImages({}, { 'file:view': true })
    cy.get('.add button[title="Add files"]').first().should('exist')
  })

  it('shows "Create file" button with image:imagine permission', () => {
    mountImages({}, { 'image:imagine': true })
    cy.get('button[title="Create file"]').should('exist')
  })

  it('hides "Create file" button without image:imagine permission', () => {
    mountImages()
    cy.get('button[title="Create file"]').should('not.exist')
  })

  it('renders images from modelValue and assets', () => {
    mountImages({
      modelValue: [{ id: '1', type: 'file' }, { id: '2', type: 'file' }],
      assets: imageAssets,
    })
    cy.get('.image').should('have.length', 2)
  })

  it('renders v-img for each loaded image', () => {
    mountImages({
      modelValue: [{ id: '1', type: 'file' }],
      assets: imageAssets,
    })
    cy.get('.image .v-img').should('have.length', 1)
  })

  it('hides add area in readonly mode', () => {
    mountImages({ readonly: true })
    cy.get('.add').should('not.exist')
  })

  it('hides overlay menus in readonly mode', () => {
    mountImages({
      modelValue: [{ id: '1', type: 'file' }],
      assets: imageAssets,
      readonly: true,
    })
    cy.get('.btn-overlay').should('not.exist')
  })

  it('emits error:true when below config.min', () => {
    const onError = cy.spy().as('error')
    mountImages({
      config: { min: 2 },
      modelValue: [{ id: '1', type: 'file' }],
      assets: imageAssets,
      onError,
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when meeting config.min', () => {
    const onError = cy.spy().as('error')
    mountImages({
      config: { min: 1 },
      modelValue: [{ id: '1', type: 'file' }],
      assets: imageAssets,
      onError,
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when exceeding config.max', () => {
    const onError = cy.spy().as('error')
    mountImages({
      config: { max: 1 },
      modelValue: [{ id: '1', type: 'file' }, { id: '2', type: 'file' }],
      assets: imageAssets,
      onError,
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when within config.max', () => {
    const onError = cy.spy().as('error')
    mountImages({
      config: { max: 3 },
      modelValue: [{ id: '1', type: 'file' }],
      assets: imageAssets,
      onError,
    })
    cy.get('@error').should('have.been.calledWith', false)
  })
})
