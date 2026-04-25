import PageDetailItemSection from '../../../js/components/PageDetailItemSection.vue'
import { useUserStore, useSchemaStore } from '../../../js/stores'

const stubs = {
  Fields: { template: '<div class="fields-stub" />' },
  SchemaItems: { template: '<div class="schema-items-stub" />' },
  VDialog: {
    template: '<div class="v-dialog" v-if="modelValue"><slot /></div>',
    props: ['modelValue'],
    emits: ['update:modelValue'],
  },
}

const schemas = {
  meta: {
    seo: { fields: { description: { type: 'string', label: 'Description' } } },
  },
}

const item = {
  id: '1',
  meta: {
    seo: { id: 'meta1', type: 'seo', data: { description: 'Test' } },
  },
}

function setupSchemaPlugin() {
  return {
    install() {
      const store = useSchemaStore()
      Object.assign(store, schemas)
    },
  }
}

function mountMeta(props = {}, perms = {}) {
  return cy.mount(PageDetailItemSection, {
    props: {
      item: { ...item, meta: { ...item.meta } },
      section: 'meta',
      assets: {},
      ...props,
    },
    global: {
      stubs,
      plugins: [setupSchemaPlugin()],
    },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('PageDetailItemSection (meta)', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the component', () => {
    mountMeta()
    cy.get('.v-container').should('exist')
  })

  it('renders expansion panels for meta items', () => {
    mountMeta()
    cy.get('.v-expansion-panel').should('have.length', 1)
  })

  it('displays meta element type', () => {
    mountMeta()
    cy.get('.element-type').should('contain', 'seo')
  })

  it('shows add button with page:save permission', () => {
    mountMeta({}, { 'page:save': true })
    cy.get('button[title="Add element"]').should('exist')
  })

  it('hides add button without page:save permission', () => {
    mountMeta()
    cy.get('button[title="Add element"]').should('not.exist')
  })

  it('shows remove button with page:save permission', () => {
    mountMeta({}, { 'page:save': true })
    cy.get('.v-expansion-panel').first().click()
    cy.get('button[title="Remove content element"]').should('exist')
  })

  it('renders Fields stub inside expansion panel', () => {
    mountMeta()
    cy.get('.v-expansion-panel').first().click()
    cy.get('.fields-stub').should('exist')
  })

  it('renders without meta items', () => {
    mountMeta({ item: { id: '1', meta: {} } })
    cy.get('.v-expansion-panel').should('not.exist')
  })
})
