import FileDetailItem from '../../../js/components/FileDetailItem.vue'
import { useMessageStore, useUserStore } from '../../../js/stores'
import { apolloClient } from '../../../js/graphql'

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
        txLocaleItems: () => [],
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
        expect(wrapper.emitted('sync')[0][0]).to.include({ disk: 'private' })
        expect(wrapper.emitted('sync')[0][0]).not.to.have.property('previews')
        expect(wrapper.emitted('patch')).to.be.undefined
        expect(wrapper.props('item').disk).to.equal('public')
        expect(wrapper.vm.loading.protect).to.equal(false)

        // the test acts as owner and applies the synced changes
        Object.assign(wrapper.props('item'), wrapper.emitted('sync')[0][0])

        return wrapper.vm.setProtect(false)
      }).then((result) => {
        expect(result).to.equal(true)
        expect(mutate).to.have.been.calledTwice
        expect(mutate.secondCall.args[0].variables).to.deep.equal({ id: ['1'], disk: 'public' })
        expect(wrapper.emitted('sync')[1][0]).to.include({ disk: 'public' })
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

  it('offers clearing the description and transcription only with file:save permission', () => {
    const texts = { item: { mime: 'video/mp4', description: { en: 'Text' }, transcription: { en: 'WEBVTT' } } }

    mountDetail(texts)
    cy.get('.description textarea').first().should('have.value', 'Text')
    cy.get('.description .v-field__clearable').should('not.exist')
    cy.get('.transcription .v-field__clearable').should('not.exist')

    mountDetail(texts, { 'file:save': true })
    cy.get('.description .v-field__clearable').should('exist')
    cy.get('.transcription .v-field__clearable').should('exist')
  })

  it('emits description updates for frozen file metadata', () => {
    const description = Object.freeze({ en: 'Original description' })

    mountDetail({ item: { description } }, { 'file:save': true }).then(() => {
      const wrapper = Cypress.vueWrapper.findComponent(FileDetailItem)

      wrapper.vm.descriptionUpdated('en', 'Updated description')

      expect(wrapper.emitted('patch')).to.deep.equal([[{ description: { en: 'Updated description' } }]])
      expect(wrapper.props('item').description).to.equal(description)
    })
  })

  it('emits transcription updates without changing the item', () => {
    const transcription = { en: 'old' }

    mountDetail({ item: { mime: 'audio/mpeg', transcription } }, { 'file:save': true }).then(() => {
      const wrapper = Cypress.vueWrapper.findComponent(FileDetailItem)

      wrapper.vm.transcriptionUpdated('de', 'neu')

      expect(wrapper.emitted('patch')).to.deep.equal([[{ transcription: { en: 'old', de: 'neu' } }]])
      expect(transcription).to.deep.equal({ en: 'old' })
    })
  })

  it('emits the new path and mime when a file version is used', () => {
    mountDetail({}, { 'file:save': true }).then(() => {
      const wrapper = Cypress.vueWrapper.findComponent(FileDetailItem)

      wrapper.vm.use([{ path: 'files/new.png', mime: 'image/png' }])

      expect(wrapper.emitted('patch')).to.deep.equal([[{ path: 'files/new.png', mime: 'image/png' }]])
      expect(wrapper.props('item').path).to.equal('/files/photo.jpg')
      expect(wrapper.emitted('update:file')).to.deep.equal([[null]])
    })
  })

  it('skips translation when all texts are empty', () => {
    mountDetail({}, { 'file:save': true, 'text:translate': true }).then(() => {
      const wrapper = Cypress.vueWrapper.findComponent(FileDetailItem)

      return wrapper.vm.translateMap({ en: '', de: null }).then((texts) => {
        expect(texts).to.deep.equal({})
        expect(wrapper.vm.loading.translate).not.to.equal(true)
      })
    })
  })

  it('keeps translations whose first part is empty', () => {
    cy.stub(apolloClient, 'mutate').resolves({ data: { translate: ['', 'Tschüss'] } })

    mountDetail({}, { 'file:save': true, 'text:translate': true }).then(() => {
      const wrapper = Cypress.vueWrapper.findComponent(FileDetailItem)
      wrapper.vm.txLocaleItems = () => [{ code: 'de' }]

      return wrapper.vm.translateMap({ en: 'Hm\nBye' }, (text) => text.split('\n'), (result) => result.join('\n'))
        .then((texts) => expect(texts).to.deep.equal({ de: '\nTschüss' }))
    })
  })

  it('translates only the cue texts and keeps the WebVTT structure', () => {
    const vtt = 'WEBVTT\n\nNOTE keep this\n\ncue-1\n00:00:01.000 --> 00:00:02.000 align:start\nHello\nworld\n\n00:00:03.000 --> 00:00:04.000\nBye'

    mountDetail({ item: { mime: 'video/mp4', transcription: { en: vtt } } }, { 'file:save': true, 'text:translate': true }).then(() => {
      const wrapper = Cypress.vueWrapper.findComponent(FileDetailItem)
      const parts = []

      wrapper.vm.translateMap = (map, split, join) => {
        parts.push(...split(map.en))
        return Promise.resolve({ de: join(parts.map((text) => text.toUpperCase())) })
      }

      wrapper.vm.translateVTT({ en: vtt })

      cy.wrap(null).should(() => {
        expect(parts).to.deep.equal(['Hello\nworld', 'Bye'])
        expect(wrapper.emitted('patch')?.[0]?.[0]).to.deep.equal({
          transcription: {
            en: vtt,
            de: 'WEBVTT\n\nNOTE keep this\n\ncue-1\n00:00:01.000 --> 00:00:02.000 align:start\nHELLO\nWORLD\n\n00:00:03.000 --> 00:00:04.000\nBYE'
          }
        })
      })
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

  it('generates the description in the language of the shown tab', () => {
    const mutate = cy.stub().resolves({ data: { describe: 'Ein Foto' } })

    mountDetail({}, { 'file:save': true }, { mutate }).then(({ wrapper }) => {
      const detail = wrapper.findComponent(FileDetailItem)

      detail.vm.tabdesc = 'de'
      detail.vm.describe()

      expect(mutate.firstCall.args[0].variables.lang).to.equal('de')
      cy.wrap(detail).should(() => {
        expect(detail.emitted('patch')?.[0]?.[0]?.description).to.deep.equal({ de: 'Ein Foto' })
      })
    })
  })

  it('reports an error if the clipboard is unavailable', () => {
    mountDetail().then(({ wrapper }) => {
      const messages = useMessageStore()
      messages.queue = []

      cy.stub(navigator, 'clipboard').value(undefined)
      wrapper.findComponent(FileDetailItem).vm.copyUrl()

      expect(messages.queue.map((m) => m.text)).to.deep.equal(['Error copying to clipboard'])
    })
  })

  it('reports an error if copying to the clipboard fails', () => {
    mountDetail().then(({ wrapper }) => {
      const messages = useMessageStore()
      messages.queue = []

      cy.stub(navigator, 'clipboard').value({ writeText: () => Promise.reject(new Error('denied')) })
      wrapper.findComponent(FileDetailItem).vm.copyUrl()

      cy.wrap(messages).its('queue.length').should('equal', 1).then(() => {
        expect(messages.queue[0].color).to.equal('error')
        expect(messages.queue[0].text).to.contain('Error copying to clipboard')
      })
    })
  })
})

