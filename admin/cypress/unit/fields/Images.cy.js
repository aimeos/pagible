import { h, ref } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
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

  it('moves images added during a running protection change afterwards', () => {
    const assets = { '1': { ...imageAssets['1'] } }
    const resolvers = []
    const mutate = cy.stub().callsFake(({ variables }) => new Promise((resolve) => {
      resolvers.push(() => resolve({
        data: { relocateFile: variables.id.map((id) => ({ id, disk: variables.disk })) },
      }))
    }))

    mountImages(
      { modelValue: [{ id: '1', type: 'file' }], assets },
      { 'file:relocate': true },
      { mutate },
    ).then(({ wrapper }) => {
      const vm = wrapper.findComponent(ImagesField).vm
      const first = vm.setProtect(true)

      vm.images.push({ ...imageAssets['2'] })
      vm.setProtect(true)
      expect(mutate).to.have.been.calledOnce

      resolvers[0]()

      cy.wrap(mutate).should('have.been.calledTwice').then(() => {
        expect(mutate.secondCall.args[0].variables).to.deep.equal({ id: ['2'], disk: 'private' })
        resolvers[1]()
      })
      cy.wrap(first).then(() => {
        expect(vm.images.every((item) => item.disk === 'private')).to.equal(true)
      })
    })
  })

  it('keeps the public state for images added while making them public', () => {
    const assets = { '1': { ...imageAssets['1'], disk: 'private' } }
    const resolvers = []
    const mutate = cy.stub().callsFake(({ variables }) => new Promise((resolve) => {
      resolvers.push(() => resolve({
        data: { relocateFile: variables.id.map((id) => ({ id, disk: variables.disk })) },
      }))
    }))

    mountImages(
      { modelValue: [{ id: '1', type: 'file' }], assets },
      { 'file:relocate': true },
      { mutate },
    ).then(({ wrapper }) => {
      const vm = wrapper.findComponent(ImagesField).vm
      const move = vm.setProtect(false)

      // the files are still private until the move finishes
      vm.select({ ...imageAssets['2'], id: '3', disk: 'private' })
      expect(vm.protect).to.equal(false)

      resolvers[0]()

      cy.wrap(move).then(() => {
        expect(vm.protect).to.equal(false)
        expect(mutate).to.have.been.calledOnce
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
        expect(query.firstCall.args[0].variables).to.deep.equal({ id: ['1', '2'], first: 2 })
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

  it('shows the images of a value replaced from outside', () => {
    const value = ref([{ id: '1', type: 'file' }])
    const onUpdate = cy.spy().as('update')
    const Host = {
      render: () => h(ImagesField, { modelValue: value.value, config: {}, assets: imageAssets, 'onUpdate:modelValue': onUpdate })
    }

    cy.mount(Host)
    cy.get('.image').should('have.length', 1)
    cy.then(() => { value.value = [{ id: '2', type: 'file' }, { id: '1', type: 'file' }] })
    cy.get('.image').should('have.length', 2)
    cy.then(() => { value.value = [] })
    cy.get('.image').should('have.length', 0)
    cy.get('@update').should('not.have.been.called')
  })

  it('persists images dragged from another images field', () => {
    const onUpdate = cy.spy().as('update')
    const onAddFile = cy.spy().as('addFile')

    mountImages({
      modelValue: [{ id: '1', type: 'file' }],
      assets: imageAssets,
      'onUpdate:modelValue': onUpdate,
      onAddFile
    }).then(({ wrapper }) => {
      const field = wrapper.findComponent(ImagesField)

      // vue-draggable-plus updates the list before the add event is emitted
      field.vm.images.push(imageAssets['2'])
      field.findComponent(VueDraggable).vm.$emit('add', { newDraggableIndex: 1 })
    })
    cy.get('@addFile').should('have.been.calledWith', imageAssets['2'])
    cy.get('@update').should('have.been.calledWith', [{ id: '1', type: 'file' }, { id: '2', type: 'file' }])
  })

  it('persists images dragged to another images field', () => {
    const onUpdate = cy.spy().as('update')
    const onRemoveFile = cy.spy().as('removeFile')

    mountImages({
      modelValue: [{ id: '1', type: 'file' }, { id: '2', type: 'file' }],
      assets: imageAssets,
      'onUpdate:modelValue': onUpdate,
      onRemoveFile
    }).then(({ wrapper }) => {
      const field = wrapper.findComponent(ImagesField)

      field.vm.images.splice(0, 1)
      field.findComponent(VueDraggable).vm.$emit('remove', { oldDraggableIndex: 0 })
    })
    cy.get('@update').should('have.been.calledWith', [{ id: '2', type: 'file' }])
    // the file may still be used by the other field
    cy.get('@removeFile').should('not.have.been.called')
  })

  it('renders v-img for each loaded image', () => {
    mountImages({
      modelValue: [{ id: '1', type: 'file' }],
      assets: imageAssets,
    })
    cy.get('.image .v-img').should('have.length', 1)
  })

  it('uses a native button for each editable image preview', () => {
    mountImages({
      modelValue: [{ id: '1', type: 'file' }],
      assets: imageAssets,
    })

    cy.get('.image').should('not.have.attr', 'role')
    cy.get('.image > button.image-preview').should('have.attr', 'aria-label', 'Edit')
    cy.get('.image .btn-overlay').parents('button.image-preview').should('not.exist')
  })

  it('removes a failed upload placeholder and releases its preview', () => {
    cy.stub(URL, 'createObjectURL').returns('blob:upload')
    const revokeObjectURL = cy.stub(URL, 'revokeObjectURL')
    const mutate = cy.stub().rejects(new Error('Upload failed'))

    mountImages({}, { 'file:add': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(ImagesField).vm
      const file = new File(['image'], 'image.jpg', { type: 'image/jpeg' })

      return vm.add([file]).then(() => {
        expect(revokeObjectURL).to.have.been.calledOnceWith('blob:upload')
        expect(vm.images).to.deep.equal([])
      })
    })
  })

  it('emits only uploaded images without pending uploads', () => {
    mountImages({ modelValue: [{ id: '1', type: 'file' }], assets: imageAssets }).then(({ wrapper }) => {
      const comp = wrapper.findComponent(ImagesField)

      comp.vm.images.push({ disk: 'public', path: 'blob:pending', uploading: true })
      comp.vm.change()

      expect(comp.emitted('update:modelValue').at(-1)[0]).to.deep.equal([{ id: '1', type: 'file' }])
    })
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

  it('emits error:true when empty and config.required is set', () => {
    const onError = cy.spy().as('error')
    mountImages({
      config: { required: true },
      modelValue: [],
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

  it('keeps value IDs whose files are not loaded yet', () => {
    const onUpdate = cy.spy().as('update')

    mountImages({
      modelValue: [{ id: '1', type: 'file' }, { id: '3', type: 'file' }, { id: '2', type: 'file' }],
      assets: imageAssets,
      'onUpdate:modelValue': onUpdate
    }).then(({ wrapper }) => {
      expect(wrapper.findComponent(ImagesField).vm.missing).to.deep.equal(['3'])
      wrapper.findComponent(ImagesField).vm.remove(0)
    })

    cy.get('.image').should('have.length', 1)
    cy.get('@update').should('have.been.calledWith', [{ id: '3', type: 'file' }, { id: '2', type: 'file' }])
  })

  it('keeps not loaded files after the image preceding them', () => {
    const onUpdate = cy.spy().as('update')

    mountImages({
      modelValue: [{ id: '1', type: 'file' }, { id: '3', type: 'file' }, { id: '2', type: 'file' }],
      assets: imageAssets,
      'onUpdate:modelValue': onUpdate
    })

    cy.get('.image .image-preview').first().focus().trigger('keydown', { key: 'ArrowRight', altKey: true })
    cy.get('@update').should('have.been.calledOnceWith', [
      { id: '2', type: 'file' }, { id: '1', type: 'file' }, { id: '3', type: 'file' }
    ])
  })

  it('shows files when their assets are loaded later', () => {
    const assets = ref({ '1': imageAssets['1'] })
    const onUpdate = cy.spy().as('update')
    const value = [{ id: '1', type: 'file' }, { id: '2', type: 'file' }]
    const Host = {
      render: () => h(ImagesField, { modelValue: value, config: {}, assets: assets.value, 'onUpdate:modelValue': onUpdate })
    }

    cy.mount(Host)
    cy.get('.image').should('have.length', 1)
    cy.then(() => { assets.value = imageAssets })
    cy.get('.image').should('have.length', 2)
    cy.get('@update').should('not.have.been.called')
  })

  it('moves images with Alt and the arrow keys', () => {
    const onUpdate = cy.spy().as('update')

    mountImages({
      modelValue: [{ id: '1', type: 'file' }, { id: '2', type: 'file' }],
      assets: imageAssets,
      'onUpdate:modelValue': onUpdate,
    })

    cy.get('.image .image-preview').first().should('have.attr', 'aria-keyshortcuts', 'Alt+ArrowLeft Alt+ArrowRight Alt+ArrowUp Alt+ArrowDown')
      .focus().trigger('keydown', { key: 'ArrowRight', altKey: true })
    cy.get('@update').should('have.been.calledOnceWith', [{ id: '2', type: 'file' }, { id: '1', type: 'file' }])
    cy.get('.image .image-preview').last().should('have.focus')

    // the first image can't move further back
    cy.get('.image .image-preview').first().trigger('keydown', { key: 'ArrowLeft', altKey: true })
    cy.get('.image .image-preview').first().trigger('keydown', { key: 'ArrowRight' })
    cy.get('@update').should('have.been.calledOnce')
  })

  it('moves images by a row of the grid with Alt and the up/down keys', () => {
    const onUpdate = cy.spy().as('update')
    const assets = {}
    const value = []

    for (let i = 1; i <= 12; i++) {
      assets[i] = { ...imageAssets['1'], id: String(i), path: `/files/${i}.jpg` }
      value.push({ id: String(i), type: 'file' })
    }

    mountImages({ modelValue: value, assets, 'onUpdate:modelValue': onUpdate })

    cy.get('.image').then(($images) => {
      const perRow = [...$images].filter((el) => el.offsetTop === $images[0].offsetTop).length
      expect(perRow).to.be.lessThan(12)

      cy.get('.image .image-preview').first().focus().trigger('keydown', { key: 'ArrowDown', altKey: true })
      cy.get('@update').should('have.been.calledOnce').then(() => {
        const ids = onUpdate.firstCall.args[0].map((entry) => entry.id)
        expect(ids.indexOf('1')).to.equal(perRow)
      })
    })
  })
})
