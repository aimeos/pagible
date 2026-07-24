import PageListItems from '../../../js/components/PageListItems.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
  Draggable: {
    template: '<div class="draggable-stub" />',
    props: ['modelValue'],
    data() { return { statsFlat: [] } },
    methods: { getSiblings() { return [] }, add() {}, remove() {}, move() {}, addMulti() {} },
  },
}

function mountList(props = {}, perms = {}, apollo = {}) {
  return cy.mount(PageListItems, {
    props: {
      ...props,
    },
    global: {
      stubs,
      provide: {
        debounce: (fn) => fn,
      },
      mocks: {
        $apollo: {
          query: () => Promise.resolve({
            data: { pages: { data: [], paginatorInfo: { currentPage: 1, lastPage: 1 } } }
          }),
          mutate: () => Promise.resolve({ data: {} }),
          provider: { defaultClient: { cache: { evict() {}, gc() {} } } },
          ...apollo,
        },
      },
      plugins: [{
        install() {
          const user = useUserStore()
          user.me = { permission: perms }
        }
      }],
    },
  })
}

describe('PageListItems', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the component', () => {
    mountList({}, { 'page:view': true })
    cy.get('.header').should('exist')
  })

  it('renders search field', () => {
    mountList({}, { 'page:view': true })
    cy.get('.v-text-field').should('exist')
  })

  it('renders checkbox for bulk selection', () => {
    mountList({}, { 'page:view': true })
    cy.get('.v-checkbox-btn').should('exist')
  })

  it('renders reload button', () => {
    mountList({}, { 'page:view': true })
    cy.get('button.btn-reload').should('exist')
  })

  it('shows add button with page:add permission and not embed', () => {
    mountList({ embed: false }, { 'page:view': true, 'page:add': true })
    cy.get('button.btn-add').should('exist')
  })

  it('hides add button when embed is true', () => {
    mountList({ embed: true }, { 'page:view': true, 'page:add': true })
    cy.get('button.btn-add').should('not.exist')
  })

  it('hides add button without page:add permission', () => {
    mountList({}, { 'page:view': true })
    cy.get('button.btn-add').should('not.exist')
  })

  it('shows no entries message when not loading and items are empty', () => {
    mountList({}, { 'page:view': true })
    cy.contains('No entries found').should('exist')
  })

  it('hides sort dropdown in tree view (default)', () => {
    mountList({ filter: { view: 'tree' } }, { 'page:view': true })
    cy.get('.btn-sort button').should('not.exist')
  })

  it('shows sort dropdown in list view', () => {
    mountList({ filter: { view: 'list' } }, { 'page:view': true })
    cy.get('.btn-sort button').should('exist')
  })

  it('opens access editing for selected pages', () => {
    mountList({}, { 'access:view': true, 'page:publish': true, 'page:view': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      vm.$refs.tree.statsFlat = [{ _checked: true, data: { id: 'page-1', has: 2 } }]
      vm.editAccess()

      expect(vm.accessIds).to.deep.equal(['page-1'])
      expect(vm.accessDescendants).to.equal(2)
      expect(vm.accessDialog).to.equal(true)
    })
  })

  it('updates access indicators for selected pages and descendants', () => {
    mountList({}, { 'access:view': true, 'page:publish': true, 'page:view': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const root = { _checked: true, data: { id: 'page-1', access: null, restricted: false } }
      const child = { _checked: false, data: { id: 'page-2', access: null, restricted: false }, parent: root }
      const other = { _checked: false, data: { id: 'page-3', access: null, restricted: false } }
      vm.$refs.tree.statsFlat = [root, child, other]

      vm.accessApplied([], true)

      expect(root.data.access).to.deep.equal([])
      expect(root.data.restricted).to.equal(true)
      expect(child.data.access).to.deep.equal([])
      expect(child.data.restricted).to.equal(true)
      expect(other.data.access).to.equal(null)
      expect(other.data.restricted).to.equal(false)
    })
  })

  it('describes page access in lock titles', () => {
    mountList({}, { 'page:view': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm

      expect(vm.accessTitle([])).to.equal('Authenticated users')
      expect(vm.accessTitle(['member', 'staff'])).to.equal('Access: member, staff')
      expect(vm.accessTitle(undefined)).to.equal('Restricted')
    })
  })

  it('saves selected page status through one bulk mutation', () => {
    const mutate = cy.stub().resolves({ data: { bulkPage: { ids: ['page-1', 'page-2'] } } })

    mountList({}, { 'page:save': true, 'page:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const stats = [
        { _checked: true, data: { id: 'page-1', status: 1 } },
        { _checked: true, data: { id: 'page-2', status: 1 } },
      ]
      vm.$refs.tree.statsFlat = stats

      return vm.status(null, 0).then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.equal({
          id: ['page-1', 'page-2'],
          input: { status: 0 },
        })
        expect(stats.map((stat) => stat.data.status)).to.deep.equal([0, 0])
      })
    })
  })
})
