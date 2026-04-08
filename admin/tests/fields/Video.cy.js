import VideoField from '../../js/fields/Video.vue'
import { useUserStore } from '../../js/stores'

const videoAsset = {
  id: '1',
  name: 'clip.mp4',
  path: 'files/clip.mp4',
  mime: 'video/mp4',
  editor: 'admin',
  updated_at: '2024-01-01T00:00:00Z',
  description: { en: 'Test clip' },
  previews: {},
}

const stubs = {
  FileDialog: { template: '<div />' },
  FileUrlDialog: { template: '<div />' },
  FileListItems: { template: '<div />' },
  FileDetail: { template: '<div />' },
}

function mountVideo(props = {}, perms = {}) {
  return cy.mount(VideoField, {
    props: { config: {}, assets: {}, ...props },
    global: { stubs },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('Video', () => {
  it('renders the file container', () => {
    mountVideo()
    cy.get('.files').should('exist')
  })

  it('shows upload and URL buttons when no file is loaded', () => {
    mountVideo()
    cy.get('button[title="Upload file"]').should('exist')
    cy.get('button[title="Add file from URL"]').should('exist')
  })

  it('shows "Add file" button with file:view permission', () => {
    mountVideo({}, { 'file:view': true })
    cy.get('button[title="Add file"]').should('exist')
  })

  it('renders video element when file is loaded', () => {
    mountVideo({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': videoAsset },
    })
    cy.get('video').should('exist')
    cy.get('video').should('have.attr', 'controls')
  })

  it('sets video src via url() inject', () => {
    mountVideo({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': videoAsset },
    })
    cy.get('video').should('have.attr', 'src', '/storage/files/clip.mp4')
  })

  it('shows metadata when file is present', () => {
    mountVideo({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': videoAsset },
    })
    cy.get('.meta').should('exist')
    cy.contains('clip.mp4').should('exist')
    cy.contains('video/mp4').should('exist')
  })

  it('hides action buttons in readonly mode', () => {
    mountVideo({ readonly: true })
    cy.get('button[title="Upload file"]').should('not.exist')
    cy.get('button[title="Add file from URL"]').should('not.exist')
  })

  it('hides overlay menu in readonly mode with file present', () => {
    mountVideo({
      modelValue: { id: '1', type: 'file' },
      assets: { '1': videoAsset },
      readonly: true,
    })
    cy.get('.btn-overlay').should('not.exist')
  })

  it('emits error:true when required and no file', () => {
    const onError = cy.spy().as('error')
    mountVideo({ config: { required: true }, onError })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when required and file is present', () => {
    const onError = cy.spy().as('error')
    mountVideo({
      config: { required: true },
      modelValue: { id: '1', type: 'file' },
      assets: { '1': videoAsset },
      onError,
    })
    cy.get('@error').should('have.been.calledWith', false)
  })
})
