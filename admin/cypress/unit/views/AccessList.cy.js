import AccessList from '../../../js/views/AccessList.vue'
import { useConfirmStore } from '../../../js/stores'

describe('AccessList', () => {
  it('uses the standard shell for access dialogs', () => {
    let view

    cy.mount(AccessList, {
      global: {
        mocks: {
          $route: { name: 'access:view', query: {} },
          $router: { replace: cy.stub() }
        },
        stubs: {
          AccessUsers: { template: '<div />' },
          Navigation: { template: '<div />' },
          User: { template: '<div />' }
        }
      }
    }).then(({ wrapper }) => {
      view = wrapper.findComponent(AccessList).vm
      view.addDialog = true
    })

    cy.contains('.v-dialog:visible .v-toolbar-title', 'Add access value').should('exist')
    cy.get('.v-dialog:visible button[aria-label="Close"]').click()

    cy.then(() => {
      view.checked = new Set(['editor'])
      view.remove()

      const confirm = useConfirmStore()
      expect(confirm.show).to.be.true
      expect(confirm.items).to.deep.equal([{ name: 'editor' }])
      confirm.close(false)
    })
  })
})
