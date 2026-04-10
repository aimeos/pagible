import SchemaItems from '../../../js/components/SchemaItems.vue'
import { useSchemaStore } from '../../../js/stores'

const sampleSchemas = {
  page: {
    heading: { label: 'Heading', group: 'basic', icon: '' },
    text: { label: 'Text', group: 'basic', icon: '' },
    image: { label: 'Image', group: 'media', icon: '' },
  },
}

function mountWithSchemas(schemas = sampleSchemas) {
  return cy.mount(SchemaItems, { props: { type: 'page' } }).then(() => {
    const store = useSchemaStore()
    Object.assign(store, schemas)
  })
}

describe('SchemaItems', () => {
  it('renders tabs for each schema group', () => {
    mountWithSchemas()
    cy.get('.v-tab').should('contain', 'basic')
    cy.get('.v-tab').should('contain', 'media')
  })

  it('renders a button for each item in the active group', () => {
    mountWithSchemas()
    // "basic" tab is active by default
    cy.get('.v-btn').should('contain', 'Heading')
    cy.get('.v-btn').should('contain', 'Text')
  })

  it('switches content when another tab is clicked', () => {
    mountWithSchemas()
    cy.contains('.v-tab', 'media').click()
    cy.get('.v-btn').should('contain', 'Image')
  })

  it('emits "add" with the schema type when a button is clicked', () => {
    const onAdd = cy.spy().as('add')
    cy.mount(SchemaItems, {
      props: { type: 'page', onAdd },
    }).then(() => {
      const store = useSchemaStore()
      Object.assign(store, sampleSchemas)
    })
    cy.contains('.v-btn', 'Heading').click()
    cy.get('@add').should('have.been.calledWithMatch', { type: 'heading' })
  })

  it('renders no tabs when there are no schemas for the type', () => {
    cy.mount(SchemaItems, { props: { type: 'unknown' } })
    cy.get('.v-tab').should('not.exist')
  })

  it('groups items under "uncategorized" when no group is specified', () => {
    cy.mount(SchemaItems, { props: { type: 'page' } }).then(() => {
      const store = useSchemaStore()
      Object.assign(store, {
        page: { nogroup: { label: 'NoGroup', icon: '' } },
      })
    })
    cy.get('.v-tab').should('contain', 'uncategorized')
  })
})
