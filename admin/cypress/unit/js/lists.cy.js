import { print } from 'graphql'
import { listBase, mutation, trashMessage } from '../../../js/lists'

const { methods } = listBase

function list(props = {}) {
  const vm = {
    type: 'file',
    items: [],
    checked: new Set(),
    loading: false,
    outdated: true,
    last: 1,
    searchSeq: 0,
    user: { can: () => true },
    messages: { add: cy.stub(), error: cy.stub(), denied: cy.stub() },
    $gettext: (msg) => msg,
    $ngettext: (one, many, num) => (num === 1 ? one : many),
    failed: (action) => `Error ${action}`,
    hydrate: (entry) => entry,
    options: () => ({}),
    ...props
  }

  for (const name of ['allowed', 'reload', 'search', 'targets', 'selected', 'drop', 'keep', 'mutate', 'invalidate']) {
    vm[name] ??= methods[name].bind(vm)
  }

  return vm
}

describe('mutation()', () => {
  it('adds the variables for the language and the publish date', () => {
    const doc = print(mutation('pub', 'page', true, true))

    expect(doc).to.include('$at: DateTime')
    expect(doc).to.include('$lang: String')
    expect(doc).to.include('pubPage(id: $id, at: $at, lang: $lang)')
  })

  it('requires the language for the variant mutations', () => {
    const doc = print(mutation('drop', 'pageVariant', true))

    expect(doc).to.include('$lang: String!')
    expect(doc).to.include('dropPageVariant(id: $id, lang: $lang)')
  })

  it('caches the documents per variant', () => {
    expect(mutation('pub', 'file', false, true)).to.equal(mutation('pub', 'file', false, true))
    expect(mutation('pub', 'file', false, true)).not.to.equal(mutation('pub', 'file'))
  })
})

describe('trashMessage()', () => {
  it('adds the undo action only if passed', () => {
    const vm = list()
    const undo = () => {}

    trashMessage(vm, 2, undo)
    trashMessage(vm, 1)

    expect(vm.messages.add.firstCall.args[0]).to.equal('%{num} entries moved to trash')
    expect(vm.messages.add.firstCall.args[3]).to.deep.equal({ label: 'Undo', handler: undo })
    expect(vm.messages.add.secondCall.args[3]).to.be.null
  })
})

