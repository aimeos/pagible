import FileListItems from '../../../js/components/FileListItems.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
}

function mountList(props = {}, perms = {}, apollo = {}) {
  return cy.mount(FileListItems, {
    props: {
      ...props,
    },
    global: {
      stubs,
      provide: {
        debounce: (fn) => fn,
        url: (path) => path,
        srcset: () => '',
      },
      mocks: {
        $apollo: {
          query: () => Promise.resolve({
            data: { files: { data: [], paginatorInfo: { lastPage: 1 } } },
          }),
          mutate: () => Promise.resolve({ data: {} }),
          provider: { defaultClient: { cache: { evict() {}, gc() {} } } },
          ...apollo,
        },
      },
    },
  }).then(({ wrapper }) => {
    const user = useUserStore()
    user.me = { permission: perms }

    return { wrapper }
  })
}

describe('FileListItems', () => {
  it('renders the component', () => {
    mountList({}, { 'file:view': true })
    cy.get('.header').should('exist')
  })

  it('renders search field', () => {
    mountList({}, { 'file:view': true })
    cy.get('.v-text-field').should('exist')
  })

  it('renders checkbox for bulk selection', () => {
    mountList({}, { 'file:view': true })
    cy.get('.v-checkbox-btn').should('exist')
  })

  it('renders sort menu button', () => {
    mountList({}, { 'file:view': true })
    cy.get('.btn-sort button').should('exist')
  })

  it('renders grid/list toggle button', () => {
    mountList({}, { 'file:view': true })
    cy.get('button.btn-grid, button.btn-list').should('exist')
  })

  it('shows add button with file:add permission and not embed', () => {
    mountList({ embed: false }, { 'file:view': true, 'file:add': true })
    cy.get('button.btn-add').should('exist')
  })

  it('hides add button when embed is true', () => {
    mountList({ embed: true }, { 'file:view': true, 'file:add': true })
    cy.get('button.btn-add').should('not.exist')
  })

  it('hides add button without file:add permission', () => {
    mountList({}, { 'file:view': true })
    cy.get('button.btn-add').should('not.exist')
  })

  it('renders reload button', () => {
    mountList({}, { 'file:view': true })
    cy.get('button.btn-reload').should('exist')
  })

  it('shows loading state initially', () => {
    mountList({}, { 'file:view': true })
    cy.contains('Loading').should('exist')
  })

  it('starts in list view by default', () => {
    mountList({}, { 'file:view': true })
    cy.get('button.btn-grid').should('exist')
  })

  it('starts in grid view when grid prop is true', () => {
    mountList({ grid: true }, { 'file:view': true })
    cy.get('button.btn-list').should('exist')
  })

  it('edits one item without changing the bulk selection', () => {
    const mutate = cy.stub().resolves({ data: { bulkFile: { ids: ['file-1'] } } })

    mountList({}, { 'file:save': true, 'file:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileListItems).vm
      const item = { id: 'file-1' }
      vm.items = [item, { id: 'file-2' }]
      vm.checked = new Set(['file-2'])

      vm.edit(item)
      expect(vm.editIds).to.deep.equal(['file-1'])
      expect(vm.editDialog).to.equal(true)

      return vm.save('de').then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.equal({
          id: ['file-1'],
          input: { lang: 'de' },
        })
        expect([...vm.checked]).to.deep.equal(['file-2'])
      })
    })
  })
})
