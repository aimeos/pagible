import FileField, { accepts } from '../../../js/filefield'

describe('accepts()', () => {
  const file = (name, type) => new File(['x'], name, { type })

  it('accepts all files without a pattern or with wildcards', () => {
    expect(accepts(file('a.bin', ''), '')).to.be.true
    expect(accepts(file('a.bin', ''), '*')).to.be.true
    expect(accepts(file('a.bin', 'application/octet-stream'), '*/*')).to.be.true
  })

  it('matches wildcard MIME types, exact MIME types and extensions', () => {
    expect(accepts(file('a.png', 'image/png'), 'image/*')).to.be.true
    expect(accepts(file('a.mp4', 'video/mp4'), 'image/*, video/*')).to.be.true
    expect(accepts(file('a.mp4', 'video/mp4'), 'image/*')).to.be.false
    expect(accepts(file('a.pdf', 'application/pdf'), 'application/pdf')).to.be.true
    expect(accepts(file('A.PDF', ''), '.pdf')).to.be.true
    expect(accepts(file('a.txt', 'text/plain'), '.pdf,image/*')).to.be.false
  })
})

describe('drop()', () => {
  const event = (...files) => ({ dataTransfer: { files } })

  function field(props = {}) {
    const vm = {
      config: {},
      dragging: true,
      add: cy.stub(),
      messages: { add: cy.stub() },
      $gettext: (msg) => msg,
      ...props
    }

    Object.defineProperty(vm, 'accept', { get: () => FileField.computed.accept.call(vm) })
    vm.drop = FileField.methods.drop.bind(vm)
    return vm
  }

  it('adds only the accepted images', () => {
    const vm = field({ images: [] })
    const png = new File(['x'], 'a.png', { type: 'image/png' })

    vm.drop(event(png, new File(['x'], 'a.pdf', { type: 'application/pdf' })))

    expect(vm.dragging).to.be.false
    expect(vm.add).to.have.been.calledOnceWith([png])
    expect(vm.messages.add).to.have.been.calledWith('Unsupported file type', 'error')
  })

  it('adds the first accepted file of the profile', () => {
    const vm = field({ profile: { accept: 'audio/*' } })
    const mp3 = new File(['x'], 'a.mp3', { type: 'audio/mpeg' })

    vm.drop(event(mp3))

    expect(vm.add).to.have.been.calledOnceWith(mp3)
    expect(vm.messages.add).not.to.have.been.called
  })

  it('does not add rejected files', () => {
    const vm = field({ config: { accept: 'video/*' } })

    vm.drop(event(new File(['x'], 'a.png', { type: 'image/png' })))

    expect(vm.add).not.to.have.been.called
    expect(vm.messages.add).to.have.been.calledOnce
  })
})
