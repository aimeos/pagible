import FileDetailItemAudio from '../../../js/components/FileDetailItemAudio.vue'

const item = {
  id: '1',
  name: 'track.mp3',
  path: 'files/track.mp3',
  mime: 'audio/mpeg',
}

function mountAudio(props = {}) {
  return cy.mount(FileDetailItemAudio, {
    props: {
      item: { ...item },
      ...props,
    },
    global: {
      provide: {
        url: (path) => path,
      },
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

  it('has crossorigin set to anonymous', () => {
    mountAudio()
    cy.get('audio.element').should('have.attr', 'crossorigin', 'anonymous')
  })
})