describe('listBase', () => {
  it('stops loading after the search succeeded', () => {
    const vm = list({
      $apollo: { query: cy.stub().resolves({ data: { files: { data: [{ id: '1' }], paginatorInfo: { lastPage: 2 } } } }) }
    })

    return vm.search().then((items) => {
      expect(items).to.deep.equal([{ id: '1' }])
      expect(vm.last).to.equal(2)
      expect(vm.loading).to.be.false
    })
  })

  it('stops loading after the search failed', () => {
    const vm = list({ $apollo: { query: cy.stub().rejects(new Error('offline')) } })

    return vm.search().then(() => {
      expect(vm.loading).to.be.false
      expect(vm.messages.error).to.have.been.calledWith('Error search')
    })
  })

  it('stops loading without searching or a message if the user can not view the items', () => {
    const query = cy.stub()
    const vm = list({ loading: true, user: { can: () => false }, $apollo: { query } })

    return vm.search().then((items) => {
      expect(items).to.deep.equal([])
      expect(vm.loading).to.be.false
      expect(query).not.to.have.been.called
      expect(vm.messages.denied).not.to.have.been.called
    })
  })

  it('goes back to the last page if the current page has no items any more', () => {
    const vm = list({
      page: 3,
      $apollo: { query: cy.stub().resolves({ data: { files: { data: [], paginatorInfo: { lastPage: 2 } } } }) }
    })

    return vm.search().then(() => {
      expect(vm.last).to.equal(2)
      expect(vm.page).to.equal(2)
    })
  })

  it('drops only the cached list on reload', () => {
    const cache = { evict: cy.stub(), gc: cy.stub() }
    const clearStore = cy.stub()
    const vm = list({
      $apollo: {
        provider: { defaultClient: { cache, clearStore } },
        query: cy.stub().resolves({ data: { files: { data: [{ id: '1' }] } } })
      }
    })

    return vm.reload().then((items) => {
      expect(cache.evict).to.have.been.calledWith({ id: 'ROOT_QUERY', fieldName: 'files' })
      expect(clearStore).not.to.have.been.called
      expect(items).to.deep.equal([{ id: '1' }])
      expect(vm.loading).to.be.false
    })
  })

  it('applies only the latest search response', () => {
    let resolveFirst
    const query = cy.stub()
    query.onFirstCall().returns(new Promise((resolve) => (resolveFirst = resolve)))
    query.onSecondCall().resolves({ data: { files: { data: [{ id: 'new' }] } } })
    const vm = list({ $apollo: { query } })

    const first = vm.search()

    return vm.search().then(() => {
      expect(vm.loading).to.be.false
      vm.loading = true // still set while the old response arrives
      resolveFirst({ data: { files: { data: [{ id: 'old' }] } } })
      return first
    }).then(() => {
      expect(vm.items).to.deep.equal([{ id: 'new' }])
      expect(vm.loading).to.be.true
    })
  })

  it('goes back to the first page when the search changes', () => {
    const vm = list({ page: 3, debouncedSearch: Object.assign(cy.stub(), { cancel: cy.stub() }), search: cy.stub() })
    const research = methods.research.bind(vm)

    research(true)
    expect(vm.page).to.equal(1)
    expect(vm.search).not.to.have.been.called
    expect(vm.debouncedSearch).not.to.have.been.called

    research(true)
    expect(vm.debouncedSearch).to.have.been.calledOnce

    research()
    expect(vm.search).to.have.been.calledOnce
  })

  it('checks all items if one is unchecked, otherwise unchecks all', () => {
    const vm = list({ items: [{ id: '1' }, { id: '2' }], checked: new Set(['1']) })
    Object.defineProperty(vm, 'allChecked', { get: () => listBase.computed.allChecked.call(vm) })

    expect(vm.allChecked).to.be.false

    methods.toggle.call(vm)
    expect([...vm.checked]).to.deep.equal(['1', '2'])
    expect(vm.allChecked).to.be.true

    methods.toggle.call(vm)
    expect(vm.checked.size).to.equal(0)
    expect(vm.allChecked).to.be.false
  })

  it('keeps only the selected items still shown after changing the language of a single item', () => {
    const vm = list({
      editIds: ['3'],
      editSelected: false,
      checked: new Set(['1', '2']),
      $apollo: { mutate: cy.stub().resolves({}) },
      invalidate: cy.stub()
    })
    vm.search = cy.stub().callsFake(() => {
      vm.items = [{ id: '3' }, { id: '2' }]
      vm.checked = new Set()
      return Promise.resolve(vm.items)
    })

    return methods.save.call(vm, 'de').then(() => {
      expect([...vm.checked]).to.deep.equal(['2'])
    })
  })

  it('shows the trash message with undo after dropping', () => {
    const item = { id: '1' }
    const vm = list({ mutate: cy.stub().resolves(true), keep: cy.stub() })

    vm.drop(item)

    cy.wrap(vm.messages.add).should('have.been.calledOnce').then(() => {
      const action = vm.messages.add.firstCall.args[3]
      expect(vm.messages.add.firstCall.args[0]).to.equal('Moved to trash')
      action.handler()
      expect(vm.keep).to.have.been.calledWith([item])
    })
  })

  it('shows the trash message without undo if restoring is not allowed', () => {
    const vm = list({ mutate: cy.stub().resolves(true), user: { can: (perm) => perm !== 'file:keep' } })

    vm.drop({ id: '1' })

    cy.wrap(vm.messages.add).should('have.been.calledOnce').then(() => {
      expect(vm.messages.add.firstCall.args[3]).to.be.null
    })
  })
})
