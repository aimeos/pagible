import FileDetailItem from '../../../js/components/FileDetailItem.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
  FileAiDialog: { template: '<div class="ai-dialog-stub" />' },
  FileDetailItemImage: { template: '<div class="image-stub" />' },
  FileDetailItemVideo: { template: '<div class="video-stub" />' },
  FileDetailItemAudio: { template: '<div class="audio-stub" />' },
}

const item = {
  disk: 'public',
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

function mountDetail(props = {}, perms = {}, apollo = {}) {
  return cy.mount(FileDetailItem, {
    props: {
      ...props,
      item: { ...item, ...props.item },
    },
    global: {
      stubs,
      mocks: {
        $apollo: {
          query: () => Promise.resolve({ data: {} }),
          mutate: () => Promise.resolve({ data: {} }),
          provider: { defaultClient: { cache: { evict() {}, gc() {} } } },
          ...apollo,
        },
      },
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

  it('uses the surface color for the URL and copy button area', () => {
    mountDetail()
    cy.get('.file-url-col')
      .then(($col) => {
        $col[0].style.setProperty('--v-theme-background', '30, 41, 59')
        $col[0].style.setProperty('--v-theme-surface', '255, 255, 255')
      })
      .should('have.css', 'background-color', 'rgb(255, 255, 255)')
      .find('.v-btn')
      .should('exist')
  })

  it('renders the name text field with item name', () => {
    mountDetail()
    cy.get('input').first().should('have.value', 'photo.jpg')
  })

  it('places the protection switch before the name field', () => {
    mountDetail({}, { 'file:relocate': true })
    cy.get('.field-protect + .v-row input').first().should('have.value', 'photo.jpg')
    cy.get('.field-protect').should('contain', 'Protect access')
  })

  it('hides the protection switch without file:relocate permission', () => {
    mountDetail()
    cy.get('.field-protect').should('not.exist')
  })

  it('shows the protection switch with file:relocate permission', () => {
    mountDetail({ item: { disk: 'private' } }, { 'file:relocate': true })
    cy.get('.field-protect input[type="checkbox"]').should('be.checked')

    mountDetail({ item: { disk: 'private' } }, { 'file:publish': true, 'file:save': true })
    cy.get('.field-protect').should('not.exist')
  })

  it('moves the file and its previews between public and private disks', () => {
    const mutate = cy.stub().callsFake(({ variables }) => {
      return Promise.resolve({
        data: {
          relocateFile: [{
            disk: variables.disk,
            id: '1',
            editor: 'admin',
            updated_at: '2026-08-03T12:00:00Z',
          }],
        },
      })
    })
    const previews = { 500: 'files/photo-500.jpg' }

    mountDetail(
      { item: { previews } },
      { 'file:relocate': true },
      { mutate },
    ).then(() => {
      const wrapper = Cypress.vueWrapper.findComponent(FileDetailItem)

      return wrapper.vm.setProtect(true).then((result) => {
        expect(result).to.equal(true)
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.equal({ id: ['1'], disk: 'private' })
        expect(wrapper.props('item').disk).to.equal('private')
        expect(wrapper.props('item').previews).to.equal(previews)
        expect(wrapper.emitted('update:item')).to.be.undefined
        expect(wrapper.vm.loading.protect).to.equal(false)

        return wrapper.vm.setProtect(false)
      }).then((result) => {
        expect(result).to.equal(true)
        expect(mutate).to.have.been.calledTwice
        expect(mutate.secondCall.args[0].variables).to.deep.equal({ id: ['1'], disk: 'public' })
        expect(wrapper.props('item').disk).to.equal('public')
        expect(wrapper.props('item').previews).to.equal(previews)
      })
    })
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

  it('emits description updates for frozen file metadata', () => {
    const description = Object.freeze({ en: 'Original description' })

    mountDetail({ item: { description } }, { 'file:save': true }).then(() => {
      const wrapper = Cypress.vueWrapper.findComponent(FileDetailItem)

      wrapper.vm.descriptionUpdated('en', 'Updated description')

      expect(wrapper.props('item').description).to.deep.equal({ en: 'Updated description' })
      expect(wrapper.emitted('update:item')).to.have.length(1)
    })
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
