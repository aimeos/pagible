import FileDetailRefs from '../../../js/components/FileDetailRefs.vue'
import { isReactive } from 'vue'
import { useUserStore, useViewStack } from '../../../js/stores'

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
  }).then(({ wrapper }) => {
    const user = useUserStore()
    user.me = { permission: perms }

    return { wrapper }
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

  it('shows a lock icon only for restricted page references', () => {
    mountRefs({}, { 'page:view': true }).then(({ wrapper }) => {
      wrapper.findComponent(FileDetailRefs).vm.file = {
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

  it('opens the item each version belongs to', () => {
    mountRefs().then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileDetailRefs).vm

      cy.stub(vm, 'openElement').as('openElement')
      cy.stub(vm, 'openFile').as('openFile')
      cy.stub(vm, 'openPage').as('openPage')

      vm.openVersion({ id: 'element-1', type: 'Element' })
      vm.openVersion({ id: 'file-1', type: 'File' })
      vm.openVersion({ id: 'page-1', type: 'Page' })

      cy.get('@openElement').should('have.been.calledOnceWith', { id: 'element-1' })
      cy.get('@openFile').should('have.been.calledOnceWith', { id: 'file-1' })
      cy.get('@openPage').should('have.been.calledOnceWith', { id: 'page-1' })
    })
  })

  it('opens referenced pages with a reactive stacked item', () => {
    mountRefs().then(async ({ wrapper }) => {
      const viewStack = useViewStack()

      await wrapper.findComponent(FileDetailRefs).vm.openPage({ id: 'page-1' })

      expect(viewStack.stack).to.have.length(1)
      expect(viewStack.stack[0].props.stacked).to.be.true
      expect(viewStack.stack[0].props.item.id).to.equal('page-1')
      expect(isReactive(viewStack.stack[0].props.item)).to.be.true
    })
  })

  it('opens a version owner when its row is clicked', () => {
    mountRefs().then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileDetailRefs).vm
      const version = { key: 'version-1', id: 'page-1', type: 'Page', published: 'yes' }

      vm.versions = [version]
      cy.stub(vm, 'openVersion').as('openVersion')

      cy.get('.v-table.versions tbody tr').click()
      cy.get('@openVersion').should('have.been.calledOnceWith', version)
    })
  })
})
