import PageDetailContent from '../../../js/components/PageDetailContent.vue'
import { useSchemaStore } from '../../../js/stores'

const stubs = {
  PageDetailContentList: { template: '<div class="content-list-stub" />' },
}

const item = {
  id: '1',
  type: 'page',
  theme: 'cms',
  content: [
    { id: 'c1', type: 'heading', group: 'main', data: { title: 'Hello' } },
  ],
}

function mountContent(props = {}, sections = null) {
  const plugins = []
  if (sections) {
    plugins.push({
      install() {
        const schemas = useSchemaStore()
        schemas.themes = { cms: { types: { page: { sections } } } }
      }
    })
  }
  return cy.mount(PageDetailContent, {
    props: {
      item: { ...item },
      assets: {},
      elements: {},
      ...props,
    },
    global: { stubs, plugins },
  })
}

describe('PageDetailContent', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the component', () => {
    mountContent()
    cy.get('.v-container').should('exist')
  })

  it('renders the content list stub', () => {
    mountContent()
    cy.get('.content-list-stub').should('exist')
  })

  it('renders single section without tabs', () => {
    mountContent()
    cy.get('.v-tab').should('not.exist')
  })

  it('renders tabs when multiple sections are configured', () => {
    mountContent({}, ['main', 'sidebar'])
    cy.get('.v-tab').should('have.length', 2)
  })
})
