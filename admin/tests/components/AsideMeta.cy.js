import AsideMeta from '../../src/components/AsideMeta.vue'

function mountMeta(item = {}) {
  return cy.mount(AsideMeta, {
    props: { item },
  })
}

describe('AsideMeta', () => {
  it('renders a navigation drawer', () => {
    mountMeta()
    cy.get('.v-navigation-drawer').should('exist')
  })

  it('renders "Meta data" heading', () => {
    mountMeta()
    cy.contains('Meta data').should('exist')
  })

  it('displays the item id when present', () => {
    mountMeta({ id: '42' })
    cy.contains('42').should('exist')
  })

  it('displays the item mime when present', () => {
    mountMeta({ mime: 'image/png' })
    cy.contains('image/png').should('exist')
  })

  it('displays the item editor when present', () => {
    mountMeta({ editor: 'admin@example.com' })
    cy.contains('admin@example.com').should('exist')
  })

  it('displays the created date when present', () => {
    mountMeta({ created_at: '2024-06-15T10:30:00Z' })
    cy.get('.v-list-item').should('have.length.greaterThan', 0)
  })

  it('renders no data items when item is empty', () => {
    mountMeta({})
    cy.get('.v-list-item .name').should('not.exist')
  })

  it('renders all metadata fields when all are present', () => {
    mountMeta({
      id: '1',
      mime: 'text/html',
      editor: 'test',
      created_at: '2024-01-01T00:00:00Z',
      updated_at: '2024-01-02T00:00:00Z',
      deleted_at: '2024-01-03T00:00:00Z',
    })
    cy.get('.v-list-item .name').should('have.length', 6)
  })
})
