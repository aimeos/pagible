import AccessList from '../../../js/views/AccessList.vue'
import { useConfirmStore, useUserStore } from '../../../js/stores'

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

  it('resets the loading state when redirecting to the users tab', () => {
    const replace = cy.stub()
    const query = cy.stub().resolves({ data: {} })

    cy.mount(AccessList, {
      global: {
        mocks: {
          $apollo: { query },
          $route: { name: 'access:view', query: {} },
          $router: { replace }
        },
        stubs: {
          AccessUsers: { template: '<div />' },
          Navigation: { template: '<div />' },
          User: { template: '<div />' }
        },
        plugins: [{
          install() {
            useUserStore().me = { permission: { 'user:create': true } }
          }
        }]
      }
    }).then(({ wrapper }) => {
      const view = wrapper.findComponent(AccessList).vm

      expect(replace).to.have.been.calledOnceWith({ name: 'access:view', query: { tab: 'users' } })
      expect(view.loading).to.equal(false)
      expect(query).not.to.have.been.called
    })
  })
})

