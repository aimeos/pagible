import AudioField from '../../js/fields/Audio.vue'
import { useUserStore } from '../../js/stores'

const audioAsset = {
  id: '1',
  name: 'recording.mp3',
  path: 'files/recording.mp3',
  mime: 'audio/mpeg',
  editor: 'admin',
  updated_at: '2024-01-01T00:00:00Z',
  description: { en: 'Test recording' },
  previews: {},
}

const stubs = {
  FileDialog: { template: '<div />' },
  FileUrlDialog: { template: '<div />' },
  FileListItems: { template: '<div />' },
  FileDetail: { template: '<div />' },
}

function mountAudio(props = {}, perms = {}) {
  return cy.mount(AudioField, {
    props: { config: {}, assets: {}, ...props },
    global: { stubs },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('Audio', () => {
  it('renders the file container', () => {
    mountAudio()
    cy.get('.files').should('exist')
  })

  it('shows upload and URL buttons when no file is loaded', () => {
    mountAudio()
    cy.get('button[title="Upload file"]').should('exist')
    cy.get('button[title="Add file from URL"]').should('exist')
  })

  it('shows "Add file" button with file:view permission', () => {
    mountAudio({}, { 'file:view': true })
    cy.get('button[title="Add file"]').should('exist')
  })

  it('renders audio element when file is loaded', () => {
    mountAudio({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': audioAsset },
    })
    cy.get('audio').should('exist')
    cy.get('audio').should('have.attr', 'controls')
  })

  it('sets audio src via url() inject', () => {
    mountAudio({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': audioAsset },
    })
    cy.get('audio').should('have.attr', 'src', '/storage/files/recording.mp3')
  })

  it('shows metadata when file is present', () => {
    mountAudio({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': audioAsset },
    })
    cy.get('.meta').should('exist')
    cy.contains('recording.mp3').should('exist')
    cy.contains('audio/mpeg').should('exist')
  })

  it('hides action buttons in readonly mode', () => {
    mountAudio({ readonly: true })
    cy.get('button[title="Upload file"]').should('not.exist')
    cy.get('button[title="Add file from URL"]').should('not.exist')
  })

  it('hides overlay menu in readonly mode with file present', () => {
    mountAudio({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': audioAsset },
      readonly: true,
    })
    cy.get('.btn-overlay').should('not.exist')
  })

  it('emits error:true when required and no file', () => {
    const onError = cy.spy().as('error')
    mountAudio({ config: { required: true }, onError })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when required and file is present', () => {
    const onError = cy.spy().as('error')
    mountAudio({
      config: { required: true },
      modelValue: { id: '1', type: 'file' },
      assets: { '1': audioAsset },
      onError,
    })
    cy.get('@error').should('have.been.calledWith', false)
  })
})
