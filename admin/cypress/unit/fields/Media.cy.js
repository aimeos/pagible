import MediaField from '../../../js/fields/Media.vue'
import { useUserStore } from '../../../js/stores'

const image = {
  id: '1',
  name: 'photo.jpg',
  path: 'files/photo.jpg',
  mime: 'image/jpeg',
  previews: {}
}

const video = {
  ...image,
  id: '2',
  name: 'clip.mp4',
  path: 'files/clip.mp4',
  mime: 'video/mp4'
}

const stubs = {
  FileAiDialog: { template: '<div />' },
  FileDialog: { template: '<div />' },
  FileUrlDialog: { template: '<div />' }
}

function mountMedia(props = {}, perms = {}) {
  return cy
    .mount(MediaField, {
      props: { config: {}, assets: {}, ...props },
      global: { stubs }
    })
    .then(() => {
      const user = useUserStore()
      user.me = { permission: perms }
    })
}

describe('Media', () => {
  it('uses the compact media actions without a dropzone', () => {
    mountMedia({}, { 'file:view': true, 'image:imagine': true })

    cy.get('button.btn-add').should('exist')
    cy.get('button.btn-create').should('exist')
    cy.get('button.btn-upload').should('exist')
    cy.get('.dropzone').should('not.exist')
  })

  it('renders image files as editable image previews', () => {
    mountMedia({ modelValue: { id: image.id, type: 'file' }, assets: { [image.id]: image } })

    cy.get('button.file-preview .v-img').should('exist')
  })

  it('renders video controls outside button-like containers', () => {
    mountMedia({ modelValue: { id: video.id, type: 'file' }, assets: { [video.id]: video } })

    cy.get('.file').should('not.have.attr', 'role')
    cy.get('video').should('have.attr', 'controls')
    cy.get('video').parents('button').should('not.exist')
  })

  it('shows a lock for protected media', () => {
    mountMedia({
      label: 'Media',
      modelValue: { id: image.id, type: 'file' },
      assets: { [image.id]: { ...image, disk: 'private' } }
    })

    cy.get('.field-label > .field-lock + span').should('contain', 'Media')
  })
})
