import FileDetailItemImage from '../../../js/components/FileDetailItemImage.vue'
import { useUserStore } from '../../../js/stores'
import '../../../js/assets/base.css'

const item = {
  id: '1',
  name: 'photo.jpg',
  path: 'files/photo.jpg',
  mime: 'image/jpeg',
  previews: {},
}

function mountImage(props = {}, perms = {}) {
  return cy.mount(FileDetailItemImage, {
    props: {
      item: { ...item },
      readonly: false,
      ...props,
    },
    global: {
      provide: {
        base64ToBlob: () => new Blob(),
        url: (path) => path,
      },
    },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('FileDetailItemImage', () => {
  it('renders the editor container', () => {
    mountImage()
    cy.get('.editor-container').should('exist')
  })

  it('renders the image element', () => {
    mountImage()
    cy.get('img.element').should('exist')
    cy.get('img.element').should('have.attr', 'src', '/storage/files/photo.jpg')
    cy.get('img.element').should('not.have.class', 'checkered')
  })

  it('shows toolbar when not readonly', () => {
    mountImage()
    cy.get('.toolbar').should('exist')
  })

  it('hides toolbar when readonly', () => {
    mountImage({ readonly: true })
    cy.get('.toolbar').should('not.exist')
  })

  it('renders crop button', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn.btn-crop').should('exist')
  })

  it('renders rotate buttons', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn.btn-rotate-ccw').should('exist')
    cy.get('.toolbar .v-btn.btn-rotate-cw').should('exist')
  })

  it('renders flip buttons', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn.btn-flip-h').should('exist')
    cy.get('.toolbar .v-btn.btn-flip-v').should('exist')
  })

  it('renders monochrome button', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn.btn-monochrome').should('exist')
  })

  it('renders download button', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn.btn-download').should('exist')
  })

  it('shows erase button with image:erase permission', () => {
    mountImage({}, { 'file:save': true, 'image:erase': true })
    cy.get('.toolbar .v-btn.btn-erase').should('exist')
  })

  it('hides erase button without image:erase permission', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn.btn-erase').should('not.exist')
  })

  it('shows isolate button with image:isolate permission', () => {
    mountImage({}, { 'file:save': true, 'image:isolate': true })
    cy.get('.toolbar .v-btn.btn-remove-bg').should('exist')
  })

  it('disables the other AI actions while one is running', () => {
    const perms = { 'file:save': true, 'image:isolate': true, 'image:uncrop': true, 'image:upscale': true }

    cy.mount(FileDetailItemImage, {
      props: { item: { ...item }, readonly: false },
      global: { provide: { base64ToBlob: () => new Blob(), url: (path) => path } }
    }).then(({ wrapper }) => {
      useUserStore().me = { permission: perms }
      wrapper.findComponent(FileDetailItemImage).vm.loading = { 'image:isolate': true }
    })

    cy.get('.toolbar .v-btn.btn-expand').should('be.disabled')
    cy.get('.toolbar .v-btn.btn-upscale').should('be.disabled')
    cy.get('.toolbar .v-btn.btn-remove-bg').should('have.class', 'v-btn--loading')
  })

  it('does not restore earlier edits or versions while an AI action is running', () => {
    const cropper = { replace: cy.stub(), destroy: cy.stub() }

    cy.mount(FileDetailItemImage, {
      props: { item: { ...item }, readonly: false },
      global: { provide: { base64ToBlob: () => new Blob(), url: (path) => path } }
    }).then(({ wrapper }) => {
      useUserStore().me = { permission: { 'file:save': true } }

      const vm = wrapper.findComponent(FileDetailItemImage).vm
      vm.loading = { 'image:isolate': true }
      vm.cropper = cropper
      vm.images = [{ blob: new Blob(['png']), url: 'blob:x' }]

      vm.replace(vm.images[0].blob, 0)
      vm.use([{ id: 'f1', path: 'files/old.png' }])

      expect(cropper.replace).not.to.have.been.called
      expect(vm.images).to.have.length(1)
      expect(wrapper.findComponent(FileDetailItemImage).emitted('use')).to.be.undefined
    })
  })

  it('blocks manual edits while an AI action is running', () => {
    const perms = { 'file:save': true, 'image:isolate': true }
    const cropper = { rotate: cy.stub(), scaleX: cy.stub(), scaleY: cy.stub(), getCroppedCanvas: cy.stub(), destroy: cy.stub() }

    cy.mount(FileDetailItemImage, {
      props: { item: { ...item }, readonly: false },
      global: { provide: { base64ToBlob: () => new Blob(), url: (path) => path } }
    }).then(({ wrapper }) => {
      useUserStore().me = { permission: perms }

      const vm = wrapper.findComponent(FileDetailItemImage).vm
      vm.loading = { 'image:isolate': true }
      vm.cropper = cropper

      vm.crop()
      vm.rotate(90)
      vm.flipX()
      vm.flipY()
      vm.monochrome()

      expect(cropper.rotate).not.to.have.been.called
      expect(cropper.scaleX).not.to.have.been.called
      expect(cropper.scaleY).not.to.have.been.called
      expect(cropper.getCroppedCanvas).not.to.have.been.called
    })

    for (const cls of ['btn-crop', 'btn-rotate-ccw', 'btn-rotate-cw', 'btn-flip-h', 'btn-flip-v', 'btn-monochrome']) {
      cy.get(`.toolbar .v-btn.${cls}`).should('be.disabled')
    }
  })

  it('disables the AI actions while the image is prepared', () => {
    const perms = { 'file:save': true, 'image:isolate': true, 'image:uncrop': true }

    cy.mount(FileDetailItemImage, {
      props: { item: { ...item }, readonly: false },
      global: { provide: { base64ToBlob: () => new Blob(), url: (path) => path } }
    }).then(({ wrapper }) => {
      useUserStore().me = { permission: perms }
      wrapper.findComponent(FileDetailItemImage).vm.preparing = true
    })

    cy.get('.toolbar .v-btn.btn-remove-bg').should('be.disabled')
    cy.get('.toolbar .v-btn.btn-expand').should('be.disabled')
    cy.get('.toolbar .v-btn.btn-rotate-cw').should('be.disabled')
  })

  it('reports images that can not be encoded instead of downloading them', () => {
    mountImage().then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileDetailItemImage).vm
      const add = cy.stub(vm.messages, 'add')
      const createUrl = cy.spy(URL, 'createObjectURL')

      vm.cropper = { getCroppedCanvas: () => ({ toBlob: (callback) => callback(null) }), destroy: cy.stub() }
      vm.download()

      cy.wrap(add).should('have.been.calledOnceWith', 'Error downloading image', 'error')
      cy.then(() => expect(createUrl).not.to.have.been.called)
    })
  })

  it('reports monochrome images that can not be encoded', () => {
    mountImage().then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileDetailItemImage).vm
      const canvas = document.createElement('canvas')
      const add = cy.stub(vm.messages, 'add')

      canvas.width = 1
      canvas.height = 1
      cy.stub(canvas, 'toBlob').callsFake((callback) => callback(null))

      vm.cropper = { getCroppedCanvas: () => canvas, destroy: cy.stub() }
      vm.clear = cy.stub()
      vm.replace = cy.stub()
      vm.monochrome()

      cy.wrap(add).should('have.been.calledOnceWith', 'Error editing image', 'error')
      cy.then(() => expect(vm.replace).not.to.have.been.called)
    })
  })

  it('reports edited images that can not be encoded', () => {
    mountImage().then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileDetailItemImage).vm
      const add = cy.stub(vm.messages, 'add')

      // local reference, the cropper of the loaded image may replace the fake one meanwhile
      const replace = cy.stub()
      vm.cropper = { getCroppedCanvas: () => ({ toBlob: (callback) => callback(null) }), replace, destroy: cy.stub() }
      vm.updateFile()

      cy.wrap(add).should('have.been.calledOnceWith', 'Error editing image', 'error')
      cy.then(() => expect(replace).not.to.have.been.called)
    })
  })

  it('hides isolate button without image:isolate permission', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn.btn-remove-bg').should('not.exist')
  })

  it('shows upscale menu with image:upscale permission', () => {
    mountImage({}, { 'file:save': true, 'image:upscale': true })
    cy.get('.toolbar .v-btn.btn-upscale').should('exist')
  })

  it('hides upscale menu without image:upscale permission', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn.btn-upscale').should('not.exist')
  })

  it('shows expand button with image:uncrop permission', () => {
    mountImage({}, { 'file:save': true, 'image:uncrop': true })
    cy.get('.toolbar .v-btn.btn-expand').should('exist')
  })

  it('hides expand button without image:uncrop permission', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn.btn-expand').should('not.exist')
  })

  it('keeps mobile action menu and dialog close buttons aligned with the toolbar', () => {
    cy.viewport(375, 667)
    mountImage({}, { 'file:save': true, 'image:repaint': true })

    const aligned = ($button) => {
      const button = $button[0].getBoundingClientRect()
      const toolbar = $button[0].closest('.v-toolbar__content').getBoundingClientRect()

      expect(toolbar.right - button.right).to.be.lessThan(12)
    }

    cy.get('.toolbar button[title="Select area"]').click()
    cy.get('.v-dialog .v-toolbar button[aria-label="Close"]').should(aligned).click({ force: true })

    cy.get('.toolbar button[title="Edit image"]').click()
    cy.get('.v-dialog .v-toolbar button[aria-label="Close"]').should(aligned)
  })

  it('hides toolbar for SVG images', () => {
    mountImage({ item: { ...item, mime: 'image/svg+xml' } }, { 'file:save': true })
    cy.get('.toolbar').should('not.exist')
  })

  it('hides toolbar for compressed SVG images', () => {
    mountImage({ item: { ...item, mime: 'image/svg+xml-compressed' } }, { 'file:save': true })
    cy.get('.toolbar').should('not.exist')
  })

  it('renders image element for SVG', () => {
    mountImage({ item: { ...item, name: 'logo.svg', path: 'files/logo.svg', mime: 'image/svg+xml' } })
    cy.get('img.element')
      .should('exist')
      .and('have.class', 'checkered')
      .should(($image) => {
        const style = getComputedStyle($image[0])
        const image = $image[0].getBoundingClientRect()
        const preview = $image[0].parentElement.getBoundingClientRect()

        expect($image[0].complete).to.equal(true)
        expect($image[0].naturalWidth).to.be.greaterThan(0)
        expect(style.backgroundImage).to.contain('conic-gradient')
        expect(style.backgroundRepeat).to.equal('repeat')
        expect(style.minHeight).to.equal('180px')
        expect(image.width).to.equal(preview.width)
        expect(image.height).to.be.at.least(180)
      })
  })

  it('keeps the alpha channel when converting to monochrome', () => {
    const canvas = document.createElement('canvas')
    canvas.width = 1
    canvas.height = 1
    const context = canvas.getContext('2d')
    context.putImageData(new ImageData(new Uint8ClampedArray([255, 0, 0, 128]), 1, 1), 0, 0)

    cy.mount(FileDetailItemImage, {
      props: { item: { ...item }, readonly: true },
      global: { provide: { base64ToBlob: () => new Blob(), url: (path) => path } }
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileDetailItemImage).vm
      vm.cropper = { getCroppedCanvas: () => canvas, destroy: cy.stub() }
      vm.clear = cy.stub()
      vm.replace = cy.stub()
      vm.monochrome()

      const data = context.getImageData(0, 0, 1, 1).data
      expect(data[3]).to.equal(128)
      expect(data[0]).to.equal(data[1])
      expect(data[1]).to.equal(data[2])
    })
  })

  it('ignores using an image without cropper', () => {
    const onUse = cy.spy().as('use')

    cy.mount(FileDetailItemImage, {
      props: { item: { ...item }, readonly: true, onUse },
      global: { provide: { base64ToBlob: () => new Blob(), url: (path) => path } }
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileDetailItemImage).vm
      expect(() => vm.use([{ ...item }])).not.to.throw()
      cy.get('@use').should('not.have.been.called')
    })
  })

  it('names edited images as PNG files matching their content', () => {
    mountImage().then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileDetailItemImage).vm
      const file = vm.pngFile(new Blob(['png'], { type: 'image/png' }))

      expect(file.name).to.equal('photo.png')
      expect(file.type).to.equal('image/png')
    })
  })

  it('does not report a ratio before the image dimensions are known', () => {
    mountImage().then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileDetailItemImage).vm

      vm.width = 0
      vm.height = 0
      expect(Number.isNaN(vm.ratio)).to.equal(true)
    })
  })

  it('uses the displayed original instead of the last edit for further edits', () => {
    mountImage().then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileDetailItemImage).vm
      const edited = new Blob(['edited'], { type: 'image/png' })
      const original = new Blob(['original'], { type: 'image/jpeg' })

      vm.cropper = { replace: cy.stub(), reset: cy.stub(), clear: cy.stub(), destroy: cy.stub() }
      vm.remember(edited)
      cy.stub(window, 'fetch').resolves(new Response(original))

      return vm.image().then((blob) => {
        expect(blob).to.equal(edited)
        vm.use([{ ...item }])
        return vm.image()
      }).then((blob) => {
        expect(window.fetch).to.have.been.calledOnceWith(vm.source)
        return blob.text()
      }).then((text) => {
        expect(text).to.equal('original')

        vm.remember(edited)
        return vm.image()
      }).then((blob) => {
        expect(blob).to.equal(edited)
      })
    })
  })

  it('restores previous edits and the original with the keyboard', () => {
    cy.stub(URL, 'createObjectURL').returns('blob:edit')

    mountImage({}, { 'file:save': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileDetailItemImage).vm
      const edited = new Blob(['edited'], { type: 'image/png' })

      vm.cropper = { replace: cy.stub(), reset: cy.stub(), clear: cy.stub(), destroy: cy.stub() }
      vm.remember(edited)
      cy.stub(vm, 'replace').as('replace')
      cy.stub(vm, 'use').as('use')
    })

    cy.get('.toolbar .v-btn.btn-undo').click()
    cy.get('.action-menu-list .v-list-item').should('have.length', 2).first().focus().type('{enter}')
    cy.get('@replace').should('have.been.calledOnce')

    cy.get('.toolbar .v-btn.btn-undo').click()
    cy.get('.action-menu-list .v-list-item').last().focus().type('{enter}')
    cy.get('@use').should('have.been.calledOnce')
  })
})
