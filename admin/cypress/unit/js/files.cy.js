import { canvasBlob, fetchFileDisks, relocateFiles } from '../../../js/files'

describe('relocateFiles()', () => {
  const response = { data: { relocateFile: [{ id: '1', disk: 'private' }] } }

  it('invalidates the files lists of the client of the component', () => {
    const cache = { evict: cy.stub(), gc: cy.stub() }
    const apollo = { mutate: cy.stub().resolves(response), getClient: () => ({ cache }) }

    return relocateFiles(apollo, ['1'], 'private').then((items) => {
      expect(items).to.deep.equal(response.data.relocateFile)
      expect(apollo.mutate.firstCall.args[0].variables).to.deep.equal({ id: ['1'], disk: 'private' })
      expect(cache.evict).to.have.been.calledWith({ id: 'ROOT_QUERY', fieldName: 'files' })
      expect(cache.gc).to.have.been.calledOnce
    })
  })

  it('invalidates the files lists of a passed Apollo client', () => {
    const cache = { evict: cy.stub(), gc: cy.stub() }
    const apollo = { mutate: cy.stub().resolves(response), cache }

    return relocateFiles(apollo, ['1'], 'public').then(() => {
      expect(cache.evict).to.have.been.calledWith({ id: 'ROOT_QUERY', fieldName: 'files' })
    })
  })
})

describe('fetchFileDisks()', () => {
  it('fetches all files in batches of 100', () => {
    const ids = Array.from({ length: 250 }, (_, i) => 'file-' + i)
    const query = cy.stub().callsFake(({ variables }) => Promise.resolve({
      data: { files: { data: variables.id.map((id) => ({ id, disk: 'public' })) } }
    }))

    return fetchFileDisks({ query }, ids).then((items) => {
      expect(query).to.have.been.calledThrice
      expect(query.getCalls().map((call) => call.args[0].variables.first)).to.deep.equal([100, 100, 50])
      expect(query.firstCall.args[0].fetchPolicy).to.equal('no-cache')
      expect(items.map((item) => item.id)).to.deep.equal(ids)
    })
  })

  it('fetches nothing without IDs', () => {
    const query = cy.stub()

    return fetchFileDisks({ query }, []).then((items) => {
      expect(query).not.to.have.been.called
      expect(items).to.deep.equal([])
    })
  })
})

describe('canvasBlob()', () => {
  it('resolves to the encoded image', () => {
    const blob = new Blob(['png'], { type: 'image/png' })
    const toBlob = cy.stub().callsFake((callback) => callback(blob))

    return canvasBlob({ toBlob }, 'image/png', 1).then((result) => {
      expect(result).to.equal(blob)
      expect(toBlob.firstCall.args.slice(1)).to.deep.equal(['image/png', 1])
    })
  })

  it('rejects if the canvas can not be encoded', () => {
    return canvasBlob({ toBlob: (callback) => callback(null) }).then(
      () => { throw new Error('resolved') },
      (error) => expect(error.message).to.equal('Canvas can not be encoded')
    )
  })

  it('rejects if encoding the canvas throws', () => {
    const toBlob = () => { throw new DOMException('tainted', 'SecurityError') }

    return canvasBlob({ toBlob }).then(
      () => { throw new Error('resolved') },
      (error) => expect(error.name).to.equal('SecurityError')
    )
  })
})
