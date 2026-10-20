import { h, nextTick, ref } from 'vue'
import DetailRefs from '../../../js/components/DetailRefs.vue'
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

    it('shows the references fetched for the first item', () => {
      const bypages = [{ id: 'page-1', path: 'used', name: 'Used page', restricted: false }]
      const query = Cypress.sinon.stub().resolves({ data: { [type]: { bypages } } })

      cy.mount(DetailRefs, {
        props: { item: { id: 'item-1' }, type },
        global: {
          stubs,
          mocks: { $apollo: { query } },
          plugins: [{
            install() {
              useUserStore().me = { permission: { [type + ':view']: true, 'page:view': true } }
            }
          }],
        },
      })

      cy.get('.v-table.pages tbody tr').should('have.length', 1).and('contain', '/used')
    })

    it('fetches the references again only for another item', () => {
      const query = Cypress.sinon.stub().resolves({ data: { [type]: {} } })

      const item = ref({ id: 'item-1', name: 'first' })
      const Host = { render: () => h(DetailRefs, { item: item.value, type }) }

      cy.mount(Host, {
        global: {
          stubs,
          mocks: { $apollo: { query } },
          plugins: [{
            install() {
              useUserStore().me = { permission: { [type + ':view']: true } }
            }
          }],
        },
      }).then(async () => {
        expect(query).to.have.been.calledOnce
        expect(query.firstCall.args[0].variables).to.deep.equal({ id: 'item-1' })

        // the detail view replaces the item object after saving
        item.value = { id: 'item-1', name: 'saved' }
        await nextTick()
        expect(query).to.have.been.calledOnce

        item.value = { id: 'item-2', name: 'other' }
        await nextTick()
        expect(query).to.have.been.calledTwice
        expect(query.secondCall.args[0].variables).to.deep.equal({ id: 'item-2' })
      })
    })

    it('shows a lock icon only for restricted page references', () => {
      mountRefs(type, {}, { 'page:view': true }).then(({ wrapper }) => {
        wrapper.findComponent(DetailRefs).vm.references = {
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

        vm.references = { bypages: [page] }
        cy.stub(vm, 'open').as('open')

        cy.get('.v-table.pages tbody tr').should('contain', '/page').click()
        cy.get('@open').should('have.been.calledOnceWith', 'Page', page)
      })
    })

    it('opens element references with the keyboard', () => {
      mountRefs(type, {}, { 'element:view': true }).then(({ wrapper }) => {
        const vm = wrapper.findComponent(DetailRefs).vm
        const element = { id: 'elem-1', type: 'text', name: 'Element' }

        vm.references = { byelements: [element] }
        cy.stub(vm, 'open').as('open')

        cy.get('.v-table.elements tbody tr').should('have.attr', 'tabindex', '0').focus().type('{enter}')
        cy.get('@open').should('have.been.calledOnceWith', 'Element', element)
      })
    })

    it('opens referenced pages as stacked view', () => {
      mountRefs(type).then(async ({ wrapper }) => {
        const viewStack = useViewStack()

        await wrapper.findComponent(DetailRefs).vm.open('Page', { id: 'page-1' })

        expect(viewStack.stack).to.have.length(1)
        expect(viewStack.stack[0].props.stacked).to.be.true
        expect(viewStack.stack[0].props.initial.id).to.equal('page-1')
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
