import { pageTree } from '../../../js/pagetree'

function tree(fetch, stats = []) {
  const vm = {
    fetch,
    messages: { error: cy.stub() },
    $gettext: (msg) => msg,
    $refs: { tree: { statsFlat: stats, addMulti: cy.stub(), _focusNode: cy.stub() } }
  }

  for (const name of ['load', 'toggle']) {
    vm[name] = pageTree.methods[name].bind(vm)
  }

  return vm
}

describe('pageTree', () => {
  describe('load()', () => {
    it('ignores clicks while the subpages are loading', () => {
      const fetch = cy.stub().returns(new Promise(() => {}))
      const vm = tree(fetch)
      const stat = { open: false }

      vm.load(stat, { id: '1' })
      vm.load(stat, { id: '1' })

      expect(fetch).to.have.been.calledOnce
      expect(stat.open).to.equal(true)
      expect(stat.loading).to.equal(true)
    })

    it('closes the node again if nothing was fetched', () => {
      let done
      const vm = tree(cy.stub().returns(new Promise((resolve) => (done = resolve))))
      const stat = { open: false }

      vm.load(stat, { id: '1' })
      done(undefined)

      cy.wrap(stat).its('loading').should('equal', false).then(() => {
        expect(stat.open).to.equal(false)
        expect(vm.$refs.tree.addMulti).not.to.have.been.called
      })
    })

    it('reports errors while adding the subpages', () => {
      const vm = tree(cy.stub().resolves({ data: [{ id: '2' }], currentPage: 1 }))
      vm.$refs.tree.addMulti.throws(new Error('failed'))
      const stat = { open: false }

      vm.load(stat, { id: '1' })

      cy.wrap(stat).its('loading').should('equal', false).then(() => {
        expect(stat.open).to.equal(false)
        expect(vm.messages.error).to.have.been.calledWith('Error fetching pages')
      })
    })
  })

  describe('toggle()', () => {
    it('checks all pages if one is unchecked, otherwise unchecks all', () => {
      const stats = [{ _checked: true }, { _checked: false }]
      const vm = tree(cy.stub(), stats)

      vm.toggle()
      expect(stats.map((stat) => stat._checked)).to.deep.equal([true, true])

      vm.toggle()
      expect(stats.map((stat) => stat._checked)).to.deep.equal([false, false])
    })
  })
})
