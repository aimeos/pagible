import AccessUsers from '../../../js/components/AccessUsers.vue'
import { apolloClient } from '../../../js/graphql'

function mountUsers() {
  return cy.mount(AccessUsers).then(({ wrapper }) => {
    const vm = wrapper.findComponent(AccessUsers).vm

    vm.permissionOptions = { roles: ['editor', 'publisher'], permissions: [] }
    vm.result = { id: '1', email: 'a@b.c', access: [], permissions: ['editor', 'page:view'] }

    return vm
  })
}

describe('AccessUsers', () => {
  it('keeps known roles selected next to custom permissions', () => {
    mountUsers().then((vm) => {
      expect(vm.permissionRole).to.deep.equal(['editor', vm.customRoleValue])
    })
  })

  it('keeps the custom entry when it is removed from the draft', () => {
    mountUsers().then((vm) => {
      vm.backendRoleDraft = [...vm.permissionRole]
      vm.draft('backendRoleDraft', ['editor'])

      expect(vm.backendRoleDraft).to.deep.equal(['editor', vm.customRoleValue])
      expect(vm.hasBackendRoleChanges).to.equal(false)
    })
  })

  it('detects added roles as changes', () => {
    mountUsers().then((vm) => {
      vm.draft('backendRoleDraft', ['editor', 'publisher', vm.customRoleValue])
      expect(vm.hasBackendRoleChanges).to.equal(true)
    })
  })

  it('shows roles which are not configured any more as selected', () => {
    mountUsers().then((vm) => {
      vm.result = { id: '1', email: 'a@b.c', access: [], permissions: ['editor', 'retired'] }

      expect(vm.isBackendPermissionCustom).to.equal(false)
      expect(vm.permissionRole).to.deep.equal(['editor', 'retired'])
      expect(vm.permissionRoleItems).to.deep.equal(['editor', 'publisher', 'retired'])
    })
  })

  it('removes roles which are not configured any more when unselected', () => {
    mountUsers().then((vm) => {
      vm.result = { id: '1', email: 'a@b.c', access: [], permissions: ['editor', 'retired', 'page:view'] }
      const calls = []
      vm.change = (...args) => calls.push(args)

      vm.changePermissionRole(['editor', vm.customRoleValue])

      expect(calls).to.have.length(1)
      expect(calls[0][0]).to.equal('permissions')
      expect(calls[0][1]).to.deep.equal(['page:view', 'editor'])
    })
  })

  it('applies saved roles for users stored with a mixed-case email', () => {
    cy.stub(apolloClient, 'mutate').resolves({ data: { assignments: ['member'] } })

    mountUsers().then(async (vm) => {
      vm.result = { id: '1', email: 'John@Example.com', access: [], permissions: [] }
      vm.email = 'john@example.com'
      const add = cy.spy(vm.messages, 'add')

      await vm.change('access', ['member'], {})

      expect(vm.result.access).to.deep.equal(['member'])
      expect(add).to.have.been.calledWith('Access roles updated', 'success')
    })
  })

  it('ignores save results of another user found in the meantime', () => {
    let resolve
    cy.stub(apolloClient, 'mutate').returns(new Promise((r) => { resolve = r }))

    mountUsers().then(async (vm) => {
      vm.result = { id: '1', email: 'a@b.c', access: [], permissions: [] }
      const pending = vm.change('access', ['member'], {})

      vm.result = { id: '2', email: 'x@y.z', access: [], permissions: [] }
      resolve({ data: { assignments: ['member'] } })
      await pending

      expect(vm.result).to.deep.equal({ id: '2', email: 'x@y.z', access: [], permissions: [] })
    })
  })
})
