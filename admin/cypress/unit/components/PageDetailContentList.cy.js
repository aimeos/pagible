import PageDetailContentList from '../../../js/components/PageDetailContentList.vue'
import { useUserStore, useSchemaStore } from '../../../js/stores'

const stubs = {
  Fields: { template: '<div class="fields-stub" />' },
  SchemaDialog: { template: '<div class="schema-dialog-stub" />' },
  VueDraggable: {
    render() { return this.$slots.default?.() || [] },
  },
}

const schemas = {
  content: {
    heading: { fields: { title: { type: 'string', label: 'Title' } } },
    text: { fields: { text: { type: 'text', label: 'Text' } } },
  },
}

const content = [
  { id: 'c1', type: 'heading', group: 'main', data: { title: 'Hello' } },
  { id: 'c2', type: 'text', group: 'main', data: { text: 'World' } },
]

function setupSchemaPlugin() {
  return {
    install() {
      const store = useSchemaStore()
      Object.assign(store, schemas)
    },
  }
}

function mountList(props = {}, perms = {}) {
  return cy.mount(PageDetailContentList, {
    props: {
      item: { id: '1', lang: 'en' },
      assets: {},
      content: [...content.map(c => ({ ...c }))],
      elements: {},
      ...props,
    },
    global: {
      stubs,
      plugins: [setupSchemaPlugin()],
      provide: {
        transcribe: () => Promise.resolve({ asText: () => '' }),
      },
    },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('PageDetailContentList', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the component', () => {
    mountList()
    cy.get('.v-expansion-panels').should('exist')
  })

  it('renders expansion panels for each content element', () => {
    mountList()
    cy.get('.v-expansion-panel').should('have.length', 2)
  })

  it('displays element type in panel title', () => {
    mountList()
    cy.get('.element-type').first().should('contain', 'heading')
  })

  it('displays element title in panel', () => {
    mountList()
    cy.get('.element-title').first().should('contain', 'Hello')
  })

  it('renders search field', () => {
    mountList()
    cy.get('.v-text-field').should('exist')
  })

  it('shows bulk actions menu with page:save permission', () => {
    mountList({}, { 'page:save': true })
    cy.get('.bulk').should('exist')
    cy.contains('Actions').should('exist')
  })

  it('hides bulk actions without page:save permission', () => {
    mountList()
    cy.get('.bulk').should('not.exist')
  })

  it('shows add element button with page:save permission', () => {
    mountList({}, { 'page:save': true })
    cy.get('button[title="Add element"]').should('exist')
  })

  it('hides add element button without page:save permission', () => {
    mountList()
    cy.get('button[title="Add element"]').should('not.exist')
  })

  it('shows AI refine textarea with page:refine permission', () => {
    mountList({}, { 'page:refine': true })
    cy.get('textarea').should('exist')
  })

  it('hides AI refine textarea without page:refine permission', () => {
    mountList()
    cy.get('.prompt').should('not.exist')
  })

  it('shows checkbox in panel title with page:save permission', () => {
    mountList({}, { 'page:save': true })
    cy.get('.v-expansion-panel-title .v-checkbox-btn').should('exist')
  })
})
