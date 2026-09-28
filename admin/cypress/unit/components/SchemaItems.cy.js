import '../../../js/assets/base.css'
import SchemaItems from '../../../js/components/SchemaItems.vue'
import { useSchemaStore } from '../../../js/stores'

const sampleSchemas = {
  page: {
    heading: { label: 'Heading', group: 'basic', icon: '', description: 'Section headline' },
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

function setupTranslations() {
  return {
    install(app) {
      app.config.globalProperties.$pgettext = (context, value) =>
        ({ 'sg:theme': 'Design', 'st:logo': 'Markenzeichen' })[`${context}:${value}`] || value
    },
  }
}

describe('SchemaItems', () => {
  it('renders tabs for each schema group', () => {
    mountWithSchemas()
    cy.get('.v-tab').should('contain', 'basic')
    cy.get('.v-tab').should('contain', 'media')
  })

  it('uses the accent tint for schema group tabs', () => {
    mountWithSchemas()
    cy.get('.v-tabs')
      .then(($tabs) => {
        $tabs[0].style.setProperty('transition', 'none')
        $tabs[0].style.setProperty('--v-theme-nav-accent', '96, 165, 250')
        $tabs[0].style.setProperty('--v-theme-on-surface', '15, 23, 42')
      })
      .should('have.css', 'background-color', 'rgba(96, 165, 250, 0.16)')
      .and('have.css', 'color', 'rgb(15, 23, 42)')
  })

  it('renders the name and description of each element', () => {
    mountWithSchemas()
    cy.contains('.item', 'Heading').find('.v-list-item-subtitle').should('contain', 'Section headline')
    cy.contains('.item', 'Text').find('.v-list-item-subtitle').should('not.exist')
  })

  it('renders elements in one or two columns depending on the width', () => {
    cy.viewport(1000, 600)
    mountWithSchemas()
    cy.get('.items .item').eq(1).then(($el) => {
      cy.get('.items .item').first().its('0.offsetTop').should('eq', $el[0].offsetTop)
    })
    cy.viewport(500, 600)
    cy.get('.items .item').eq(1).then(($el) => {
      cy.get('.items .item').first().its('0.offsetTop').should('be.lt', $el[0].offsetTop)
    })
  })

  it('searches in the element descriptions', () => {
    mountWithSchemas()
    cy.get('.search input').type('headline')
    cy.get('.items .item').should('have.length', 1).and('contain', 'Heading')
  })

  it('renders an item for each element in the active group', () => {
    mountWithSchemas()
    // "basic" tab is active by default
    cy.get('.item').should('contain', 'Heading')
    cy.get('.item').should('contain', 'Text')
  })

  it('switches content when another tab is clicked', () => {
    mountWithSchemas()
    cy.contains('.v-tab', 'media').click()
    cy.get('.item').should('contain', 'Image')
  })

  it('translates schema groups and config element labels in their contexts', () => {
    cy.mount(SchemaItems, {
      props: { type: 'config' },
      global: { plugins: [setupTranslations()] },
    }).then(() => {
      const store = useSchemaStore()
      Object.assign(store, { config: { logo: { group: 'theme', icon: '' } } })
    })

    cy.contains('.v-tab', 'Design').click()
    cy.get('.item').should('contain', 'Markenzeichen')
  })

  it('emits "add" with the schema type when a button is clicked', () => {
    const onAdd = cy.spy().as('add')
    cy.mount(SchemaItems, {
      props: { type: 'page', onAdd },
    }).then(() => {
      const store = useSchemaStore()
      Object.assign(store, sampleSchemas)
    })
    cy.contains('.item', 'Heading').click()
    cy.get('@add').should('have.been.calledWithMatch', { type: 'heading' })
  })

  it('renders the group tabs vertically', () => {
    cy.viewport(800, 600)
    mountWithSchemas()
    cy.get('.v-tabs').should('have.class', 'v-tabs--vertical')
  })

  it('renders the group tabs horizontally on small screens', () => {
    mountWithSchemas()
    cy.get('.v-tabs').should('have.class', 'v-tabs--horizontal')
  })

  it('searches for elements across all groups', () => {
    mountWithSchemas()
    cy.get('.search input').type('image')
    cy.get('.items .item').should('have.length', 1).and('contain', 'Image')
    cy.get('.search input').clear().type('e')
    cy.get('.items .item').should('have.length', 3)
  })

  it('shows a message when the search has no matches', () => {
    mountWithSchemas()
    cy.get('.search input').type('xyz')
    cy.get('.items .item').should('not.exist')
    cy.contains('No entries found').should('exist')
  })

  it('clears the search when a group tab is clicked', () => {
    mountWithSchemas()
    cy.get('.search input').type('image')
    cy.contains('.v-tab', 'basic').click()
    cy.get('.search input').should('have.value', '')
    cy.get('.items .item').should('contain', 'Heading')
  })

  it('sorts elements by name', () => {
    mountWithSchemas({
      page: {
        zeta: { label: 'Zeta', group: 'basic', icon: '' },
        alpha: { label: 'Alpha', group: 'basic', icon: '' },
      },
    })
    cy.get('.items .item').first().should('contain', 'Zeta')
    cy.get('.btn-sort button').click()
    cy.contains('.v-overlay .v-list .v-btn', 'Name').click()
    cy.get('.btn-sort button').should('contain', 'Name')
    cy.get('.items .item').first().should('contain', 'Alpha')
  })

  it('reloads the content elements', () => {
    mountWithSchemas().then(() => {
      cy.stub(useSchemaStore(), 'reload').resolves().as('reload')
    })
    cy.get('.btn-reload').click()
    cy.get('@reload').should('have.been.calledOnce')
  })

  it('shows the first group when there is no "basic" group', () => {
    mountWithSchemas({ page: { image: { label: 'Image', group: 'media', icon: '' } } })
    cy.get('.items .item').should('have.length', 1).and('contain', 'Image')
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
