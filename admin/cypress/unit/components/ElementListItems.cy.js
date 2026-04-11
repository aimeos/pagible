import ElementListItems from '../../../js/components/ElementListItems.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
  SchemaItems: { template: '<div class="schema-items-stub" />' },
}

function mountList(props = {}, perms = {}) {
  return cy.mount(ElementListItems, {
    props: {
      ...props,
    },
    global: {
      stubs,
      provide: {
        debounce: (fn) => fn,
      },
    },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('ElementListItems', () => {
  it('renders the component', () => {
    mountList({}, { 'element:view': true })
    cy.get('.header').should('exist')
  })

  it('renders search field', () => {
    mountList({}, { 'element:view': true })
    cy.get('.v-text-field').should('exist')
  })

  it('renders sort menu button', () => {
    mountList({}, { 'element:view': true })
    cy.get('button[title="Sort by"]').should('exist')
  })

  it('renders checkbox for bulk selection', () => {
    mountList({}, { 'element:view': true })
    cy.get('.v-checkbox-btn').should('exist')
  })

  it('shows add button with element:add permission and not embed', () => {
    mountList({ embed: false }, { 'element:view': true, 'element:add': true })
    cy.get('button[title="Add element"]').should('exist')
  })

  it('hides add button when embed is true', () => {
    mountList({ embed: true }, { 'element:view': true, 'element:add': true })
    cy.get('button[title="Add element"]').should('not.exist')
  })

  it('hides add button without element:add permission', () => {
    mountList({}, { 'element:view': true })
    cy.get('button[title="Add element"]').should('not.exist')
  })

  it('renders reload button', () => {
    mountList({}, { 'element:view': true })
    cy.get('button[title="Reload elements"]').should('exist')
  })

  it('shows loading state initially', () => {
    mountList({}, { 'element:view': true })
    cy.contains('Loading').should('exist')
  })
})
