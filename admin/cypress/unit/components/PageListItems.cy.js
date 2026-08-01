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

  it('loads the saved list view with its active filters', () => {
    const query = cy.stub().resolves({
      data: { pages: { data: [], paginatorInfo: { currentPage: 1, lastPage: 1 } } }
    })

    mountList({ filter: { view: 'list', status: 0 } }, { 'page:view': true }, { query }).then(() => {
      expect(query).to.have.been.calledOnce
      expect(query.firstCall.args[0].variables.filter).to.deep.equal({ status: 0 })
      expect(query.firstCall.args[0].variables.sort).to.deep.equal([{ column: 'LFT', order: 'ASC' }])
    })
  })

  it('keeps the latest reload result when an older request finishes afterwards', () => {
    const response = (id, name) => ({
      data: {
        pages: {
          data: [{
            id,
            parent_id: null,
            created_at: '2026-01-01 00:00:00',
            deleted_at: null,
            editor: 'test@test.com',
            has: 0,
            restricted: false,
            latest: {
              id: `${id}-latest`,
              published: true,
              publish_at: null,
              data: JSON.stringify({ name }),
              editor: 'test@test.com',
              created_at: '2026-01-01 00:00:00'
            }
          }],
          paginatorInfo: { currentPage: 1, lastPage: 1 }
        }
      }
    })
    let finishInitial
    const query = cy.stub()
    query.onFirstCall().returns(new Promise((resolve) => { finishInitial = resolve }))
    query.onSecondCall().resolves(response('new', 'New page'))

    mountList({ filter: { view: 'list' } }, { 'page:view': true }, { query }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm

      return vm.reload(false).then(() => {
        finishInitial(response('old', 'Old page'))

        return Cypress.Promise.resolve().then(() => {
          expect(vm.items.map((item) => item.id)).to.deep.equal(['new'])
          expect(vm.loading).to.equal(false)
        })
      })
    })
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

  it('opens bulk editing and access control for one page node', () => {
    mountList({}, { 'access:view': true, 'page:publish': true, 'page:save': true, 'page:view': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const node = { _checked: false, data: { id: 'page-1', has: 2 } }
      const selected = { _checked: true, data: { id: 'page-2', has: 0 } }
      vm.$refs.tree.statsFlat = [node, selected]

      vm.editProps(node)
      expect(vm.propsCount).to.equal(1)
      expect(vm.propsDescendants).to.equal(2)
      expect(vm.propsIds).to.deep.equal(['page-1'])
      expect(vm.propsDialog).to.equal(true)

      vm.editAccess(node)
      expect(vm.accessIds).to.deep.equal(['page-1'])
      expect(vm.accessDescendants).to.equal(2)
      expect(vm.accessDialog).to.equal(true)
      expect(selected._checked).to.equal(true)
    })
  })

  it('updates access indicators for one page node and its descendants', () => {
    mountList({}, { 'access:view': true, 'page:publish': true, 'page:view': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const root = { _checked: false, data: { id: 'page-1', access: null, restricted: false } }
      const child = { _checked: false, data: { id: 'page-2', access: null, restricted: false }, parent: root }
      const other = { _checked: true, data: { id: 'page-3', access: null, restricted: false } }
      vm.$refs.tree.statsFlat = [root, child, other]

      vm.editAccess(root)
      vm.accessApplied([], true)

      expect(root.data.access).to.deep.equal([])
      expect(root.data.restricted).to.equal(true)
      expect(child.data.access).to.deep.equal([])
      expect(child.data.restricted).to.equal(true)
      expect(other.data.access).to.equal(null)
      expect(other.data.restricted).to.equal(false)
      expect(other._checked).to.equal(true)
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

  it('updates missing tree fields after a page is saved in the detail view', () => {
    mountList({}, { 'page:view': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const stat = { data: { id: 'page-1' } }
      const data = {
        cache: 15,
        domain: 'example.com',
        lang: 'de',
        name: 'Updated name',
        path: 'updated-path',
        status: 2,
        tag: 'updated-tag',
        theme: 'corporate',
        title: 'Updated title',
        to: '/target',
        type: 'landing',
      }
      vm.$refs.tree.statsFlat = [stat]

      vm.changes.notify('page', { id: 'page-1', ...data, content: [{ type: 'text' }] })

      return vm.$nextTick().then(() => {
        expect(stat.data).to.include(data)
        expect(stat.data).not.to.have.property('content')
        expect(vm.changes.get('page')).to.be.empty
      })
    })
  })

  it('inherits the parent theme and type when inserting a child page', () => {
    const mutate = cy.stub().resolves({ data: { addPage: { id: 'page-new' } } })

    mountList({}, { 'page:add': true, 'page:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const parent = {
        children: [],
        data: { id: 'page-parent', has: 0, theme: 'corporate', type: 'landing' },
        open: true,
      }
      vm.$refs.tree.getSiblings = () => [parent]

      return vm.insert(parent).then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.include({
          parent: 'page-parent',
          ref: null,
        })
        expect(mutate.firstCall.args[0].variables.input).to.include({
          theme: 'corporate',
          type: 'landing',
        })
      })
    })
  })

  it('inherits the containing parent theme and type when inserting beside a page', () => {
    const mutate = cy.stub().resolves({ data: { addPage: { id: 'page-new' } } })

    mountList({}, { 'page:add': true, 'page:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const parent = {
        children: [],
        data: { id: 'page-parent', has: 1, theme: 'corporate', type: 'landing' },
      }
      const sibling = {
        data: { id: 'page-sibling', theme: 'editorial', type: 'article' },
        parent,
      }
      vm.$refs.tree.getSiblings = () => [sibling]

      return vm.insert(sibling, 0).then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.include({
          parent: 'page-parent',
          ref: 'page-sibling',
        })
        expect(mutate.firstCall.args[0].variables.input).to.include({
          theme: 'corporate',
          type: 'landing',
        })
      })
    })
  })

  it('clears the selected page subtree with cache:clear permission', () => {
    const mutate = cy.stub().resolves({ data: { clearCache: 3 } })

    mountList({}, { 'cache:clear': true, 'page:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm

      return vm.clear({ data: { id: 'page-1' } }).then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.equal({ id: 'page-1' })
      })
    })
  })

  it('does not clear page caches without cache:clear permission', () => {
    const mutate = cy.stub().resolves({ data: { clearCache: 1 } })

    mountList({}, { 'page:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm

      vm.clear({ data: { id: 'page-1' } })
      expect(mutate).not.to.have.been.called
    })
  })

  it('saves properties for one page node without changing the selection', () => {
    const mutate = cy.stub().resolves({
      data: {
        bulkPage: {
          ids: ['page-1'],
          latest: '{}',
          data: '{"status":0}',
          failed: 0,
        },
      },
    })

    mountList({}, { 'page:save': true, 'page:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const node = { _checked: false, data: { id: 'page-1', status: 1, has: 2 } }
      const selected = { _checked: true, data: { id: 'page-2', status: 1, has: 0 } }
      vm.$refs.tree.statsFlat = [node, selected]
      vm.editProps(node)

      return vm.saveProps({ input: { status: 0 }, descendants: true }).then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.equal({
          id: ['page-1'],
          input: { status: 0 },
          descendants: true,
        })
        expect(node.data.status).to.equal(0)
        expect(selected._checked).to.equal(true)
      })
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
