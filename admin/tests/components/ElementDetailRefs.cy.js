import ElementDetailRefs from '../../js/components/ElementDetailRefs.vue'
import { useUserStore } from '../../js/stores'

const stubs = {
}

function mountRefs(props = {}, perms = {}) {
  return cy.mount(ElementDetailRefs, {
    props: {
      item: { id: null },
      ...props,
    },
    global: { stubs },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('ElementDetailRefs', () => {
  it('renders the component', () => {
    mountRefs()
    cy.get('.v-container').should('exist')
  })

  it('renders expansion panels', () => {
    mountRefs()
    cy.get('.v-expansion-panels').should('exist')
  })

  it('does not show pages panel when no pages data', () => {
    mountRefs()
    cy.contains('Shared elements').should('not.exist')
  })

  it('does not show versions panel when no versions data', () => {
    mountRefs()
    cy.contains('Versions').should('not.exist')
  })

  it('does not fetch data when item has no id', () => {
    mountRefs({ item: { id: null } }, { 'element:view': true })
    // No tables should be rendered
    cy.get('.v-table').should('not.exist')
  })
})
