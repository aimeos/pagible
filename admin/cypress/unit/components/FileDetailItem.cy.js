import FileDetailItem from '../../../js/components/FileDetailItem.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
  FileAiDialog: { template: '<div class="ai-dialog-stub" />' },
  FileDetailItemImage: { template: '<div class="image-stub" />' },
  FileDetailItemVideo: { template: '<div class="video-stub" />' },
  FileDetailItemAudio: { template: '<div class="audio-stub" />' },
}

const item = {
  id: '1',
  name: 'photo.jpg',
  path: '/files/photo.jpg',
  mime: 'image/jpeg',
  lang: 'en',
  editor: 'admin',
  description: {},
  transcription: {},
  previews: {},
  updated_at: '2024-01-01T00:00:00Z',
}

function mountDetail(props = {}, perms = {}) {
  return cy.mount(FileDetailItem, {
    props: {
      item: { ...item, ...props.item },
      ...props,
    },
    global: {
      stubs,
      provide: {
        base64ToBlob: () => new Blob(),
        locales: () => ['en', 'de'],
        transcribe: () => Promise.resolve({ asText: () => '' }),
        translate: () => Promise.resolve(['']),
        txlocales: () => [],
        url: (path) => path,
      },
    },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('FileDetailItem', () => {
  it('renders the component', () => {
    mountDetail()
    cy.get('.v-container').should('exist')
  })

  it('renders the name text field with item name', () => {
    mountDetail()
    cy.get('input').first().should('have.value', 'photo.jpg')
  })

  it('renders language select', () => {
    mountDetail()
    cy.get('.v-select').should('exist')
  })

  it('renders image sub-component for image mime types', () => {
    mountDetail()
    cy.get('.image-stub').should('exist')
  })

  it('renders video sub-component for video mime types', () => {
    mountDetail({ item: { mime: 'video/mp4' } })
    cy.get('.video-stub').should('exist')
  })

  it('renders audio sub-component for audio mime types', () => {
    mountDetail({ item: { mime: 'audio/mpeg' } })
    cy.get('.audio-stub').should('exist')
  })

  it('renders fallback SVG for unknown mime types', () => {
    mountDetail({ item: { mime: 'application/pdf' } })
    cy.get('svg').should('exist')
    cy.get('.image-stub').should('not.exist')
    cy.get('.video-stub').should('not.exist')
    cy.get('.audio-stub').should('not.exist')
  })

  it('makes name field readonly without file:save permission', () => {
    mountDetail()
    cy.get('input').first().should('have.attr', 'readonly')
  })

  it('makes name field editable with file:save permission', () => {
    mountDetail({}, { 'file:save': true })
    cy.get('input').first().should('not.have.attr', 'readonly')
  })

  it('shows transcription section for audio files', () => {
    mountDetail({ item: { mime: 'audio/mpeg' } })
    cy.contains('Transcriptions').should('exist')
  })

  it('shows transcription section for video files', () => {
    mountDetail({ item: { mime: 'video/mp4' } })
    cy.contains('Transcriptions').should('exist')
  })

  it('hides transcription section for image files', () => {
    mountDetail()
    cy.contains('Transcriptions').should('not.exist')
  })
})
