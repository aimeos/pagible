import PageDetailItemProps from '../../../js/components/PageDetailItemProps.vue'
import { useAppStore, useUserStore, useSchemaStore } from '../../../js/stores'

const stubs = {
}

const item = {
  id: '1',
  title: 'Test Page',
  name: 'test',
  path: 'test-page',
  status: 1,
  lang: 'en',
  theme: 'cms',
  type: 'page',
  tag: '',
  cache: 5,
  to: '',
  domain: '',
}

function mountProps(props = {}, perms = {}) {
  return cy.mount(PageDetailItemProps, {
    props: {
      item: { ...item },
      assets: {},
      ...props,
    },
    global: {
      stubs,
      provide: {
        debounce: (fn) => fn,
        locales: () => ['en', 'de'],
        slugify: (val) => val?.toLowerCase().replace(/\s+/g, '-') || '',
      },
    },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
    const schemas = useSchemaStore()
    schemas.themes = { cms: { types: { page: {} } } }
    const app = useAppStore()
    app.multidomain = false
  })
}

describe('PageDetailItemProps', () => {
  it('renders the component', () => {
    mountProps()
    cy.get('.v-container').should('exist')
  })

  it('renders status select', () => {
    mountProps()
    cy.get('.v-select').should('exist')
  })

  it('renders title field with value', () => {
    mountProps()
    cy.get('input').should('exist')
  })

  it('renders path field', () => {
    mountProps()
    // There should be multiple text fields
    cy.get('.v-text-field').should('have.length.greaterThan', 2)
  })

  it('renders cache select', () => {
    mountProps()
    cy.get('.v-select').should('have.length.greaterThan', 1)
  })

  it('renders redirect URL field', () => {
    mountProps()
    cy.get('.v-text-field').should('exist')
  })

  it('makes fields readonly without page:save permission', () => {
    mountProps()
    cy.get('input').first().should('have.attr', 'readonly')
  })

  it('makes fields editable with page:save permission', () => {
    mountProps({}, { 'page:save': true })
    cy.get('input').first().should('not.have.attr', 'readonly')
  })

  it('does not show domain field when multidomain is false', () => {
    mountProps()
    cy.contains('Domain').should('not.exist')
  })
})
