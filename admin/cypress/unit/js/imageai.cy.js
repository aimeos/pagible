import { imageAi } from '../../../js/imageai'

function editor(props = {}) {
  const vm = {
    cropper: {},
    clear: cy.stub(),
    edittext: 'a cat',
    item: { mime: 'image/jpeg' },
    messages: { error: cy.stub() },
    mutate: cy.stub(),
    $gettext: (msg) => msg,
    ...props
  }

  for (const name of ['croppedFile', 'erase', 'imageFile', 'inpaint', 'isolate', 'repaint', 'run', 'uncrop', 'upscale']) {
    vm[name] = imageAi.methods[name].bind(vm)
  }

  return vm
}

describe('imageAi', () => {
  it('reports failures while preparing the image to erase', () => {
    const vm = editor({ masked: cy.stub().rejects(new Error('404')) })

    vm.erase()

    cy.wrap(vm.messages.error).should('have.been.calledWith', 'Error erasing image part').then(() => {
      expect(vm.mutate).not.to.have.been.called
    })
  })

  it('reports failures while preparing the image to inpaint', () => {
    const vm = editor({ masked: cy.stub().rejects(new Error('404')) })

    vm.inpaint()

    cy.wrap(vm.messages.error).should('have.been.calledWith', 'Error editing image part')
  })

  it('reports failures while preparing the image to repaint', () => {
    const vm = editor({ image: cy.stub().rejects(new Error('404')) })

    vm.repaint()

    cy.wrap(vm.messages.error).should('have.been.calledWith', 'Error editing image')
  })

  it('uploads edited images with their own type instead of the original one', () => {
    const vm = editor({ image: cy.stub().resolves(new Blob(['png'], { type: 'image/png' })) })

    vm.repaint()

    cy.wrap(vm.mutate).should('have.been.called').then(() => {
      expect(vm.mutate.firstCall.args[2].file.type).to.equal('image/png')
      expect(vm.imageFile(new Blob(['raw'])).type).to.equal('image/jpeg')
    })
  })

  it('starts no other operation while one is running', () => {
    const vm = editor({ aiBusy: true, masked: cy.stub().resolves({}) })

    vm.erase()
    vm.isolate()

    expect(vm.masked).not.to.have.been.called
    expect(vm.mutate).not.to.have.been.called
  })

  it('refuses a mutation if another one started while the image was prepared', () => {
    const vm = {
      readonly: false,
      loading: { 'image:erase': true },
      user: { can: () => true },
      messages: { denied: cy.stub(), error: cy.stub() },
      $apollo: { mutate: cy.stub().resolves({ data: {} }) },
      clear: cy.stub(),
      get aiBusy() {
        return imageAi.computed.aiBusy.call(this)
      }
    }

    return imageAi.methods.mutate.call(vm, 'image:isolate', null, {}, 'Error').then(() => {
      expect(vm.$apollo.mutate).not.to.have.been.called
      expect(vm.loading['image:isolate']).to.be.undefined
      expect(vm.clear).not.to.have.been.called
    })
  })

  it('counts preparing the image as running operation', () => {
    let resolve
    const vm = editor({ masked: cy.stub().returns(new Promise((r) => { resolve = r })), preparing: false })
    vm.mutate = cy.stub().callsFake(() => {
      expect(vm.preparing).to.be.false
      return Promise.resolve()
    })

    const promise = vm.erase()

    expect(vm.preparing).to.be.true
    expect(imageAi.computed.aiBusy.call({ preparing: true, loading: {} })).to.be.true
    resolve({ file: 'file', mask: 'mask' })

    return promise.then(() => {
      expect(vm.mutate).to.have.been.calledOnceWith('image:erase')
      expect(vm.preparing).to.be.false
    })
  })

  it('resets the preparing state if preparing the image fails', () => {
    const vm = editor({ image: cy.stub().rejects(new Error('404')), preparing: false })

    return vm.repaint().then(() => {
      expect(vm.preparing).to.be.false
      expect(vm.mutate).not.to.have.been.called
      expect(vm.messages.error).to.have.been.calledWith('Error editing image')
    })
  })

  it('returns the promise of every operation', () => {
    const vm = editor({ masked: cy.stub().resolves({}), image: cy.stub().resolves(new Blob(['png'])) })
    vm.mutate = cy.stub().resolves()

    return vm.erase().then(() => {
      expect(vm.inpaint()?.then).to.be.a('function')
    }).then(() => vm.repaint())
  })

  it('reports images that can not be encoded instead of uploading them', () => {
    const canvas = { toBlob: (callback) => callback(null) }
    const vm = editor({ cropper: { getCroppedCanvas: () => canvas } })

    return vm.upscale(2).then(() => {
      expect(vm.mutate).not.to.have.been.called
      expect(vm.messages.error).to.have.been.calledWith('Error upscaling image')
    })
  })

  it('uploads the cropped area as PNG file for isolating, uncropping and upscaling', () => {
    const canvas = { toBlob: (callback) => callback(new Blob(['png'], { type: 'image/png' })) }
    const vm = editor({ cropper: { getCroppedCanvas: () => canvas }, extend: { top: 10 } })
    vm.mutate = cy.stub().resolves()

    return vm.isolate()
      .then(() => vm.uncrop())
      .then(() => vm.upscale(2))
      .then(() => {
        expect(vm.mutate).to.have.been.calledThrice

        for (const call of vm.mutate.getCalls()) {
          expect(call.args[2].file.name).to.equal('image.png')
          expect(call.args[2].file.type).to.equal('image/png')
        }

        expect(vm.mutate.secondCall.args[2]).to.include({ top: 10, right: 0, bottom: 0, left: 0 })
        expect(vm.mutate.thirdCall.args[2].factor).to.equal(2)
      })
  })

  it('resets the preparing state if preparing the image throws', () => {
    const vm = editor({ preparing: false })

    return vm.run('image:repaint', {}, 'Error editing image', () => { throw new Error('sync') }).then(() => {
      expect(vm.preparing).to.be.false
      expect(vm.mutate).not.to.have.been.called
      expect(vm.messages.error).to.have.been.calledWith('Error editing image')
    })
  })

  it('drops the result if another version was restored meanwhile', () => {
    let resolve
    const vm = editor({
      aiBusy: false,
      loading: {},
      readonly: false,
      replace: cy.stub(),
      item: { mime: 'image/png', path: 'files/a.png' },
      user: { can: () => true },
      $apollo: { mutate: () => new Promise((r) => { resolve = r }) }
    })
    vm.mutate = imageAi.methods.mutate.bind(vm)

    const running = vm.mutate('image:upscale', {}, {}, 'Error upscaling image')
    vm.item.path = 'files/b.png'
    resolve({ data: { upscale: 'aW1n' } })

    return running.then(() => {
      expect(vm.replace).not.to.have.been.called
      expect(vm.loading['image:upscale']).to.be.false
    })
  })
})
