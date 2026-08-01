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

function mountList(props = {}, perms = {}, apollo = {}) {
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
      mocks: {
        $apollo: {
          mutate: () => Promise.resolve({ data: {} }),
          ...apollo,
        },
      },
    },
  }).then(({ wrapper }) => {
    const user = useUserStore()
    user.me = { permission: perms }

    return { wrapper }
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
    cy.get('button.btn-add').should('exist')
  })

  it('hides add element button without page:save permission', () => {
    mountList()
    cy.get('button.btn-add').should('not.exist')
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

  it('adds files from a selected shared element to the page assets', () => {
    const assets = {}
    const elements = {}

    mountList({ assets, content: [], elements }).then(() => {
      const vm = Cypress.vueWrapper.findComponent(PageDetailContentList).vm
      const file = { disk: 'private', id: 'file-1', path: 'draft.jpg', previews: {} }

      vm.add({ id: 'element-1', name: 'Shared', files: [file] }, null)

      expect(assets['file-1']).to.equal(file)
      expect(elements['element-1'].files).to.deep.equal([file])
    })
  })

  it('keeps normalized files when making content shared', () => {
    const assets = {}
    const elements = {}
    const mutate = cy.stub().resolves({
      data: {
        addElement: {
          id: 'element-1',
          data: '{}',
          files: [{
            disk: 'private',
            id: 'file-1',
            path: 'published.jpg',
            previews: '{}',
            latest: {
              data: '{"path":"draft.jpg","previews":{"500":"draft-500.webp"}}',
              aux: '{}',
            },
          }],
        },
      },
    })
    const item = { id: 'c1', type: 'heading', group: 'main', data: {} }

    mountList({ assets, content: [item], elements }, { 'element:add': true }, { mutate }).then(({ wrapper }) => {
      wrapper.findComponent(PageDetailContentList).vm.share(0)
    })

    cy.wrap(elements).should((value) => {
      expect(value['element-1'].files[0].path).to.equal('draft.jpg')
      expect(value['element-1'].files[0].previews).to.deep.equal({ 500: 'draft-500.webp' })
      expect(assets['file-1']).to.equal(value['element-1'].files[0])
    })
  })
})
