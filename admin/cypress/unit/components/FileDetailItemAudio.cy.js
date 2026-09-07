import FileDetailItemAudio from '../../../js/components/FileDetailItemAudio.vue'
import { useAppStore } from '../../../js/stores'

const item = {
  id: '1',
  name: 'track.mp3',
  path: 'files/track.mp3',
  mime: 'audio/mpeg',
  disk: 'public',
}

function mountAudio(props = {}) {
  return cy.mount(FileDetailItemAudio, {
    props: {
      ...props,
      item: { ...item, ...props.item },
    },
    global: {
      plugins: [{
        install() {
          const app = useAppStore()
          app.urlfile = '/storage'
          app.urlasset = '/cmsadminasset/_file_/_variant_'
        }
      }],
    },
  })
}

describe('FileDetailItemAudio', () => {
  it('renders the audio element', () => {
    mountAudio()
    cy.get('audio.element').should('exist')
  })

  it('sets the correct source', () => {
    mountAudio()
    cy.get('audio.element').should('have.attr', 'src', '/storage/files/track.mp3')
  })

  it('has controls enabled', () => {
    mountAudio()
    cy.get('audio.element').should('have.attr', 'controls')
  })

  it('uses the protected asset route for private audio', () => {
    cy.intercept('GET', '/cmsadminasset/1', {
      statusCode: 200,
      headers: { 'content-type': 'audio/mpeg' },
      body: '',
    })
    mountAudio({ item: { disk: 'private' } })
    cy.get('audio.element').should('have.attr', 'src', '/cmsadminasset/1')
  })

  it('has crossorigin set to anonymous', () => {
    mountAudio()
    cy.get('audio.element').should('have.attr', 'crossorigin', 'anonymous')
  })
})
