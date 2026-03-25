import FileDetailItemVideo from '../../src/components/FileDetailItemVideo.vue'
import { useUserStore } from '../../src/stores'

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
    cy.get('.toolbar .v-btn[title="Use as cover image"]').should('exist')
    cy.get('.toolbar .v-btn[title="Upload cover image"]').should('exist')
  })

  it('shows cover preview when preview exists', () => {
    mountVideo({ item: { previews: { thumb: 'files/thumb.jpg' } } })
    cy.get('img.video-preview').should('exist')
  })

  it('hides cover buttons when preview exists', () => {
    mountVideo({ item: { previews: { thumb: 'files/thumb.jpg' } } })
    cy.get('.toolbar .v-btn[title="Use as cover image"]').should('not.exist')
  })

  it('has hidden file input for cover upload', () => {
    mountVideo({}, { 'file:save': true })
    cy.get('.cover-input').should('exist')
    cy.get('.cover-input').should('not.be.visible')
  })
})
