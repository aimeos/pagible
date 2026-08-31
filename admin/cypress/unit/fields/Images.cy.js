import ImagesField from '../../../js/fields/Images.vue'
import { useUserStore } from '../../../js/stores'

const imageAssets = {
  '1': { disk: 'public', id: '1', name: 'a.jpg', path: '/files/a.jpg', mime: 'image/jpeg', previews: { '500': '/files/a-500.jpg' } },
  '2': { disk: 'public', id: '2', name: 'b.jpg', path: '/files/b.jpg', mime: 'image/jpeg', previews: { '500': '/files/b-500.jpg' } },
}

const stubs = {
  FileDialog: { template: '<div />' },
  FileUrlDialog: { template: '<div />' },
  FileAiDialog: { template: '<div />' },
  FileListItems: { template: '<div />' },
  FileDetail: { template: '<div />' },
}

function mountImages(props = {}, perms = {}, apollo = {}) {
  return cy.mount(ImagesField, {
    props: { config: {}, assets: {}, ...props },
    global: {
      stubs,
      mocks: {
        $apollo: {
          query: () => Promise.resolve({ data: {} }),
          mutate: () => Promise.resolve({ data: {} }),
          provider: { defaultClient: { cache: { evict() {}, gc() {} } } },
          ...apollo,
        },
      },
    },
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
    cy.get('.add button.btn-upload').should('exist')
  })

  it('offers public-by-default page access protection', () => {
    mountImages({}, { 'file:relocate': true }).then(({ wrapper }) => {
      expect(wrapper.findComponent(ImagesField).vm.protect).to.equal(false)
    })
    cy.contains('Protect access').should('exist')
  })

  it('shows a lock when the field contains a protected image', () => {
    mountImages({
      label: 'Gallery',
      modelValue: [{ id: '1', type: 'file' }, { id: '2', type: 'file' }],
      assets: {
        ...imageAssets,
        '2': { ...imageAssets['2'], disk: 'private' },
      },
    })

    cy.get('.field-label > .field-lock + span').should('contain', 'Gallery')
  })

  it('relocates every selected image when protection is enabled', () => {
    const assets = {
      '1': { ...imageAssets['1'] },
      '2': { ...imageAssets['2'] },
    }
    const onAddFile = cy.spy()
    const mutate = cy.stub().callsFake(({ variables }) =>
      Promise.resolve({
        data: {
          relocateFile: variables.id.map((id) => ({
            id,
            disk: variables.disk,
            editor: 'admin',
            updated_at: '2024-01-02T00:00:00Z',
          })),
        },
      }),
    )

    mountImages(
      {
        modelValue: [{ id: '1', type: 'file' }, { id: '2', type: 'file' }],
        assets,
        onAddFile,
      },
      { 'file:relocate': true },
      { mutate },
    ).then(({ wrapper }) => {
      const vm = wrapper.findComponent(ImagesField).vm

      return vm.setProtect(true).then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.equal({
          id: ['1', '2'],
          disk: 'private',
        })
        expect(vm.images.every((item) => item.disk === 'private')).to.equal(true)
        expect(vm.images.map((item) => item.path)).to.deep.equal(['/files/a.jpg', '/files/b.jpg'])
        expect(vm.images[0].previews).to.deep.equal(imageAssets['1'].previews)
        expect(onAddFile).to.have.been.calledOnce
        expect(onAddFile.firstCall.args[0].map((item) => item.id)).to.deep.equal(['1', '2'])
      })
    })
  })

  it('reloads every image after a partly failed protection change', () => {
    const assets = {
      '1': { ...imageAssets['1'] },
      '2': { ...imageAssets['2'] },
    }
    const onAddFile = cy.spy()
    const mutate = cy.stub().rejects(new Error('Unable to relocate every file'))
    const query = cy.stub().resolves({
      data: {
        files: {
          data: [
            { id: '1', disk: 'private', editor: 'admin', updated_at: '2024-01-02T00:00:00Z' },
            { id: '2', disk: 'public', editor: 'admin', updated_at: '2024-01-01T00:00:00Z' },
          ],
        },
      },
    })

    mountImages(
      {
        modelValue: [{ id: '1', type: 'file' }, { id: '2', type: 'file' }],
        assets,
        onAddFile,
      },
      { 'file:relocate': true },
      { mutate, query },
    ).then(({ wrapper }) => {
      const vm = wrapper.findComponent(ImagesField).vm

      return vm.setProtect(true).then(() => {
        expect(query).to.have.been.calledOnce
        expect(query.firstCall.args[0].variables).to.deep.equal({ id: ['1', '2'] })
        expect(vm.images.map((item) => item.disk)).to.deep.equal(['private', 'public'])
        expect(vm.images.map((item) => item.path)).to.deep.equal(['/files/a.jpg', '/files/b.jpg'])
        expect(vm.protect).to.equal(false)
        expect(onAddFile).to.have.been.calledOnce
      })
    })

    cy.get('.image .v-img').should('have.length', 2)
  })

  it('shows "Add files from URLs" button', () => {
    mountImages()
    cy.get('button.btn-add-urls').should('exist')
  })

  it('shows "Add files" select button with file:view permission', () => {
    mountImages({}, { 'file:view': true })
    cy.get('.add button.btn-add').should('exist')
  })

  it('shows "Create file" button with image:imagine permission', () => {
    mountImages({}, { 'image:imagine': true })
    cy.get('button.btn-create').should('exist')
  })

  it('hides "Create file" button without image:imagine permission', () => {
    mountImages()
    cy.get('button.btn-create').should('not.exist')
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
