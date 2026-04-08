import PageDetailItemConfig from '../../js/components/PageDetailItemConfig.vue'
import { useUserStore, useSchemaStore } from '../../js/stores'

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
  config: {
    tracking: { fields: { code: { type: 'string', label: 'Code' } } },
  },
}

const item = {
  id: '1',
  config: {
    tracking: { id: 'cfg1', type: 'tracking', data: { code: 'UA-123' } },
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

function mountConfig(props = {}, perms = {}) {
  return cy.mount(PageDetailItemConfig, {
    props: {
      item: { ...item, config: { ...item.config } },
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

describe('PageDetailItemConfig', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the component', () => {
    mountConfig()
    cy.get('.v-container').should('exist')
  })

  it('renders expansion panels for config items', () => {
    mountConfig()
    cy.get('.v-expansion-panel').should('have.length', 1)
  })

  it('displays config element type', () => {
    mountConfig()
    cy.get('.element-type').should('contain', 'tracking')
  })

  it('shows add button with page:save and config:page permissions', () => {
    mountConfig({}, { 'page:save': true, 'config:page': true })
    cy.get('button[title="Add element"]').should('exist')
  })

  it('hides add button without page:save permission', () => {
    mountConfig()
    cy.get('button[title="Add element"]').should('not.exist')
  })

  it('shows remove button with proper permissions', () => {
    mountConfig({}, { 'page:save': true, 'config:page': true })
    cy.get('.v-expansion-panel').first().click()
    cy.get('button[title="Remove content element"]').should('exist')
  })

  it('renders Fields stub inside expansion panel', () => {
    mountConfig()
    cy.get('.v-expansion-panel').first().click()
    cy.get('.fields-stub').should('exist')
  })
})
