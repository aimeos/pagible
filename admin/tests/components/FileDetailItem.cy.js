import FileDetailItem from '../../src/components/FileDetailItem.vue'
import { useAuthStore } from '../../src/stores'

const stubs = {
  FileAiDialog: { template: '<div class="ai-dialog-stub" />' },
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
      item: { ...item },
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
    const auth = useAuthStore()
    auth.me = { permission: perms }
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

  it('shows image preview for image mime types', () => {
    mountDetail()
    cy.get('.v-img, img').should('exist')
  })

  it('makes name field readonly without file:save permission', () => {
    mountDetail()
    cy.get('input').first().should('have.attr', 'readonly')
  })

  it('makes name field editable with file:save permission', () => {
    mountDetail({}, { 'file:save': true })
    cy.get('input').first().should('not.have.attr', 'readonly')
  })
})
