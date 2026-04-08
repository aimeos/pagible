import ElementDetailItem from '../../js/components/ElementDetailItem.vue'
import { useUserStore, useSchemaStore } from '../../js/stores'

const stubs = {
  Fields: { template: '<div class="fields-stub" />' },
}

const schemas = {
  content: {
    heading: { fields: { title: { type: 'string', label: 'Title' } } },
  },
}

const item = {
  id: '1',
  name: 'Test Element',
  type: 'heading',
  lang: 'en',
  data: { title: 'Hello' },
  files: [],
}

function setupSchemaPlugin() {
  return {
    install() {
      const store = useSchemaStore()
      Object.assign(store, schemas)
    },
  }
}

function mountDetail(props = {}, perms = {}) {
  return cy.mount(ElementDetailItem, {
    props: { item: { ...item }, assets: {}, ...props },
    global: {
      stubs,
      plugins: [setupSchemaPlugin()],
      provide: {
        locales: (all) => all ? ['', 'en', 'de'] : ['en', 'de'],
      },
    },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('ElementDetailItem', () => {
  it('renders the component', () => {
    mountDetail()
    cy.get('.v-container').should('exist')
  })

  it('renders the name text field', () => {
    mountDetail()
    cy.get('.v-text-field').should('exist')
    cy.get('input').should('have.value', 'Test Element')
  })

  it('renders the language select', () => {
    mountDetail()
    cy.get('.v-select').should('exist')
  })

  it('renders the Fields stub', () => {
    mountDetail()
    cy.get('.fields-stub').should('exist')
  })

  it('makes name field readonly without element:save permission', () => {
    mountDetail()
    cy.get('input').first().should('have.attr', 'readonly')
  })

  it('makes name field editable with element:save permission', () => {
    mountDetail({}, { 'element:save': true })
    cy.get('input').first().should('not.have.attr', 'readonly')
  })

  it('emits update:item when name is changed', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(ElementDetailItem, {
      props: {
        item: { ...item },
        assets: {},
        'onUpdate:item': onUpdate,
      },
      global: {
        stubs,
        plugins: [setupSchemaPlugin()],
        provide: {
          locales: () => ['en', 'de'],
        },
      },
    }).then(() => {
      const user = useUserStore()
      user.me = { permission: { 'element:save': true } }
    })
    cy.get('input').first().clear().type('New Name')
    cy.get('@update').should('have.been.called')
  })
})
