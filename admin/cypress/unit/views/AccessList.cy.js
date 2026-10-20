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
      view.items = ['editor']
      view.checked = new Set(['editor'])
      view.remove()

      const confirm = useConfirmStore()
      expect(confirm.show).to.be.true
      expect(confirm.items).to.deep.equal([{ name: 'editor' }])
      confirm.close(false)
    })
  })

  it('adds the value with Enter but not while an input method composes it', () => {
    let add

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
      const view = wrapper.findComponent(AccessList).vm
      add = cy.stub(view, 'add')
      view.addDialog = true
    })

    cy.get('.v-dialog:visible input').type('編集')
      .trigger('keydown', { eventConstructor: 'KeyboardEvent', key: 'Enter', keyCode: 229 })
    // the key up of the confirming Enter looks like a normal one
    cy.get('.v-dialog:visible input').trigger('keyup', { eventConstructor: 'KeyboardEvent', key: 'Enter', keyCode: 13 })
    cy.then(() => expect(add).not.to.have.been.called)

    cy.get('.v-dialog:visible input').type('{enter}')
    cy.then(() => expect(add).to.have.been.calledOnce)
  })

  it('purges only the checked values matching the search term', () => {
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
      const view = wrapper.findComponent(AccessList).vm

      view.items = ['editor', 'admin']
      view.checked = new Set(['editor', 'admin'])
      view.term = 'edit'
      view.remove()

      const confirm = useConfirmStore()
      expect(view.selection).to.deep.equal(['editor'])
      expect(confirm.items).to.deep.equal([{ name: 'editor' }])
      confirm.close(false)
    })
  })

  it('shows the users tab without redirecting if the roles are not accessible', () => {
    const replace = cy.stub()
    const query = cy.stub().resolves({ data: {} })

    cy.mount(AccessList, {
      global: {
        mocks: {
          $apollo: { query },
          $route: { name: 'access:view', query: { tab: 'roles' } },
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

      expect(replace).not.to.have.been.called
      expect(view.activeTab).to.equal('users')
      expect(view.loading).to.equal(false)
      expect(query).not.to.have.been.called
    })
  })
})

