import FileDetailItemImage from '../../src/components/FileDetailItemImage.vue'
import { useUserStore } from '../../src/stores'

const item = {
  id: '1',
  name: 'photo.jpg',
  path: 'files/photo.jpg',
  mime: 'image/jpeg',
  previews: {},
}

function mountImage(props = {}, perms = {}) {
  return cy.mount(FileDetailItemImage, {
    props: {
      item: { ...item },
      readonly: false,
      ...props,
    },
    global: {
      provide: {
        base64ToBlob: () => new Blob(),
        url: (path) => path,
      },
    },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('FileDetailItemImage', () => {
  it('renders the editor container', () => {
    mountImage()
    cy.get('.editor-container').should('exist')
  })

  it('renders the image element', () => {
    mountImage()
    cy.get('img.element').should('exist')
    cy.get('img.element').should('have.attr', 'src', '/storage/files/photo.jpg')
  })

  it('shows toolbar when not readonly', () => {
    mountImage()
    cy.get('.toolbar').should('exist')
  })

  it('hides toolbar when readonly', () => {
    mountImage({ readonly: true })
    cy.get('.toolbar').should('not.exist')
  })

  it('renders crop button', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn[title="Crop selected area"]').should('exist')
  })

  it('renders rotate buttons', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn[title="Rotate counter-clockwise"]').should('exist')
    cy.get('.toolbar .v-btn[title="Rotate clockwise"]').should('exist')
  })

  it('renders flip buttons', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn[title="Flip horizontally"]').should('exist')
    cy.get('.toolbar .v-btn[title="Flip vertically"]').should('exist')
  })

  it('renders download button', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn[title="Download"]').should('exist')
  })

  it('shows erase button with image:erase permission', () => {
    mountImage({}, { 'file:save': true, 'image:erase': true })
    cy.get('.toolbar .v-btn[title="Erase selected area"]').should('exist')
  })

  it('hides erase button without image:erase permission', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn[title="Erase selected area"]').should('not.exist')
  })

  it('shows isolate button with image:isolate permission', () => {
    mountImage({}, { 'file:save': true, 'image:isolate': true })
    cy.get('.toolbar .v-btn[title="Remove background"]').should('exist')
  })

  it('hides isolate button without image:isolate permission', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn[title="Remove background"]').should('not.exist')
  })

  it('shows upscale menu with image:upscale permission', () => {
    mountImage({}, { 'file:save': true, 'image:upscale': true })
    cy.get('.toolbar .v-btn[title="Upscale image"]').should('exist')
  })

  it('hides upscale menu without image:upscale permission', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn[title="Upscale image"]').should('not.exist')
  })

  it('shows expand button with image:uncrop permission', () => {
    mountImage({}, { 'file:save': true, 'image:uncrop': true })
    cy.get('.toolbar .v-btn[title="Expand image"]').should('exist')
  })

  it('hides expand button without image:uncrop permission', () => {
    mountImage({}, { 'file:save': true })
    cy.get('.toolbar .v-btn[title="Expand image"]').should('not.exist')
  })
})
