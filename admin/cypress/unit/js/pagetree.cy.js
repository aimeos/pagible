import { dragContext } from '@he-tree/vue'
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

  describe('insert()', () => {
    function inserter(mutate) {
      const vm = {
        $apollo: { mutate },
        $gettext: (msg) => msg,
        $refs: { tree: { add: cy.stub(), getSiblings: (stat) => [stat] } },
        allowed: () => true,
        create: (props) => ({ ...props, lang: 'en' }),
        invalidate: cy.stub(),
        load: cy.stub(),
        messages: { error: cy.stub() },
        updateHas: cy.stub()
      }

      for (const name of ['insert', 'target']) {
        vm[name] = pageTree.methods[name].bind(vm)
      }

      return vm
    }

    it('loads unloaded children after inserting into a collapsed page', () => {
      const vm = inserter(cy.stub().resolves({ data: { addPage: { id: 'new' } } }))
      const stat = { open: false, children: [], data: { id: 'parent', has: 2 } }

      return vm.insert(stat).then(() => {
        expect(vm.load).to.have.been.calledOnceWith(stat, stat.data)
        expect(vm.load).to.have.been.calledAfter(vm.$apollo.mutate)
        expect(vm.$refs.tree.add).not.to.have.been.called
      })
    })

    it('opens collapsed pages with loaded children and adds the page first', () => {
      const vm = inserter(cy.stub().resolves({ data: { addPage: { id: 'new' } } }))
      const child = { data: { id: 'child' } }
      const stat = { open: false, children: [child], data: { id: 'parent', has: 1 } }
      vm.load.callsFake(() => (stat.open = true))

      return vm.insert(stat).then(() => {
        expect(vm.load).to.have.been.calledBefore(vm.$apollo.mutate)
        expect(vm.$apollo.mutate.firstCall.args[0].variables.ref).to.equal('child')
        expect(vm.$refs.tree.add).to.have.been.calledOnceWith(Cypress.sinon.match({ id: 'new' }), stat, 0)
      })
    })
  })

  describe('change()', () => {
    it('ignores keyboard moves without a dragged node', () => {
      const vm = { $refs: { tree: { dragNode: null } }, movePage: cy.stub() }

      pageTree.methods.change.call(vm)

      expect(vm.movePage).not.to.have.been.called
    })

    it('reloads the tree if moving the dropped page fails', () => {
      const node = { data: { id: 'moved', has: 0 } }
      const vm = {
        $refs: { tree: { dragNode: node } },
        movePage: cy.stub().resolves(false),
        position: () => ({ id: 'moved', parent: 'new', ref: null }),
        reload: cy.stub(),
        updateHas: cy.stub(),
        moved: cy.stub()
      }
      cy.stub(dragContext, 'startInfo').get(() => ({ dragNode: node, parent: { data: { id: 'old' } } }))
      cy.stub(dragContext, 'targetInfo').get(() => ({ parent: { data: { id: 'new' } } }))

      return pageTree.methods.change.call(vm).then(() => {
        expect(vm.reload).to.have.been.calledOnceWith(false)
        expect(vm.updateHas).not.to.have.been.called
        expect(vm.moved).not.to.have.been.called
      })
    })
  })

  describe('reorder()', () => {
    it('reloads the tree if saving the keyboard move fails', () => {
      const stat = { data: { id: 'moved' } }
      const vm = {
        $nextTick: (fcn) => fcn(),
        $refs: { tree: { activeDescendant: stat } },
        movePage: cy.stub().resolves(false),
        position: () => ({ id: 'moved', parent: null, ref: null }),
        reload: cy.stub()
      }

      pageTree.methods.reorder.call(vm, { key: 'ArrowUp' })

      cy.wrap(vm.reload).should('have.been.calledOnceWith', false)
    })

    function reorderer(stat, positions) {
      const vm = {
        $nextTick: (fcn) => fcn(),
        $refs: { tree: { activeDescendant: stat } },
        movePage: cy.stub().resolves(true),
        position: cy.stub(),
        reload: cy.stub(),
        updateHas: cy.stub(),
        moved: cy.stub()
      }
      positions.forEach((pos, idx) => vm.position.onCall(idx).returns(pos))

      return vm
    }

    it('updates the page counts and offers undo after a keyboard move', () => {
      const oldparent = { data: { id: 'old' } }
      const newparent = { data: { id: 'new' } }
      const stat = { parent: oldparent, data: { id: 'moved', has: 2 } }
      const origin = { id: 'moved', parent: 'old', ref: 'next' }
      const vm = reorderer(stat, [origin, { id: 'moved', parent: 'new', ref: null }])

      pageTree.methods.reorderStart.call(vm, { key: 'ArrowLeft' })
      stat.parent = newparent // he-tree moves the page between both handlers
      pageTree.methods.reorder.call(vm, { key: 'ArrowLeft' })

      cy.wrap(vm.moved).should('have.been.calledOnceWith', origin).then(() => {
        expect(vm.movePage).to.have.been.calledOnceWith('moved', 'new', null)
        expect(vm.updateHas).to.have.been.calledWith(oldparent, -3)
        expect(vm.updateHas).to.have.been.calledWith(newparent, 3)
        expect(vm.reload).not.to.have.been.called
      })
    })

    it('does not save keyboard moves which did not move the page', () => {
      const stat = { parent: null, data: { id: 'moved' } }
      const pos = { id: 'moved', parent: null, ref: 'next' }
      const vm = reorderer(stat, [pos, { ...pos }])

      pageTree.methods.reorderStart.call(vm, { key: 'ArrowUp' })
      pageTree.methods.reorder.call(vm, { key: 'ArrowUp' })

      expect(vm.movePage).not.to.have.been.called
      expect(vm.moved).not.to.have.been.called
    })

    it('ignores keys other than the arrow keys', () => {
      const vm = reorderer({ data: { id: 'moved' } }, [])

      pageTree.methods.reorderStart.call(vm, { key: 'Enter' })
      pageTree.methods.reorder.call(vm, { key: 'Enter' })

      expect(vm.keyOrigin).to.be.undefined
      expect(vm.movePage).not.to.have.been.called
    })
  })

  describe('paste()', () => {
    function paster() {
      const vm = {
        clip: { type: 'copy', node: { id: 'source' } },
        $apollo: { mutate: cy.stub().resolves({ data: { copyPage: { id: 'copy' } } }) },
        $gettext: (msg) => msg,
        $refs: { tree: { add: cy.stub(), getSiblings: (s) => [s] } },
        allowed: () => true,
        invalidate: cy.stub(),
        load: cy.stub(),
        messages: { error: cy.stub() },
        pages: cy.stub().resolves({ data: [{ id: 'copy', has: 0 }] }),
        updateHas: cy.stub()
      }

      vm.target = pageTree.methods.target.bind(vm)
      vm.paste = pageTree.methods.paste.bind(vm)

      return vm
    }

    it('adds the copy to a collapsed page with loaded children and opens it', () => {
      const stat = { open: false, children: [{ data: { id: 'child' } }], data: { id: 'parent', has: 1 } }
      const vm = paster()

      return vm.paste(stat).then(() => {
        expect(vm.$refs.tree.add).to.have.been.calledOnceWith({ id: 'copy', has: 0 }, stat, 0)
        expect(vm.load).not.to.have.been.called
        expect(stat.open).to.be.true
      })
    })

    it('loads the children of a collapsed page incl. the copy if they are unknown', () => {
      const stat = { open: false, children: [], data: { id: 'parent', has: 1 } }
      const vm = paster()

      return vm.paste(stat).then(() => {
        expect(vm.$refs.tree.add).not.to.have.been.called
        expect(vm.load).to.have.been.calledOnceWith(stat, stat.data)
      })
    })
  })

  describe('move()', () => {
    function mover() {
      const clip = { node: { id: 'moved' }, stat: { parent: null, data: { id: 'moved', has: 0 }, cut: true } }
      const vm = {
        clip,
        $refs: { tree: { getSiblings: (s) => [s], move: cy.stub(), remove: cy.stub() } },
        load: cy.stub(),
        movePage: cy.stub().resolves(true),
        moved: cy.stub(),
        position: () => ({ id: 'moved', parent: null, ref: null }),
        updateHas: cy.stub()
      }

      vm.target = pageTree.methods.target.bind(vm)
      vm.move = pageTree.methods.move.bind(vm)

      return { vm, clip }
    }

    it('loads the children of a collapsed page instead of adding the moved page alone', () => {
      const stat = { open: false, children: [], data: { id: 'parent', has: 2 } }
      const { vm, clip } = mover()

      return vm.move(stat).then((success) => {
        expect(success).to.be.true
        expect(vm.$refs.tree.move).not.to.have.been.called
        expect(vm.$refs.tree.remove).to.have.been.calledOnceWith(clip.stat)
        expect(vm.load).to.have.been.calledOnceWith(stat, stat.data)
        expect(clip.stat.cut).to.be.undefined
      })
    })

    it('moves the page into a collapsed page with loaded children and opens it', () => {
      const stat = { open: false, children: [{ data: { id: 'child' } }], data: { id: 'parent', has: 1 } }
      const { vm, clip } = mover()

      return vm.move(stat).then(() => {
        expect(vm.$refs.tree.move).to.have.been.calledOnceWith(clip.stat, stat, 0)
        expect(vm.load).not.to.have.been.called
        expect(stat.open).to.be.true
      })
    })

    it('moves the page into open pages directly', () => {
      const stat = { open: true, children: [], data: { id: 'parent', has: 0 } }
      const { vm, clip } = mover()

      return vm.move(stat).then(() => {
        expect(vm.$refs.tree.move).to.have.been.calledOnceWith(clip.stat, stat, 0)
        expect(vm.$refs.tree.remove).not.to.have.been.called
        expect(vm.load).not.to.have.been.called
      })
    })
  })
})
