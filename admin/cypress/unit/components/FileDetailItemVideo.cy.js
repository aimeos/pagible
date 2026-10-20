import FileDetailItemVideo from '../../../js/components/FileDetailItemVideo.vue'
import { useConfirmStore, useMessageStore, useUserStore } from '../../../js/stores'

const item = {
  id: '1',
  name: 'clip.mp4',
  path: 'files/clip.mp4',
  mime: 'video/mp4',
  previews: {},
  updated_at: '2024-01-01T00:00:00Z',
}

function mountVideo(props = {}, perms = {}) {
  return cy.mount(FileDetailItemVideo, {
    props: {
      readonly: false,
      ...props,
      item: { ...item, ...props.item },
    },
    global: {
      provide: {
        url: (path) => path,
      },
    },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('FileDetailItemVideo', () => {
  it('renders the editor container', () => {
    mountVideo()
    cy.get('.editor-container').should('exist')
  })

  it('renders the video element', () => {
    mountVideo()
    cy.get('video.element').should('exist')
    cy.get('video.element').should('have.attr', 'controls')
  })

  it('shows toolbar when not readonly', () => {
    mountVideo()
    cy.get('.toolbar').should('exist')
  })

  it('hides toolbar when readonly', () => {
    mountVideo({ readonly: true })
    cy.get('.toolbar').should('not.exist')
  })

  it('shows cover image buttons when no preview exists', () => {
    mountVideo({}, { 'file:save': true })
    cy.get('.toolbar .v-btn.btn-cover-use').should('exist')
    cy.get('.toolbar .v-btn.btn-cover-upload').should('exist')
  })

  it('shows cover preview when preview exists', () => {
    mountVideo({ item: { previews: { thumb: 'files/thumb.jpg' } } })
    cy.get('img.video-preview').should('exist')
  })

  it('hides cover buttons when preview exists', () => {
    mountVideo({ item: { previews: { thumb: 'files/thumb.jpg' } } })
    cy.get('.toolbar .v-btn.btn-cover-use').should('not.exist')
  })

  it('shows the cover buttons when the item has no previews', () => {
    mountVideo({ item: { previews: null } })
    cy.get('.toolbar .v-btn.btn-cover-use').should('exist')
    cy.get('.btn-cover-remove').should('not.exist')
  })

  it('renders the cover preview as accessible remove button', () => {
    mountVideo({ item: { previews: { thumb: 'files/thumb.jpg' } } })
    cy.get('button.btn-cover-remove')
      .should('have.attr', 'aria-label', 'Remove cover image')
      .find('img.video-preview')
      .should('have.attr', 'alt', 'clip.mp4')
  })

  it('asks for confirmation before removing the cover', () => {
    const mutate = cy.stub().resolves({ data: { saveFile: { latest: null } } })

    cy.mount(FileDetailItemVideo, {
      props: { item: { ...item, previews: { 500: 'files/clip.png' } } },
      global: { mocks: { $apollo: { mutate } }, provide: { url: (path) => path } }
    }).then(() => {
      const confirm = useConfirmStore()
      const ask = cy.stub(confirm, 'ask').resolves(false)

      cy.get('button.btn-cover-remove').click()
      cy.wrap(ask).should('have.been.calledOnceWith', 'Remove cover image', 'Remove the cover image of this video?')
      cy.then(() => expect(mutate).not.to.have.been.called)

      cy.then(() => ask.resolves(true))
      cy.get('button.btn-cover-remove').click()
      cy.wrap(mutate).should('have.been.calledOnce')
    })
  })

  it('has hidden file input for cover upload', () => {
    mountVideo({}, { 'file:save': true })
    cy.get('.cover-input').should('exist')
    cy.get('.cover-input').should('not.be.visible')
    cy.get('.cover-input').should('have.attr', 'accept', 'image/*')
  })

  it('emits the saved cover previews without changing the item', () => {
    const previews = {}
    const mutate = cy.stub().resolves({
      data: { saveFile: { latest: { id: 'v2', published: false, data: JSON.stringify({ previews: { 500: 'files/clip.png' } }), created_at: '2026-01-02' } } }
    })

    cy.mount(FileDetailItemVideo, {
      props: { item: { ...item, previews } },
      global: { mocks: { $apollo: { mutate } }, provide: { url: (path) => path } }
    }).then(({ wrapper }) => {
      wrapper = wrapper.findComponent(FileDetailItemVideo)

      return wrapper.vm.cover(new File([''], 'clip.png'), 'error').then(() => {
        expect(wrapper.emitted('sync')).to.deep.equal([[{ previews: { 500: 'files/clip.png' }, latestId: 'v2', published: false, updated_at: '2026-01-02' }]])
        expect(previews).to.deep.equal({})
      })
    })
  })

  it('emits the previews from the response when removing the cover', () => {
    const mutate = cy.stub().resolves({
      data: { saveFile: { latest: { id: 'v3', published: false, data: JSON.stringify({ previews: {} }), created_at: '2026-01-03' } } }
    })

    cy.mount(FileDetailItemVideo, {
      props: { item: { ...item, previews: { 500: 'files/clip.png' } } },
      global: { mocks: { $apollo: { mutate } }, provide: { url: (path) => path } }
    }).then(({ wrapper }) => {
      wrapper = wrapper.findComponent(FileDetailItemVideo)
      cy.stub(useConfirmStore(), 'ask').resolves(true)
      const promise = wrapper.vm.removeCover()

      // not synced before the cover is removed on the server
      expect(wrapper.emitted('sync')).to.equal(undefined)

      return promise.then(() => {
        expect(mutate.firstCall.args[0].variables).to.deep.equal({ id: '1', preview: false })
        expect(wrapper.emitted('sync')).to.deep.equal([[{ previews: {}, latestId: 'v3', published: false, updated_at: '2026-01-03' }]])
        expect(wrapper.props('item').previews).to.deep.equal({ 500: 'files/clip.png' })
      })
    })
  })

  it('keeps the previews when removing the cover fails', () => {
    const mutate = cy.stub().rejects(new Error('failed'))

    cy.mount(FileDetailItemVideo, {
      props: { item: { ...item, previews: { 500: 'files/clip.png' } } },
      global: { mocks: { $apollo: { mutate } }, provide: { url: (path) => path } }
    }).then(({ wrapper }) => {
      wrapper = wrapper.findComponent(FileDetailItemVideo)

      return wrapper.vm.cover(false, 'error').then(() => {
        expect(wrapper.emitted('sync')).to.equal(undefined)
      })
    })
  })

  it('does not capture a cover before the video has a frame', () => {
    const mutate = cy.stub()

    cy.mount(FileDetailItemVideo, {
      props: { item: { ...item } },
      global: { mocks: { $apollo: { mutate } }, provide: { url: (path) => path } }
    }).then(({ wrapper }) => {
      wrapper = wrapper.findComponent(FileDetailItemVideo)
      const drawImage = cy.spy(CanvasRenderingContext2D.prototype, 'drawImage')

      const messages = useMessageStore()
      const add = cy.spy(messages, 'add')

      wrapper.vm.addCover()

      expect(drawImage).not.to.have.been.called
      expect(mutate).not.to.have.been.called
      expect(add).to.have.been.calledWith('Video not loaded yet', 'info')
    })
  })

  it('saves the captured cover only once on double click', () => {
    let resolve
    const mutate = cy.stub().returns(new Promise((res) => { resolve = res }))

    cy.stub(HTMLMediaElement.prototype, 'readyState').get(() => HTMLMediaElement.HAVE_ENOUGH_DATA)
    cy.stub(HTMLVideoElement.prototype, 'videoWidth').get(() => 4)
    cy.stub(HTMLVideoElement.prototype, 'videoHeight').get(() => 4)
    cy.stub(CanvasRenderingContext2D.prototype, 'drawImage')

    cy.mount(FileDetailItemVideo, {
      props: { item: { ...item } },
      global: { mocks: { $apollo: { mutate } }, provide: { url: (path) => path } }
    }).then(({ wrapper }) => {
      wrapper = wrapper.findComponent(FileDetailItemVideo)

      wrapper.vm.addCover()
      wrapper.vm.addCover()
      expect(wrapper.vm.loading.cover).to.be.true

      cy.wrap(mutate).should('have.been.calledOnce')
      cy.then(() => {
        const target = { files: [new File(['x'], 'cover.png', { type: 'image/png' })], value: 'cover.png' }

        // other cover changes wait until the cover is saved
        wrapper.vm.uploadCover({ target })
        wrapper.vm.removeCover()
        expect(mutate).to.have.been.calledOnce

        resolve({ data: { saveFile: { latest: null } } })
      })
      cy.wrap(wrapper.vm).its('loading.cover').should('be.false')
    })
  })

  it('allows capturing the cover again if converting the frame fails', () => {
    const mutate = cy.stub()

    cy.stub(HTMLMediaElement.prototype, 'readyState').get(() => HTMLMediaElement.HAVE_ENOUGH_DATA)
    cy.stub(HTMLVideoElement.prototype, 'videoWidth').get(() => 4)
    cy.stub(HTMLVideoElement.prototype, 'videoHeight').get(() => 4)
    cy.stub(CanvasRenderingContext2D.prototype, 'drawImage').throws(new DOMException('tainted', 'SecurityError'))

    cy.mount(FileDetailItemVideo, {
      props: { item: { ...item } },
      global: { mocks: { $apollo: { mutate } }, provide: { url: (path) => path } }
    }).then(({ wrapper }) => {
      wrapper = wrapper.findComponent(FileDetailItemVideo)
      const error = cy.stub(useMessageStore(), 'error')

      wrapper.vm.addCover()

      expect(error).to.have.been.calledOnce
      expect(wrapper.vm.loading.cover).to.be.false
      expect(mutate).not.to.have.been.called
    })
  })

  it('allows capturing the cover again if encoding the frame fails', () => {
    const mutate = cy.stub()

    cy.stub(HTMLMediaElement.prototype, 'readyState').get(() => HTMLMediaElement.HAVE_ENOUGH_DATA)
    cy.stub(HTMLVideoElement.prototype, 'videoWidth').get(() => 4)
    cy.stub(HTMLVideoElement.prototype, 'videoHeight').get(() => 4)
    cy.stub(CanvasRenderingContext2D.prototype, 'drawImage')
    cy.stub(HTMLCanvasElement.prototype, 'toBlob').callsFake((callback) => callback(null))

    cy.mount(FileDetailItemVideo, {
      props: { item: { ...item } },
      global: { mocks: { $apollo: { mutate } }, provide: { url: (path) => path } }
    }).then(({ wrapper }) => {
      wrapper = wrapper.findComponent(FileDetailItemVideo)
      const error = cy.stub(useMessageStore(), 'error')

      wrapper.vm.addCover()

      cy.wrap(error).should('have.been.calledOnceWith', 'Error saving video cover')
      cy.wrap(wrapper.vm).its('loading.cover').should('be.false')
      cy.then(() => expect(mutate).not.to.have.been.called)
    })
  })

  it('resets the cover input so the same file can be selected again', () => {
    const mutate = cy.stub().resolves({ data: { saveFile: { latest: null } } })

    cy.mount(FileDetailItemVideo, {
      props: { item: { ...item } },
      global: { mocks: { $apollo: { mutate } }, provide: { url: (path) => path } }
    }).then(({ wrapper }) => {
      wrapper = wrapper.findComponent(FileDetailItemVideo)
      const target = { files: [new File(['x'], 'cover.png', { type: 'image/png' })], value: 'C:\\fakepath\\cover.png' }

      wrapper.vm.uploadCover({ target })

      expect(target.value).to.equal('')
      expect(mutate).to.have.been.calledOnce
    })
  })
})
