import FileDetailItemVideo from '../../../js/components/FileDetailItemVideo.vue'
import { useUserStore } from '../../../js/stores'

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
      item: { ...item, ...props.item },
      readonly: false,
      ...props,
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

  it('has hidden file input for cover upload', () => {
    mountVideo({}, { 'file:save': true })
    cy.get('.cover-input').should('exist')
    cy.get('.cover-input').should('not.be.visible')
  })

  it('emits the saved cover previews without changing the item', () => {
    const previews = {}
    const mutate = cy.stub().resolves({
      data: { saveFile: { latest: { data: JSON.stringify({ previews: { 500: 'files/clip.png' } }), created_at: '2026-01-02' } } }
    })

    cy.mount(FileDetailItemVideo, {
      props: { item: { ...item, previews } },
      global: { mocks: { $apollo: { mutate } }, provide: { url: (path) => path } }
    }).then(({ wrapper }) => {
      wrapper = wrapper.findComponent(FileDetailItemVideo)

      return wrapper.vm.cover(new File([''], 'clip.png'), 'error').then(() => {
        expect(wrapper.emitted('sync')).to.deep.equal([[{ previews: { 500: 'files/clip.png' }, updated_at: '2026-01-02' }]])
        expect(previews).to.deep.equal({})
      })
    })
  })

  it('emits empty previews when removing the cover', () => {
    const mutate = cy.stub().resolves({ data: { saveFile: { latest: null } } })

    cy.mount(FileDetailItemVideo, {
      props: { item: { ...item, previews: { 500: 'files/clip.png' } } },
      global: { mocks: { $apollo: { mutate } }, provide: { url: (path) => path } }
    }).then(({ wrapper }) => {
      wrapper = wrapper.findComponent(FileDetailItemVideo)
      wrapper.vm.removeCover()

      expect(wrapper.emitted('sync')[0]).to.deep.equal([{ previews: {} }])
      expect(wrapper.props('item').previews).to.deep.equal({ 500: 'files/clip.png' })
    })
  })
})
