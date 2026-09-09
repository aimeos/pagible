import { loadVersions } from '../../../js/version'

const context = () => ({
  user: { can: cy.stub().returns(true) },
  $apollo: { query: cy.stub() },
  messages: { add: cy.spy() },
  $gettext: value => value,
  $log: cy.spy()
})

const load = (vm, key = 'page', id = 'id', convert = value => value) =>
  loadVersions(vm, 'query', key, id, 'Could not load history', convert)

describe('History version loading', () => {
  for (const key of ['page', 'element', 'file']) {
    it(`loads and converts ${key} snapshots without caching`, async () => {
      const vm = context()
      vm.$apollo.query.resolves({ data: { [key]: { versions: [{ data: '{"title":"Saved"}' }] } } })
      const result = await load(vm, key, 'saved-id', version => ({ data: JSON.parse(version.data) }))
      expect(result).to.deep.equal([{ data: { title: 'Saved' } }])
      expect(vm.user.can).to.have.been.calledWith(key + ':view')
      expect(vm.$apollo.query).to.have.been.calledOnceWithExactly({ query: 'query', variables: { id: 'saved-id' }, fetchPolicy: 'no-cache' })
    })
  }

  it('skips requests when permission or an ID is missing', async () => {
    const vm = context()
    vm.user.can.returns(false)
    expect(await load(vm)).to.deep.equal([])
    expect(vm.messages.add).to.have.been.calledOnceWithExactly('Permission denied', 'error')
    vm.user.can.returns(true)
    expect(await load(vm, 'page', '')).to.deep.equal([])
    expect(vm.$apollo.query).not.to.have.been.called
  })

  it('returns empty history for an item with no versions', async () => {
    const vm = context()
    vm.$apollo.query.resolves({ data: { page: {} } })
    expect(await load(vm)).to.deep.equal([])
  })

  for (const failure of ['network', 'graphql', 'missing item', 'conversion']) {
    it(`reports ${failure} failures without disguising them as empty history`, async () => {
      const vm = context()
      if (failure === 'network') vm.$apollo.query.rejects(new Error('Offline'))
      else vm.$apollo.query.resolves(failure === 'graphql' ? { errors: [{ message: 'Denied' }] }
        : { data: { page: failure === 'missing item' ? null : { versions: [{}] } } })
      expect(await load(vm, 'page', 'id', () => { throw new Error('Invalid snapshot') })).to.equal(undefined)
      expect(vm.messages.add).to.have.been.calledOnce
      expect(vm.messages.add.firstCall.args[0]).to.include('Could not load history')
      expect(vm.$log).to.have.been.calledOnce
    })
  }
})
