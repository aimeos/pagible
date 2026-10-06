import DetailRefs from '../../../js/components/DetailRefs.vue'
import { isReactive } from 'vue'
import { useUserStore, useViewStack } from '../../../js/stores'

const stubs = {
}

function mountRefs(type, props = {}, perms = {}) {
  return cy.mount(DetailRefs, {
    props: {
      item: { id: null },
      type,
      ...props,
    },
    global: {
      stubs,
      provide: {
        openView: () => {},
      },
    },
  }).then(({ wrapper }) => {
    const user = useUserStore()
    user.me = { permission: perms }

    return { wrapper }
  })
}

for (const type of ['element', 'file']) {
  describe(`DetailRefs (${type})`, () => {
    it('renders the component', () => {
      mountRefs(type)
      cy.get('.v-container').should('exist')
    })

    it('renders expansion panels', () => {
      mountRefs(type)
      cy.get('.v-expansion-panels').should('exist')
    })

    it('does not show pages panel when no pages data', () => {
      mountRefs(type)
      cy.contains('Pages').should('not.exist')
    })

    it('does not show elements panel when no elements data', () => {
      mountRefs(type)
      cy.contains('Elements').should('not.exist')
    })

    it('does not show versions panel when no versions data', () => {
      mountRefs(type)
      cy.contains('Versions').should('not.exist')
    })

    it('does not fetch data when item has no id', () => {
      mountRefs(type, { item: { id: null } }, { [type + ':view']: true })
      // No tables should be rendered
      cy.get('.v-table').should('not.exist')
    })

    it('shows a lock icon only for restricted page references', () => {
      mountRefs(type, {}, { 'page:view': true }).then(({ wrapper }) => {
        wrapper.findComponent(DetailRefs).vm.refs = {
          bypages: [
            { id: 'page-1', path: 'public', name: 'Public page', restricted: false },
            { id: 'page-2', path: 'private', name: 'Restricted page', restricted: true },
          ],
        }

        cy.get('.item-access')
          .should('have.length', 1)
          .and('have.attr', 'title', 'Restricted')
      })
    })

    it('opens page references in a detail view', () => {
      mountRefs(type, {}, { 'page:view': true }).then(({ wrapper }) => {
        const vm = wrapper.findComponent(DetailRefs).vm
        const page = { id: 'page-1', path: 'page', name: 'Page' }

        vm.refs = { bypages: [page] }
        cy.stub(vm, 'open').as('open')

        cy.get('.v-table.pages tbody tr').should('contain', '/page').click()
        cy.get('@open').should('have.been.calledOnceWith', 'Page', page)
      })
    })

    it('opens referenced pages with a reactive stacked item', () => {
      mountRefs(type).then(async ({ wrapper }) => {
        const viewStack = useViewStack()

        await wrapper.findComponent(DetailRefs).vm.open('Page', { id: 'page-1' })

        expect(viewStack.stack).to.have.length(1)
        expect(viewStack.stack[0].props.stacked).to.be.true
        expect(viewStack.stack[0].props.item.id).to.equal('page-1')
        expect(isReactive(viewStack.stack[0].props.item)).to.be.true
      })
    })

    it('opens a version owner when its row is clicked', () => {
      mountRefs(type).then(({ wrapper }) => {
        const vm = wrapper.findComponent(DetailRefs).vm
        const version = { key: 'version-1', id: 'page-1', type: 'Page', published: 'yes' }

        vm.versions = [version]
        cy.stub(vm, 'open').as('open')

        cy.get('.v-table.versions tbody tr').click()
        cy.get('@open').should('have.been.calledOnceWith', 'Page', { id: 'page-1' })
      })
    })
  })
}
