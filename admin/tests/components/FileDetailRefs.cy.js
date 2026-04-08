import FileDetailRefs from '../../js/components/FileDetailRefs.vue'
import { useUserStore } from '../../js/stores'

const stubs = {
}

function mountRefs(props = {}, perms = {}) {
  return cy.mount(FileDetailRefs, {
    props: {
      item: { id: null },
      ...props,
    },
    global: {
      stubs,
      provide: {
        openView: () => {},
      },
    },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
  })
}

describe('FileDetailRefs', () => {
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
    cy.contains('Pages').should('not.exist')
  })

  it('does not show elements panel when no elements data', () => {
    mountRefs()
    cy.contains('Elements').should('not.exist')
  })

  it('does not show versions panel when no versions data', () => {
    mountRefs()
    cy.contains('Versions').should('not.exist')
  })

  it('does not fetch data when item has no id', () => {
    mountRefs({ item: { id: null } }, { 'file:view': true })
    cy.get('.v-table').should('not.exist')
  })
})
